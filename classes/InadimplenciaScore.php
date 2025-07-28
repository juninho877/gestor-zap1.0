<?php
require_once __DIR__ . '/../config/database.php';

class InadimplenciaScore {
    private $conn;
    
    // Pesos para o cálculo do score
    const PESO_ATRASOS = 0.4;
    const PESO_TEMPO_MEDIO = 0.3;
    const PESO_FREQUENCIA = 0.3;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Calcular score de inadimplência para um cliente
     */
    public function calculateScore($client_id) {
        // Buscar histórico de pagamentos e atrasos
        $query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN due_date < last_payment_date THEN 1 ELSE 0 END) as late_payments,
                    AVG(CASE 
                        WHEN due_date < last_payment_date 
                        THEN DATEDIFF(last_payment_date, due_date) 
                        ELSE 0 
                    END) as avg_delay_days,
                    DATEDIFF(NOW(), MIN(created_at)) as client_age_days
                  FROM (
                    SELECT due_date, last_payment_date, created_at
                    FROM clients 
                    WHERE id = :client_id
                    UNION ALL
                    SELECT DATE_SUB(due_date, INTERVAL 1 MONTH) as due_date, 
                           last_payment_date, created_at
                    FROM clients 
                    WHERE id = :client_id AND last_payment_date IS NOT NULL
                  ) payment_history";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data || $data['total_payments'] == 0) {
            return 0; // Cliente novo ou sem histórico
        }
        
        // Calcular componentes do score
        $atraso_score = ($data['late_payments'] / $data['total_payments']) * 100;
        $tempo_score = min($data['avg_delay_days'] * 10, 100); // Max 100 pontos
        $frequencia_score = ($data['late_payments'] / max($data['client_age_days'] / 30, 1)) * 100;
        
        // Score final ponderado
        $final_score = (
            $atraso_score * self::PESO_ATRASOS +
            $tempo_score * self::PESO_TEMPO_MEDIO +
            $frequencia_score * self::PESO_FREQUENCIA
        );
        
        return min(round($final_score), 100); // Máximo 100
    }

    /**
     * Atualizar score de um cliente
     */
    public function updateClientScore($client_id) {
        $score = $this->calculateScore($client_id);
        
        $query = "UPDATE clients SET inadimplencia_score = :score WHERE id = :client_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':score', $score);
        $stmt->bindParam(':client_id', $client_id);
        
        return $stmt->execute();
    }

    /**
     * Atualizar scores de todos os clientes de um usuário
     */
    public function updateAllScores($user_id) {
        $query = "SELECT id FROM clients WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $updated = 0;
        foreach ($clients as $client_id) {
            if ($this->updateClientScore($client_id)) {
                $updated++;
            }
        }
        
        return $updated;
    }

    /**
     * Obter classificação do score
     */
    public static function getScoreClass($score) {
        if ($score <= 30) {
            return ['class' => 'success', 'label' => 'Baixo Risco', 'color' => 'green'];
        } elseif ($score <= 70) {
            return ['class' => 'warning', 'label' => 'Médio Risco', 'color' => 'yellow'];
        } else {
            return ['class' => 'danger', 'label' => 'Alto Risco', 'color' => 'red'];
        }
    }

    /**
     * Obter clientes por faixa de score
     */
    public function getClientsByScoreRange($user_id, $min_score = 0, $max_score = 100) {
        $query = "SELECT id, name, phone, inadimplencia_score, due_date, subscription_amount
                  FROM clients 
                  WHERE user_id = :user_id 
                  AND inadimplencia_score BETWEEN :min_score AND :max_score
                  ORDER BY inadimplencia_score DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':min_score', $min_score);
        $stmt->bindParam(':max_score', $max_score);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Obter estatísticas de inadimplência
     */
    public function getStatistics($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_clients,
                    AVG(inadimplencia_score) as avg_score,
                    SUM(CASE WHEN inadimplencia_score <= 30 THEN 1 ELSE 0 END) as low_risk,
                    SUM(CASE WHEN inadimplencia_score BETWEEN 31 AND 70 THEN 1 ELSE 0 END) as medium_risk,
                    SUM(CASE WHEN inadimplencia_score > 70 THEN 1 ELSE 0 END) as high_risk
                  FROM clients 
                  WHERE user_id = :user_id AND status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>