<?php
require_once __DIR__ . '/../config/database.php';

class Notification {
    private $conn;
    private $table_name = "notifications";

    public $id;
    public $user_id;
    public $type;
    public $title;
    public $message;
    public $read_at;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar uma nova notificação
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, type=:type, title=:title, message=:message";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":message", $this->message);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar notificações de um usuário
     */
    public function readByUser($user_id, $limit = 20, $offset = 0, $unread_only = false) {
        $where_clause = "WHERE user_id = :user_id";
        if ($unread_only) {
            $where_clause .= " AND read_at IS NULL";
        }
        
        $query = "SELECT * FROM " . $this->table_name . " 
                  $where_clause 
                  ORDER BY created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Contar notificações não lidas
     */
    public function countUnread($user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND read_at IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * Marcar notificação como lida
     */
    public function markAsRead($notification_id, $user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET read_at = NOW() 
                  WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $notification_id);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    /**
     * Marcar todas as notificações como lidas
     */
    public function markAllAsRead($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET read_at = NOW() 
                  WHERE user_id = :user_id AND read_at IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    /**
     * Criar notificação para múltiplos usuários
     */
    public static function createForUsers($db, $user_ids, $type, $title, $message) {
        $query = "INSERT INTO notifications (user_id, type, title, message) VALUES ";
        $values = [];
        $params = [];
        
        foreach ($user_ids as $index => $user_id) {
            $values[] = "(:user_id_$index, :type_$index, :title_$index, :message_$index)";
            $params[":user_id_$index"] = $user_id;
            $params[":type_$index"] = $type;
            $params[":title_$index"] = $title;
            $params[":message_$index"] = $message;
        }
        
        $query .= implode(', ', $values);
        $stmt = $db->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Criar notificação para todos os administradores
     */
    public static function createForAdmins($db, $type, $title, $message) {
        $query = "SELECT id FROM users WHERE role = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $admin_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($admin_ids)) {
            return self::createForUsers($db, $admin_ids, $type, $title, $message);
        }
        
        return false;
    }

    /**
     * Limpar notificações antigas
     */
    public static function cleanOldNotifications($db, $days = 30) {
        $query = "DELETE FROM notifications 
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':days', $days);
        return $stmt->execute();
    }
}
?>