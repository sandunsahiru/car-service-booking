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
 * Mark a recommendation as completed
 * 
 * @param int $recommendationId The recommendation ID to mark as completed
 * @return bool True if successful
 * @throws Exception If database query fails
 */
public function markAsCompleted(int $recommendationId): bool {
    try {
        $sql = "UPDATE service_recommendations 
               SET status = 'completed', 
                   completed_date = CURRENT_DATE() 
               WHERE id = :recommendation_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':recommendation_id', $recommendationId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error marking recommendation as completed: " . $e->getMessage());
        throw new Exception('Failed to update recommendation status');
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

    /**
     * Get service recommendations for a specific vehicle
     * 
     * @param int $carId The car ID
     * @return array The recommendations for the car
     */
    public function getRecommendationsForCar(int $carId): array {
        try {
            $sql = "SELECT sr.*, s.name as service_name, s.price, s.duration_minutes
                   FROM service_recommendations sr 
                   JOIN services s ON sr.service_id = s.id 
                   WHERE sr.car_id = :car_id 
                   AND sr.status = 'pending' 
                   ORDER BY sr.priority DESC, sr.recommended_date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':car_id' => $carId]);
            $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure all numeric values are properly typed
            foreach ($recommendations as &$rec) {
                // Convert price to float
                if (isset($rec['price'])) {
                    $rec['price'] = (float)$rec['price'];
                }
                
                // Convert recommendation to string
                if (isset($rec['recommendation'])) {
                    $rec['recommendation'] = htmlspecialchars((string)$rec['recommendation']);
                } else {
                    $rec['recommendation'] = '';
                }
                
                // Convert reason to string
                if (isset($rec['reason'])) {
                    $rec['reason'] = htmlspecialchars((string)$rec['reason']);
                } else {
                    $rec['reason'] = '';
                }
            }
            
            return $recommendations;
        } catch (PDOException $e) {
            error_log("Error getting recommendations for car: " . $e->getMessage());
            throw new Exception('Failed to get car service recommendations');
        }
    }
}