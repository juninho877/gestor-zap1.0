<?php
require_once __DIR__ . '/../config/database.php';

class Ticket {
    private $conn;
    private $table_name = "tickets";

    public $id;
    public $user_id;
    public $title;
    public $description;
    public $status;
    public $priority;
    public $category;
    public $assigned_to;
    public $resolved_at;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar um novo ticket
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, title=:title, description=:description, 
                      status=:status, priority=:priority, category=:category";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":category", $this->category);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar tickets do usuário
     */
    public function readByUser($user_id, $limit = 20, $offset = 0) {
        $query = "SELECT t.*, u.name as assigned_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u ON t.assigned_to = u.id
                  WHERE t.user_id = :user_id 
                  ORDER BY t.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar todos os tickets (admin)
     */
    public function readAll($limit = 50, $offset = 0, $filters = []) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "t.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $where_conditions[] = "t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        
        if (!empty($filters['assigned_to'])) {
            $where_conditions[] = "t.assigned_to = :assigned_to";
            $params[':assigned_to'] = $filters['assigned_to'];
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $query = "SELECT t.*, u1.name as user_name, u2.name as assigned_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u1 ON t.user_id = u1.id
                  LEFT JOIN users u2 ON t.assigned_to = u2.id
                  $where_clause
                  ORDER BY t.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar um ticket específico
     */
    public function readOne() {
        $query = "SELECT t.*, u1.name as user_name, u1.email as user_email, u2.name as assigned_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u1 ON t.user_id = u1.id
                  LEFT JOIN users u2 ON t.assigned_to = u2.id
                  WHERE t.id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->user_id = $row['user_id'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->status = $row['status'];
            $this->priority = $row['priority'];
            $this->category = $row['category'];
            $this->assigned_to = $row['assigned_to'];
            $this->resolved_at = $row['resolved_at'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return $row;
        }
        return false;
    }

    /**
     * Atualizar ticket
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET title=:title, description=:description, status=:status, 
                      priority=:priority, category=:category, assigned_to=:assigned_to";
        
        if ($this->status === 'resolved' && empty($this->resolved_at)) {
            $query .= ", resolved_at=NOW()";
        }
        
        $query .= " WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":assigned_to", $this->assigned_to);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }

    /**
     * Obter estatísticas de tickets
     */
    public function getStatistics($user_id = null) {
        $where_clause = $user_id ? "WHERE user_id = :user_id" : "";
        
        $query = "SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tickets,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_tickets
                  FROM " . $this->table_name . " $where_clause";
        
        $stmt = $this->conn->prepare($query);
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validar dados do ticket
     */
    public function validate() {
        $errors = [];
        
        if (empty(trim($this->title))) {
            $errors[] = "Título é obrigatório";
        }
        
        if (empty(trim($this->description))) {
            $errors[] = "Descrição é obrigatória";
        }
        
        if (!in_array($this->status, ['open', 'in_progress', 'resolved', 'closed'])) {
            $errors[] = "Status inválido";
        }
        
        if (!in_array($this->priority, ['low', 'medium', 'high', 'urgent'])) {
            $errors[] = "Prioridade inválida";
        }
        
        return $errors;
    }
}
?>