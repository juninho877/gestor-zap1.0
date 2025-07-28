<?php
require_once __DIR__ . '/../config/database.php';

class Affiliate {
    private $conn;
    private $table_name = "affiliates";

    public $id;
    public $user_id;
    public $affiliate_code;
    public $commission_rate;
    public $level;
    public $total_clicks;
    public $total_conversions;
    public $total_commission;
    public $status;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar um novo afiliado
     */
    public function create() {
        // Gerar código único se não fornecido
        if (empty($this->affiliate_code)) {
            $this->affiliate_code = $this->generateUniqueCode();
        }
        
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, affiliate_code=:affiliate_code, 
                      commission_rate=:commission_rate, level=:level, status=:status";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":affiliate_code", $this->affiliate_code);
        $stmt->bindParam(":commission_rate", $this->commission_rate);
        $stmt->bindParam(":level", $this->level);
        $stmt->bindParam(":status", $this->status);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar afiliado por código
     */
    public function readByCode($code) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE affiliate_code = :code LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->affiliate_code = $row['affiliate_code'];
            $this->commission_rate = $row['commission_rate'];
            $this->level = $row['level'];
            $this->total_clicks = $row['total_clicks'];
            $this->total_conversions = $row['total_conversions'];
            $this->total_commission = $row['total_commission'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    /**
     * Buscar afiliado por usuário
     */
    public function readByUser($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->affiliate_code = $row['affiliate_code'];
            $this->commission_rate = $row['commission_rate'];
            $this->level = $row['level'];
            $this->total_clicks = $row['total_clicks'];
            $this->total_conversions = $row['total_conversions'];
            $this->total_commission = $row['total_commission'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    /**
     * Registrar clique
     */
    public function recordClick($ip_address, $user_agent, $referrer, $landing_page) {
        // Verificar se já existe um clique recente do mesmo IP (evitar spam)
        $check_query = "SELECT id FROM affiliate_clicks 
                       WHERE affiliate_id = :affiliate_id 
                       AND ip_address = :ip_address 
                       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':affiliate_id', $this->id);
        $check_stmt->bindParam(':ip_address', $ip_address);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            return false; // Clique duplicado
        }
        
        // Registrar o clique
        $query = "INSERT INTO affiliate_clicks 
                  SET affiliate_id=:affiliate_id, ip_address=:ip_address, 
                      user_agent=:user_agent, referrer=:referrer, landing_page=:landing_page";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':affiliate_id', $this->id);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        $stmt->bindParam(':referrer', $referrer);
        $stmt->bindParam(':landing_page', $landing_page);
        
        if ($stmt->execute()) {
            // Atualizar contador de cliques
            $this->updateClickCount();
            return true;
        }
        
        return false;
    }

    /**
     * Registrar conversão
     */
    public function recordConversion($user_id, $plan_id, $commission_amount, $payment_id = null) {
        $query = "INSERT INTO affiliate_conversions 
                  SET affiliate_id=:affiliate_id, user_id=:user_id, plan_id=:plan_id, 
                      commission_amount=:commission_amount, payment_id=:payment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':affiliate_id', $this->id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':plan_id', $plan_id);
        $stmt->bindParam(':commission_amount', $commission_amount);
        $stmt->bindParam(':payment_id', $payment_id);
        
        if ($stmt->execute()) {
            // Atualizar contadores
            $this->updateConversionStats($commission_amount);
            $this->updateLevel();
            return true;
        }
        
        return false;
    }

    /**
     * Atualizar contador de cliques
     */
    private function updateClickCount() {
        $query = "UPDATE " . $this->table_name . " 
                  SET total_clicks = (SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :id)
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    /**
     * Atualizar estatísticas de conversão
     */
    private function updateConversionStats($commission_amount) {
        $query = "UPDATE " . $this->table_name . " 
                  SET total_conversions = (SELECT COUNT(*) FROM affiliate_conversions WHERE affiliate_id = :id),
                      total_commission = (SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_conversions WHERE affiliate_id = :id)
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    /**
     * Atualizar nível do afiliado baseado na performance
     */
    private function updateLevel() {
        $new_level = 'bronze';
        
        if ($this->total_conversions >= 50) {
            $new_level = 'platinum';
        } elseif ($this->total_conversions >= 20) {
            $new_level = 'gold';
        } elseif ($this->total_conversions >= 5) {
            $new_level = 'silver';
        }
        
        if ($new_level !== $this->level) {
            $query = "UPDATE " . $this->table_name . " SET level = :level WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':level', $new_level);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            $this->level = $new_level;
        }
    }

    /**
     * Gerar código único
     */
    private function generateUniqueCode() {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
            $query = "SELECT id FROM " . $this->table_name . " WHERE affiliate_code = :code";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':code', $code);
            $stmt->execute();
        } while ($stmt->rowCount() > 0);
        
        return $code;
    }

    /**
     * Obter estatísticas do afiliado
     */
    public function getStatistics() {
        $query = "SELECT 
                    a.*,
                    (SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = a.id) as total_clicks,
                    (SELECT COUNT(*) FROM affiliate_conversions WHERE affiliate_id = a.id) as total_conversions,
                    (SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_conversions WHERE affiliate_id = a.id) as total_commission,
                    (SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = a.id AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as clicks_30_days,
                    (SELECT COUNT(*) FROM affiliate_conversions WHERE affiliate_id = a.id AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as conversions_30_days
                  FROM " . $this->table_name . " a
                  WHERE a.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar todos os afiliados (admin)
     */
    public function readAll($limit = 50, $offset = 0) {
        $query = "SELECT a.*, u.name as user_name, u.email as user_email 
                  FROM " . $this->table_name . " a
                  LEFT JOIN users u ON a.user_id = u.id
                  ORDER BY a.total_commission DESC, a.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
}
?>