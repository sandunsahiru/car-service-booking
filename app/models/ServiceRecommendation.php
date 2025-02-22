<?php
declare(strict_types=1);

class ServiceRecommendation {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get pending service recommendations for a user's cars
     */
    public function getPendingRecommendations(int $userId): array {
        try {
            $sql = "SELECT sr.*, c.make, c.model, c.reg_number, s.name as service_name 
                   FROM service_recommendations sr 
                   JOIN cars c ON sr.car_id = c.id 
                   JOIN services s ON sr.service_id = s.id 
                   WHERE c.user_id = :user_id 
                   AND sr.status = 'pending' 
                   ORDER BY sr.priority DESC, sr.recommended_date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting recommendations: " . $e->getMessage());
            throw new Exception('Failed to get service recommendations');
        }
    }

    /**
     * Get high priority recommendations count
     */
    public function getHighPriorityCount(int $userId): int {
        try {
            $sql = "SELECT COUNT(*) 
                   FROM service_recommendations sr 
                   JOIN cars c ON sr.car_id = c.id 
                   WHERE c.user_id = :user_id 
                   AND sr.priority = 'high' 
                   AND sr.status = 'pending'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting high priority count: " . $e->getMessage());
            throw new Exception('Failed to get high priority recommendations count');
        }
    }
}