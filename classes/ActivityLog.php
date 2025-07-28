<?php
require_once __DIR__ . '/../config/database.php';

class ActivityLog {
    private $conn;
    private $table_name = "activity_logs";

    public $id;
    public $user_id;
    public $action;
    public $entity_type;
    public $entity_id;
    public $description;
    public $ip_address;
    public $user_agent;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Registrar uma atividade
     */
    public function log($user_id, $action, $entity_type, $entity_id, $description) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, action=:action, entity_type=:entity_type, 
                      entity_id=:entity_id, description=:description, 
                      ip_address=:ip_address, user_agent=:user_agent";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":entity_type", $entity_type);
        $stmt->bindParam(":entity_id", $entity_id);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $this->getClientIP());
        $stmt->bindParam(":user_agent", $this->getUserAgent());
        
        return $stmt->execute();
    }

    /**
     * Buscar logs de atividade
     */
    public function readByUser($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT al.*, u.name as user_name 
                  FROM " . $this->table_name . " al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE al.user_id = :user_id 
                  ORDER BY al.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar todos os logs (admin)
     */
    public function readAll($limit = 100, $offset = 0, $filters = []) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "al.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where_conditions[] = "al.action = :action";
            $params[':action'] = $filters['action'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where_conditions[] = "al.entity_type = :entity_type";
            $params[':entity_type'] = $filters['entity_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(al.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(al.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $query = "SELECT al.*, u.name as user_name 
                  FROM " . $this->table_name . " al
                  LEFT JOIN users u ON al.user_id = u.id
                  $where_clause
                  ORDER BY al.created_at DESC 
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
     * Obter IP do cliente
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Obter User Agent
     */
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Métodos estáticos para facilitar o uso
     */
    public static function logActivity($db, $user_id, $action, $entity_type, $entity_id, $description) {
        $log = new ActivityLog($db);
        return $log->log($user_id, $action, $entity_type, $entity_id, $description);
    }

    public static function logLogin($db, $user_id, $user_name) {
        return self::logActivity($db, $user_id, 'login', 'user', $user_id, "Usuário {$user_name} fez login no sistema");
    }

    public static function logLogout($db, $user_id, $user_name) {
        return self::logActivity($db, $user_id, 'logout', 'user', $user_id, "Usuário {$user_name} fez logout do sistema");
    }

    public static function logClientCreate($db, $user_id, $client_id, $client_name) {
        return self::logActivity($db, $user_id, 'create', 'client', $client_id, "Cliente '{$client_name}' foi criado");
    }

    public static function logClientUpdate($db, $user_id, $client_id, $client_name) {
        return self::logActivity($db, $user_id, 'update', 'client', $client_id, "Cliente '{$client_name}' foi atualizado");
    }

    public static function logClientDelete($db, $user_id, $client_id, $client_name) {
        return self::logActivity($db, $user_id, 'delete', 'client', $client_id, "Cliente '{$client_name}' foi removido");
    }

    public static function logMessageSent($db, $user_id, $client_id, $client_name) {
        return self::logActivity($db, $user_id, 'message_sent', 'client', $client_id, "Mensagem enviada para '{$client_name}'");
    }

    public static function logPaymentReceived($db, $user_id, $client_id, $client_name, $amount) {
        return self::logActivity($db, $user_id, 'payment_received', 'client', $client_id, "Pagamento de R$ {$amount} recebido de '{$client_name}'");
    }
}
?>