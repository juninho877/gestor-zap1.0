/*
  # Criação de tabelas para funcionalidades avançadas

  1. Tabelas para Sistema de Notificações
    - `notifications` - Notificações internas do sistema
    - `activity_logs` - Log de atividades dos usuários

  2. Tabelas para Sistema de Tickets
    - `tickets` - Tickets de suporte
    - `ticket_attachments` - Anexos dos tickets
    - `ticket_responses` - Respostas dos tickets

  3. Tabelas para Sistema de Afiliados
    - `affiliates` - Afiliados cadastrados
    - `affiliate_clicks` - Cliques nos links de afiliados
    - `affiliate_conversions` - Conversões dos afiliados

  4. Tabelas para Mensagens Agendadas
    - `scheduled_messages` - Mensagens agendadas

  5. Tabelas para Chat Interno
    - `client_interactions` - Interações com clientes

  6. Tabelas para Campanhas de Marketing
    - `campaigns` - Campanhas de marketing
    - `campaign_sends` - Envios das campanhas
    - `campaign_recipients` - Destinatários das campanhas

  7. Atualizações nas tabelas existentes
    - Adicionar score de inadimplência aos clientes
*/

-- Tabela de notificações internas
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'system') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_id (user_id),
    INDEX idx_notifications_read_at (read_at),
    INDEX idx_notifications_created_at (created_at)
);

-- Tabela de log de atividades
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_activity_logs_user_id (user_id),
    INDEX idx_activity_logs_action (action),
    INDEX idx_activity_logs_created_at (created_at)
);

-- Tabela de tickets de suporte
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    category VARCHAR(100) NULL,
    assigned_to INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tickets_user_id (user_id),
    INDEX idx_tickets_status (status),
    INDEX idx_tickets_priority (priority),
    INDEX idx_tickets_created_at (created_at)
);

-- Tabela de anexos dos tickets
CREATE TABLE IF NOT EXISTS ticket_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_attachments_ticket_id (ticket_id)
);

-- Tabela de respostas dos tickets
CREATE TABLE IF NOT EXISTS ticket_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_responses_ticket_id (ticket_id),
    INDEX idx_ticket_responses_created_at (created_at)
);

-- Tabela de afiliados
CREATE TABLE IF NOT EXISTS affiliates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    affiliate_code VARCHAR(50) UNIQUE NOT NULL,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    total_clicks INT DEFAULT 0,
    total_conversions INT DEFAULT 0,
    total_commission DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_affiliates_code (affiliate_code),
    INDEX idx_affiliates_user_id (user_id),
    INDEX idx_affiliates_level (level)
);

-- Tabela de cliques dos afiliados
CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    referrer VARCHAR(500) NULL,
    landing_page VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    INDEX idx_affiliate_clicks_affiliate_id (affiliate_id),
    INDEX idx_affiliate_clicks_created_at (created_at)
);

-- Tabela de conversões dos afiliados
CREATE TABLE IF NOT EXISTS affiliate_conversions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT NOT NULL,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    payment_id INT NULL,
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    INDEX idx_affiliate_conversions_affiliate_id (affiliate_id),
    INDEX idx_affiliate_conversions_user_id (user_id),
    INDEX idx_affiliate_conversions_created_at (created_at)
);

-- Tabela de mensagens agendadas
CREATE TABLE IF NOT EXISTS scheduled_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    client_id INT NULL,
    template_id INT NULL,
    message TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    scheduled_for TIMESTAMP NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
    INDEX idx_scheduled_messages_user_id (user_id),
    INDEX idx_scheduled_messages_scheduled_for (scheduled_for),
    INDEX idx_scheduled_messages_status (status)
);

-- Tabela de interações com clientes
CREATE TABLE IF NOT EXISTS client_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    client_id INT NOT NULL,
    type ENUM('message', 'payment', 'note', 'status_change', 'call', 'meeting') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_interactions_client_id (client_id),
    INDEX idx_client_interactions_type (type),
    INDEX idx_client_interactions_created_at (created_at)
);

-- Tabela de campanhas de marketing
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    template_id INT NULL,
    target_audience JSON NULL,
    scheduled_for TIMESTAMP NULL,
    recurrence_pattern VARCHAR(100) NULL,
    status ENUM('draft', 'scheduled', 'running', 'completed', 'cancelled') DEFAULT 'draft',
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    delivered_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
    INDEX idx_campaigns_user_id (user_id),
    INDEX idx_campaigns_status (status),
    INDEX idx_campaigns_scheduled_for (scheduled_for)
);

-- Tabela de envios das campanhas
CREATE TABLE IF NOT EXISTS campaign_sends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    client_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_campaign_sends_campaign_id (campaign_id),
    INDEX idx_campaign_sends_status (status),
    INDEX idx_campaign_sends_sent_at (sent_at)
);

-- Tabela de destinatários das campanhas
CREATE TABLE IF NOT EXISTS campaign_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    client_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_campaign_client (campaign_id, client_id),
    INDEX idx_campaign_recipients_campaign_id (campaign_id)
);

-- Adicionar score de inadimplência aos clientes
ALTER TABLE clients
ADD COLUMN inadimplencia_score INT DEFAULT 0 AFTER next_payment_date,
ADD INDEX idx_clients_inadimplencia_score (inadimplencia_score);

-- Adicionar campos para afiliados nos usuários
ALTER TABLE users
ADD COLUMN referred_by VARCHAR(50) NULL AFTER manual_pix_key,
ADD COLUMN affiliate_id INT NULL AFTER referred_by,
ADD FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL;

-- Adicionar configurações para o sistema
INSERT INTO app_settings (`key`, `value`, description, type) VALUES
('default_language', 'pt-BR', 'Idioma padrão do sistema', 'string'),
('enable_affiliates', 'true', 'Habilitar sistema de afiliados', 'boolean'),
('affiliate_commission_rate', '10.00', 'Taxa de comissão padrão para afiliados (%)', 'number'),
('enable_notifications', 'true', 'Habilitar notificações internas', 'boolean'),
('enable_tickets', 'true', 'Habilitar sistema de tickets', 'boolean'),
('max_file_upload_size', '5242880', 'Tamanho máximo para upload de arquivos (bytes)', 'number'),
('pwa_enabled', 'true', 'Habilitar funcionalidades PWA', 'boolean'),
('firebase_config', '{}', 'Configuração do Firebase para push notifications', 'json');