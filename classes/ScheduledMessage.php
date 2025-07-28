<?php
require_once __DIR__ . '/../config/database.php';

class ScheduledMessage {
    private $conn;
    private $table_name = "scheduled_messages";

    public $id;
    public $user_id;
    public $client_id;
    public $template_id;
    public $message;
    public $phone;
    public $scheduled_for;
    public $status;
    public $sent_at;
    public $error_message;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar uma nova mensagem agendada
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, client_id=:client_id, template_id=:template_id, 
                      message=:message, phone=:phone, scheduled_for=:scheduled_for, status=:status";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":template_id", $this->template_id);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":scheduled_for", $this->scheduled_for);
        $stmt->bindParam(":status", $this->status);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar mensagens agendadas do usuário
     */
    public function readByUser($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT sm.*, c.name as client_name, mt.name as template_name 
                  FROM " . $this->table_name . " sm
                  LEFT JOIN clients c ON sm.client_id = c.id
                  LEFT JOIN message_templates mt ON sm.template_id = mt.id
                  WHERE sm.user_id = :user_id 
                  ORDER BY sm.scheduled_for DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar mensagens prontas para envio
     */
    public function getPendingMessages() {
        $query = "SELECT sm.*, u.whatsapp_instance, u.whatsapp_connected 
                  FROM " . $this->table_name . " sm
                  LEFT JOIN users u ON sm.user_id = u.id
                  WHERE sm.status = 'pending' 
                  AND sm.scheduled_for <= NOW()
                  AND u.whatsapp_connected = 1
                  ORDER BY sm.scheduled_for ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Atualizar status da mensagem
     */
    public function updateStatus($status, $error_message = null) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status";
        
        if ($status === 'sent') {
            $query .= ", sent_at = NOW()";
        }
        
        if ($error_message) {
            $query .= ", error_message = :error_message";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $this->id);
        
        if ($error_message) {
            $stmt->bindParam(':error_message', $error_message);
        }
        
        return $stmt->execute();
    }

    /**
     * Cancelar mensagem agendada
     */
    public function cancel() {
        return $this->updateStatus('cancelled');
    }

    /**
     * Obter estatísticas
     */
    public function getStatistics($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_scheduled,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
                  FROM " . $this->table_name . " 
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validar dados
     */
    public function validate() {
        $errors = [];
        
        if (empty($this->message)) {
            $errors[] = "Mensagem é obrigatória";
        }
        
        if (empty($this->phone)) {
            $errors[] = "Telefone é obrigatório";
        }
        
        if (empty($this->scheduled_for)) {
            $errors[] = "Data de agendamento é obrigatória";
        } else {
            $scheduled_time = strtotime($this->scheduled_for);
            if ($scheduled_time <= time()) {
                $errors[] = "Data de agendamento deve ser no futuro";
            }
        }
        
        return $errors;
    }
}
?>