<?php
/**
 * Cron Job para Mensagens Agendadas
 * 
 * Este script deve ser executado a cada minuto pelo cron do servidor
 * Exemplo de configuração no crontab:
 * * * * * * /usr/bin/php /caminho/para/seu/projeto/cron_scheduled_messages.php
 */

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Incluir arquivos necessários
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/ScheduledMessage.php';
require_once __DIR__ . '/classes/WhatsAppAPI.php';
require_once __DIR__ . '/classes/MessageHistory.php';
require_once __DIR__ . '/webhook/cleanWhatsAppMessageId.php';

// Log de início
error_log("=== SCHEDULED MESSAGES CRON JOB STARTED ===");
error_log("Date: " . date('Y-m-d H:i:s'));

// Estatísticas do processamento
$stats = [
    'messages_processed' => 0,
    'messages_sent' => 0,
    'messages_failed' => 0,
    'errors' => []
];

try {
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Erro na conexão com o banco de dados");
    }
    
    // Inicializar classes
    $scheduledMessage = new ScheduledMessage($db);
    $whatsapp = new WhatsAppAPI();
    $messageHistory = new MessageHistory($db);
    
    // Buscar mensagens prontas para envio
    $pending_messages_stmt = $scheduledMessage->getPendingMessages();
    $pending_messages = $pending_messages_stmt->fetchAll();
    
    error_log("Found " . count($pending_messages) . " messages ready to send");
    
    foreach ($pending_messages as $message_data) {
        $stats['messages_processed']++;
        $message_id = $message_data['id'];
        $user_id = $message_data['user_id'];
        $instance_name = $message_data['whatsapp_instance'];
        
        error_log("Processing scheduled message ID: $message_id for user: $user_id");
        
        try {
            // Verificar se a instância está conectada
            if (!$whatsapp->isInstanceConnected($instance_name)) {
                error_log("WhatsApp instance not connected for user $user_id");
                
                // Marcar como falha
                $scheduledMessage->id = $message_id;
                $scheduledMessage->updateStatus('failed', 'WhatsApp não conectado');
                $stats['messages_failed']++;
                continue;
            }
            
            // Enviar mensagem
            $result = $whatsapp->sendMessage(
                $instance_name, 
                $message_data['phone'], 
                $message_data['message']
            );
            
            if ($result['status_code'] == 200 || $result['status_code'] == 201) {
                // Mensagem enviada com sucesso
                $scheduledMessage->id = $message_id;
                $scheduledMessage->updateStatus('sent');
                
                // Registrar no histórico de mensagens
                $messageHistory->user_id = $user_id;
                $messageHistory->client_id = $message_data['client_id'];
                $messageHistory->template_id = $message_data['template_id'];
                $messageHistory->message = $message_data['message'];
                $messageHistory->phone = $message_data['phone'];
                $messageHistory->status = 'sent';
                $messageHistory->payment_id = null;
                
                // Extrair e limpar ID da mensagem do WhatsApp se disponível
                if (isset($result['data']['key']['id'])) {
                    $raw_id = $result['data']['key']['id'];
                    $messageHistory->whatsapp_message_id = cleanWhatsAppMessageId($raw_id);
                }
                
                $messageHistory->create();
                
                $stats['messages_sent']++;
                error_log("Scheduled message sent successfully: $message_id");
                
            } else {
                // Falha no envio
                $error_message = $result['data']['message'] ?? 'Erro desconhecido';
                
                $scheduledMessage->id = $message_id;
                $scheduledMessage->updateStatus('failed', $error_message);
                
                $stats['messages_failed']++;
                error_log("Failed to send scheduled message $message_id: $error_message");
            }
            
            // Delay entre mensagens
            sleep(2);
            
        } catch (Exception $e) {
            error_log("Error processing scheduled message $message_id: " . $e->getMessage());
            
            // Marcar como falha
            $scheduledMessage->id = $message_id;
            $scheduledMessage->updateStatus('failed', $e->getMessage());
            
            $stats['messages_failed']++;
            $stats['errors'][] = "Mensagem $message_id: " . $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    error_log("Critical error in scheduled messages cron job: " . $e->getMessage());
    $stats['errors'][] = "Erro crítico: " . $e->getMessage();
}

// Log de estatísticas finais
error_log("=== SCHEDULED MESSAGES CRON JOB COMPLETED ===");
error_log("Messages processed: " . $stats['messages_processed']);
error_log("Messages sent: " . $stats['messages_sent']);
error_log("Messages failed: " . $stats['messages_failed']);
error_log("Errors: " . count($stats['errors']));

if (!empty($stats['errors'])) {
    foreach ($stats['errors'] as $error) {
        error_log("Error: " . $error);
    }
}
?>