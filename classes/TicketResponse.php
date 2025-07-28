<?php
require_once __DIR__ . '/../config/database.php';

class TicketResponse {
    private $conn;
    private $table_name = "ticket_responses";

    public $id;
    public $ticket_id;
    public $user_id;
    public $message;
    public $is_internal;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar uma nova resposta
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET ticket_id=:ticket_id, user_id=:user_id, message=:message, is_internal=:is_internal";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":ticket_id", $this->ticket_id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":is_internal", $this->is_internal, PDO::PARAM_BOOL);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar respostas de um ticket
     */
    public function readByTicket($ticket_id, $include_internal = false) {
        $where_clause = "WHERE tr.ticket_id = :ticket_id";
        if (!$include_internal) {
            $where_clause .= " AND tr.is_internal = FALSE";
        }
        
        $query = "SELECT tr.*, u.name as user_name, u.role as user_role 
                  FROM " . $this->table_name . " tr
                  LEFT JOIN users u ON tr.user_id = u.id
                  $where_clause
                  ORDER BY tr.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ticket_id', $ticket_id);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Contar respostas de um ticket
     */
    public function countByTicket($ticket_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE ticket_id = :ticket_id AND is_internal = FALSE";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ticket_id', $ticket_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
}
?>