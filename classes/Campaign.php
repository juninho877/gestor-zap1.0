<?php
require_once __DIR__ . '/../config/database.php';

class Campaign {
    private $conn;
    private $table_name = "campaigns";

    public $id;
    public $user_id;
    public $name;
    public $description;
    public $template_id;
    public $target_audience;
    public $scheduled_for;
    public $recurrence_pattern;
    public $status;
    public $total_recipients;
    public $sent_count;
    public $delivered_count;
    public $failed_count;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar uma nova campanha
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, name=:name, description=:description, 
                      template_id=:template_id, target_audience=:target_audience, 
                      scheduled_for=:scheduled_for, recurrence_pattern=:recurrence_pattern, 
                      status=:status";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":template_id", $this->template_id);
        $stmt->bindParam(":target_audience", $this->target_audience);
        $stmt->bindParam(":scheduled_for", $this->scheduled_for);
        $stmt->bindParam(":recurrence_pattern", $this->recurrence_pattern);
        $stmt->bindParam(":status", $this->status);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar campanhas do usuário
     */
    public function readByUser($user_id, $limit = 20, $offset = 0) {
        $query = "SELECT c.*, mt.name as template_name 
                  FROM " . $this->table_name . " c
                  LEFT JOIN message_templates mt ON c.template_id = mt.id
                  WHERE c.user_id = :user_id 
                  ORDER BY c.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar uma campanha específica
     */
    public function readOne() {
        $query = "SELECT c.*, mt.name as template_name, mt.message as template_message 
                  FROM " . $this->table_name . " c
                  LEFT JOIN message_templates mt ON c.template_id = mt.id
                  WHERE c.id = :id AND c.user_id = :user_id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->template_id = $row['template_id'];
            $this->target_audience = $row['target_audience'];
            $this->scheduled_for = $row['scheduled_for'];
            $this->recurrence_pattern = $row['recurrence_pattern'];
            $this->status = $row['status'];
            $this->total_recipients = $row['total_recipients'];
            $this->sent_count = $row['sent_count'];
            $this->delivered_count = $row['delivered_count'];
            $this->failed_count = $row['failed_count'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return $row;
        }
        return false;
    }

    /**
     * Atualizar campanha
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name=:name, description=:description, template_id=:template_id, 
                      target_audience=:target_audience, scheduled_for=:scheduled_for, 
                      recurrence_pattern=:recurrence_pattern, status=:status
                  WHERE id=:id AND user_id=:user_id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":template_id", $this->template_id);
        $stmt->bindParam(":target_audience", $this->target_audience);
        $stmt->bindParam(":scheduled_for", $this->scheduled_for);
        $stmt->bindParam(":recurrence_pattern", $this->recurrence_pattern);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        
        return $stmt->execute();
    }

    /**
     * Adicionar destinatários à campanha
     */
    public function addRecipients($client_ids) {
        if (empty($client_ids)) {
            return false;
        }
        
        $values = [];
        $params = [];
        
        foreach ($client_ids as $index => $client_id) {
            $values[] = "(:campaign_id_$index, :client_id_$index)";
            $params[":campaign_id_$index"] = $this->id;
            $params[":client_id_$index"] = $client_id;
        }
        
        $query = "INSERT IGNORE INTO campaign_recipients (campaign_id, client_id) VALUES " . implode(', ', $values);
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        if ($stmt->execute()) {
            $this->updateRecipientCount();
            return true;
        }
        
        return false;
    }

    /**
     * Atualizar contador de destinatários
     */
    private function updateRecipientCount() {
        $query = "UPDATE " . $this->table_name . " 
                  SET total_recipients = (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = :id)
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    /**
     * Executar campanha
     */
    public function execute() {
        // Buscar destinatários
        $query = "SELECT cr.client_id, c.name, c.phone, c.subscription_amount, c.due_date 
                  FROM campaign_recipients cr
                  LEFT JOIN clients c ON cr.client_id = c.id
                  WHERE cr.campaign_id = :campaign_id AND c.status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':campaign_id', $this->id);
        $stmt->execute();
        $recipients = $stmt->fetchAll();
        
        if (empty($recipients)) {
            return false;
        }
        
        // Buscar template
        $template_query = "SELECT message FROM message_templates WHERE id = :template_id";
        $template_stmt = $this->conn->prepare($template_query);
        $template_stmt->bindParam(':template_id', $this->template_id);
        $template_stmt->execute();
        $template = $template_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            return false;
        }
        
        // Criar registros de envio
        foreach ($recipients as $recipient) {
            $message = $this->personalizeMessage($template['message'], $recipient);
            
            $send_query = "INSERT INTO campaign_sends 
                          SET campaign_id=:campaign_id, client_id=:client_id, 
                              phone=:phone, message=:message, status='pending'";
            
            $send_stmt = $this->conn->prepare($send_query);
            $send_stmt->bindParam(':campaign_id', $this->id);
            $send_stmt->bindParam(':client_id', $recipient['client_id']);
            $send_stmt->bindParam(':phone', $recipient['phone']);
            $send_stmt->bindParam(':message', $message);
            $send_stmt->execute();
        }
        
        // Atualizar status da campanha
        $this->status = 'running';
        $update_query = "UPDATE " . $this->table_name . " SET status = 'running' WHERE id = :id";
        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->bindParam(':id', $this->id);
        $update_stmt->execute();
        
        return true;
    }

    /**
     * Personalizar mensagem com dados do cliente
     */
    private function personalizeMessage($message, $client_data) {
        $message = str_replace('{nome}', $client_data['name'], $message);
        $message = str_replace('{valor}', 'R$ ' . number_format($client_data['subscription_amount'], 2, ',', '.'), $message);
        $message = str_replace('{vencimento}', date('d/m/Y', strtotime($client_data['due_date'])), $message);
        
        return $message;
    }

    /**
     * Obter estatísticas da campanha
     */
    public function getStatistics($user_id = null) {
        $where_clause = $user_id ? "WHERE user_id = :user_id" : "";
        
        $query = "SELECT 
                    COUNT(*) as total_campaigns,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_campaigns,
                    SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running_campaigns,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_campaigns,
                    SUM(total_recipients) as total_recipients,
                    SUM(sent_count) as total_sent,
                    SUM(delivered_count) as total_delivered,
                    SUM(failed_count) as total_failed
                  FROM " . $this->table_name . " $where_clause";
        
        $stmt = $this->conn->prepare($query);
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validar dados da campanha
     */
    public function validate() {
        $errors = [];
        
        if (empty(trim($this->name))) {
            $errors[] = "Nome da campanha é obrigatório";
        }
        
        if (empty($this->template_id)) {
            $errors[] = "Template é obrigatório";
        }
        
        if (!empty($this->scheduled_for)) {
            $scheduled_time = strtotime($this->scheduled_for);
            if ($scheduled_time <= time()) {
                $errors[] = "Data de agendamento deve ser no futuro";
            }
        }
        
        return $errors;
    }
}
?>