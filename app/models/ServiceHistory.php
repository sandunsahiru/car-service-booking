<?php
declare(strict_types=1);

class ServiceHistory {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get recent service history for a user's cars
     * 
     * @param int $userId User ID to get history for
     * @param int $limit Maximum number of records to return
     * @return array Array of service history entries
     * @throws Exception If database query fails
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
     * 
     * @param int $userId User ID to calculate total cost for
     * @return float Total cost of all services
     * @throws Exception If database query fails
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

    /**
     * Get filtered service history based on provided parameters
     * 
     * @param int $userId The user ID
     * @param int|null $carId The car ID to filter by (optional)
     * @param string|null $startDate Start date in Y-m-d format (optional)
     * @param string|null $endDate End date in Y-m-d format (optional)
     * @param int|null $serviceTypeId The service type ID to filter by (optional)
     * @return array Array of service history entries matching the filters
     * @throws Exception If database query fails
     */
    public function getFilteredHistory(int $userId, ?int $carId = null, ?string $startDate = null, 
                                      ?string $endDate = null, ?int $serviceTypeId = null): array {
        try {
            $sql = "SELECT sh.*, c.make, c.model, c.reg_number, s.name as service_name 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   JOIN services s ON sh.service_id = s.id 
                   WHERE c.user_id = :user_id";
            
            $params = [':user_id' => $userId];
            
            // Add optional filters
            if ($carId !== null) {
                $sql .= " AND sh.car_id = :car_id";
                $params[':car_id'] = $carId;
            }
            
            if ($startDate !== null) {
                $sql .= " AND sh.service_date >= :start_date";
                $params[':start_date'] = $startDate;
            }
            
            if ($endDate !== null) {
                $sql .= " AND sh.service_date <= :end_date";
                $params[':end_date'] = $endDate;
            }
            
            if ($serviceTypeId !== null) {
                $sql .= " AND sh.service_id = :service_type_id";
                $params[':service_type_id'] = $serviceTypeId;
            }
            
            // Order by date descending
            $sql .= " ORDER BY sh.service_date DESC";
            
            $stmt = $this->db->prepare($sql);
            
            // Bind all parameters
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }
            
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting filtered service history: " . $e->getMessage());
            throw new Exception('Failed to get service history with filters');
        }
    }

    /**
     * Get the count of service entries for a specific service type
     * 
     * @param int $userId The user ID
     * @param int $serviceTypeId The service type ID
     * @return int Count of this service type
     * @throws Exception If database query fails
     */
    public function getServiceTypeCount(int $userId, int $serviceTypeId): int {
        try {
            $sql = "SELECT COUNT(*) 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   WHERE c.user_id = :user_id 
                   AND sh.service_id = :service_type_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':service_type_id', $serviceTypeId, PDO::PARAM_INT);
            $stmt->execute();
            
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting service type count: " . $e->getMessage());
            throw new Exception('Failed to get service type count');
        }
    }

    /**
     * Get the most common service type for a user
     * 
     * @param int $userId The user ID
     * @return array|null Service type information or null if no history
     * @throws Exception If database query fails
     */
    public function getMostCommonServiceType(int $userId): ?array {
        try {
            $sql = "SELECT s.id, s.name, COUNT(*) as service_count 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   JOIN services s ON sh.service_id = s.id 
                   WHERE c.user_id = :user_id 
                   GROUP BY s.id, s.name 
                   ORDER BY service_count DESC 
                   LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error getting most common service type: " . $e->getMessage());
            throw new Exception('Failed to get most common service type');
        }
    }

    /**
     * Get the highest cost service for a user
     * 
     * @param int $userId The user ID
     * @return array|null Service info or null if no history
     * @throws Exception If database query fails
     */
    public function getHighestCostService(int $userId): ?array {
        try {
            $sql = "SELECT sh.*, s.name as service_name, c.make, c.model, c.reg_number 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   JOIN services s ON sh.service_id = s.id 
                   WHERE c.user_id = :user_id 
                   ORDER BY sh.cost DESC 
                   LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error getting highest cost service: " . $e->getMessage());
            throw new Exception('Failed to get highest cost service');
        }
    }

    /**
     * Get a specific service history entry by ID
     * 
     * @param int $entryId The service history entry ID
     * @param int $userId The user ID (for security validation)
     * @return array|null Service history entry or null if not found
     * @throws Exception If database query fails
     */
    public function getById(int $entryId, int $userId): ?array {
        try {
            $sql = "SELECT sh.*, c.make, c.model, c.year, c.reg_number, s.name as service_name, s.description as service_description 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   JOIN services s ON sh.service_id = s.id 
                   WHERE sh.id = :entry_id AND c.user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':entry_id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error getting service history entry: " . $e->getMessage());
            throw new Exception('Failed to get service history entry');
        }
    }

    /**
     * Add a new service history entry
     * 
     * @param array $data Service history data
     * @return int The ID of the newly created entry
     * @throws Exception If database query fails
     */
    public function addEntry(array $data): int {
        try {
            $sql = "INSERT INTO service_history (car_id, service_id, service_date, mileage, cost, description, technician, invoice_number) 
                   VALUES (:car_id, :service_id, :service_date, :mileage, :cost, :description, :technician, :invoice_number)";

            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':car_id', $data['car_id'], PDO::PARAM_INT);
            $stmt->bindValue(':service_id', $data['service_id'], PDO::PARAM_INT);
            $stmt->bindValue(':service_date', $data['service_date'], PDO::PARAM_STR);
            $stmt->bindValue(':mileage', $data['mileage'], PDO::PARAM_INT);
            $stmt->bindValue(':cost', $data['cost'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':technician', $data['technician'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':invoice_number', $data['invoice_number'] ?? null, PDO::PARAM_STR);
            
            $stmt->execute();
            
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error adding service history entry: " . $e->getMessage());
            throw new Exception('Failed to add service history entry');
        }
    }

    /**
     * Update an existing service history entry
     * 
     * @param int $entryId The ID of the entry to update
     * @param array $data The updated data
     * @param int $userId The user ID (for security validation)
     * @return bool True if successful
     * @throws Exception If database query fails or validation fails
     */
    public function updateEntry(int $entryId, array $data, int $userId): bool {
        // First verify the user owns this entry
        try {
            $sql = "SELECT sh.id 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   WHERE sh.id = :entry_id AND c.user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':entry_id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                throw new Exception('Unauthorized access to service history entry');
            }
            
            // Now perform the update
            $sql = "UPDATE service_history 
                   SET service_id = :service_id, 
                       service_date = :service_date, 
                       mileage = :mileage, 
                       cost = :cost, 
                       description = :description, 
                       technician = :technician, 
                       invoice_number = :invoice_number 
                   WHERE id = :entry_id";

            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':entry_id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':service_id', $data['service_id'], PDO::PARAM_INT);
            $stmt->bindValue(':service_date', $data['service_date'], PDO::PARAM_STR);
            $stmt->bindValue(':mileage', $data['mileage'], PDO::PARAM_INT);
            $stmt->bindValue(':cost', $data['cost'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':technician', $data['technician'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':invoice_number', $data['invoice_number'] ?? null, PDO::PARAM_STR);
            
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            error_log("Error updating service history entry: " . $e->getMessage());
            throw new Exception('Failed to update service history entry');
        }
    }

    /**
     * Delete a service history entry
     * 
     * @param int $entryId The ID of the entry to delete
     * @param int $userId The user ID (for security validation)
     * @return bool True if successful
     * @throws Exception If database query fails or validation fails
     */
    public function deleteEntry(int $entryId, int $userId): bool {
        // First verify the user owns this entry
        try {
            $sql = "SELECT sh.id 
                   FROM service_history sh 
                   JOIN cars c ON sh.car_id = c.id 
                   WHERE sh.id = :entry_id AND c.user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':entry_id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                throw new Exception('Unauthorized access to service history entry');
            }
            
            // Now perform the delete
            $sql = "DELETE FROM service_history WHERE id = :entry_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':entry_id', $entryId, PDO::PARAM_INT);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            error_log("Error deleting service history entry: " . $e->getMessage());
            throw new Exception('Failed to delete service history entry');
        }
    }

    /**
     * Calculate average service frequency in days for a user
     * 
     * @param int $userId The user ID
     * @return float|null Average number of days between services or null if insufficient data
     * @throws Exception If database query fails
     */
    public function getAverageServiceFrequency(int $userId): ?float {
        try {
            // This query gets the average days between consecutive services per car
            $sql = "SELECT AVG(days_between) as avg_frequency
                   FROM (
                       SELECT 
                           car_id,
                           DATEDIFF(service_date, LAG(service_date) OVER (PARTITION BY car_id ORDER BY service_date)) as days_between
                       FROM service_history sh
                       JOIN cars c ON sh.car_id = c.id
                       WHERE c.user_id = :user_id
                   ) as service_gaps
                   WHERE days_between > 0";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetchColumn();
            return $result !== false ? (float)$result : null;
        } catch (PDOException $e) {
            error_log("Error calculating average service frequency: " . $e->getMessage());
            throw new Exception('Failed to calculate service frequency');
        }
    }

    /**
     * Get service history statistics for a user
     * 
     * @param int $userId The user ID
     * @return array Statistics including counts, averages, etc.
     * @throws Exception If database query fails
     */
    public function getServiceStatistics(int $userId): array {
        try {
            $stats = [
                'total_services' => 0,
                'total_cost' => 0.00,
                'average_cost' => 0.00,
                'most_common_service' => null,
                'highest_cost_service' => null,
                'service_count_by_car' => [],
                'service_count_by_type' => []
            ];
            
            // Get total services and cost
            $sql = "SELECT 
                       COUNT(*) as total_services,
                       SUM(cost) as total_cost
                   FROM service_history sh
                   JOIN cars c ON sh.car_id = c.id
                   WHERE c.user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $basic = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($basic) {
                $stats['total_services'] = (int)$basic['total_services'];
                $stats['total_cost'] = (float)$basic['total_cost'];
                $stats['average_cost'] = $stats['total_services'] > 0 ? $stats['total_cost'] / $stats['total_services'] : 0;
            }
            
            // Get most common service
            $mostCommon = $this->getMostCommonServiceType($userId);
            $stats['most_common_service'] = $mostCommon;
            
            // Get highest cost service
            $highestCost = $this->getHighestCostService($userId);
            $stats['highest_cost_service'] = $highestCost;
            
            // Get service count by car
            $sql = "SELECT 
                       c.id, c.make, c.model, c.reg_number,
                       COUNT(*) as service_count,
                       SUM(cost) as total_cost
                   FROM service_history sh
                   JOIN cars c ON sh.car_id = c.id
                   WHERE c.user_id = :user_id
                   GROUP BY c.id, c.make, c.model, c.reg_number";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $stats['service_count_by_car'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get service count by type
            $sql = "SELECT 
                       s.id, s.name,
                       COUNT(*) as service_count,
                       SUM(cost) as total_cost
                   FROM service_history sh
                   JOIN cars c ON sh.car_id = c.id
                   JOIN services s ON sh.service_id = s.id
                   WHERE c.user_id = :user_id
                   GROUP BY s.id, s.name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $stats['service_count_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting service statistics: " . $e->getMessage());
            throw new Exception('Failed to get service statistics');
        }
    }
}