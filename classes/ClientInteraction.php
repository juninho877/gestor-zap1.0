<?php
require_once __DIR__ . '/../config/database.php';

class ClientInteraction {
    private $conn;
    private $table_name = "client_interactions";

    public $id;
    public $user_id;
    public $client_id;
    public $type;
    public $title;
    public $description;
    public $metadata;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar uma nova interação
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, client_id=:client_id, type=:type, 
                      title=:title, description=:description, metadata=:metadata";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":metadata", $this->metadata);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar interações de um cliente
     */
    public function readByClient($client_id, $user_id, $limit = 50, $offset = 0) {
        $query = "SELECT ci.*, u.name as user_name 
                  FROM " . $this->table_name . " ci
                  LEFT JOIN users u ON ci.user_id = u.id
                  WHERE ci.client_id = :client_id AND ci.user_id = :user_id
                  ORDER BY ci.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar interações por tipo
     */
    public function readByType($client_id, $user_id, $type, $limit = 20) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE client_id = :client_id AND user_id = :user_id AND type = :type
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Registrar interação de mensagem
     */
    public static function logMessage($db, $user_id, $client_id, $message_text, $status) {
        $interaction = new ClientInteraction($db);
        $interaction->user_id = $user_id;
        $interaction->client_id = $client_id;
        $interaction->type = 'message';
        $interaction->title = 'Mensagem enviada';
        $interaction->description = substr($message_text, 0, 200) . (strlen($message_text) > 200 ? '...' : '');
        $interaction->metadata = json_encode(['status' => $status, 'full_message' => $message_text]);
        
        return $interaction->create();
    }

    /**
     * Registrar interação de pagamento
     */
    public static function logPayment($db, $user_id, $client_id, $amount, $payment_date) {
        $interaction = new ClientInteraction($db);
        $interaction->user_id = $user_id;
        $interaction->client_id = $client_id;
        $interaction->type = 'payment';
        $interaction->title = 'Pagamento recebido';
        $interaction->description = "Pagamento de R$ " . number_format($amount, 2, ',', '.') . " recebido";
        $interaction->metadata = json_encode(['amount' => $amount, 'payment_date' => $payment_date]);
        
        return $interaction->create();
    }

    /**
     * Registrar alteração de status
     */
    public static function logStatusChange($db, $user_id, $client_id, $old_status, $new_status) {
        $interaction = new ClientInteraction($db);
        $interaction->user_id = $user_id;
        $interaction->client_id = $client_id;
        $interaction->type = 'status_change';
        $interaction->title = 'Status alterado';
        $interaction->description = "Status alterado de '{$old_status}' para '{$new_status}'";
        $interaction->metadata = json_encode(['old_status' => $old_status, 'new_status' => $new_status]);
        
        return $interaction->create();
    }

    /**
     * Registrar nota manual
     */
    public static function logNote($db, $user_id, $client_id, $title, $note) {
        $interaction = new ClientInteraction($db);
        $interaction->user_id = $user_id;
        $interaction->client_id = $client_id;
        $interaction->type = 'note';
        $interaction->title = $title;
        $interaction->description = $note;
        $interaction->metadata = json_encode(['note_type' => 'manual']);
        
        return $interaction->create();
    }

    /**
     * Obter estatísticas de interações
     */
    public function getStatistics($client_id, $user_id) {
        $query = "SELECT 
                    COUNT(*) as total_interactions,
                    SUM(CASE WHEN type = 'message' THEN 1 ELSE 0 END) as message_count,
                    SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) as payment_count,
                    SUM(CASE WHEN type = 'note' THEN 1 ELSE 0 END) as note_count,
                    SUM(CASE WHEN type = 'status_change' THEN 1 ELSE 0 END) as status_change_count,
                    MAX(created_at) as last_interaction
                  FROM " . $this->table_name . " 
                  WHERE client_id = :client_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>