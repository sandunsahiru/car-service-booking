<?php
declare(strict_types=1);

class ServiceHistory {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get recent service history for a user's cars
     */
    public function getRecentHistory(int $userId, int $limit = 5): array {
        try {
            $sql = "SELECT sh.*, c.make, c.model, c.reg_number, s.name as service_name 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   JOIN services s ON sh.service_id = s.id 
                   WHERE c.user_id = :user_id 
                   ORDER BY sh.service_date DESC 
                   LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting service history: " . $e->getMessage());
            throw new Exception('Failed to get service history');
        }
    }

    /**
     * Get total cost of services for a user
     */
    public function getTotalServiceCost(int $userId): float {
        try {
            $sql = "SELECT SUM(sh.cost) 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   WHERE c.user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
            return (float)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting total service cost: " . $e->getMessage());
            throw new Exception('Failed to get total service cost');
        }
    }
}