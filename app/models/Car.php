<?php
declare(strict_types=1);

class Car {
    private PDO $db;

    /**
     * Initialize Car model with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Create a new car record
     * 
     * @param array $data Car data
     * @return bool True on success, false on failure
     * @throws Exception If validation fails
     */
    public function create(array $data): bool {
        try {
            $this->validateCarData($data);
    
            $sql = "INSERT INTO cars (
                    user_id, 
                    make, 
                    model, 
                    year, 
                    reg_number, 
                    mileage, 
                    last_service_date,
                    created_at
                   ) VALUES (
                    :user_id, 
                    :make, 
                    :model, 
                    :year, 
                    :reg_number, 
                    :mileage, 
                    :last_service_date,
                    NOW()
                   )";
            
            $params = [
                ':user_id' => $data['user_id'],
                ':make' => $data['make'],
                ':model' => $data['model'],
                ':year' => $data['year'],
                ':reg_number' => $data['reg_number'],
                ':mileage' => $data['mileage'],
                ':last_service_date' => !empty($data['last_service']) ? date('Y-m-d', strtotime($data['last_service'])) : null
            ];
    
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database error while creating car record: " . $e->getMessage());
            throw new Exception('Failed to create car record');
        }
    }

    
    

    /**
     * Check if a registration number already exists
     * 
     * @param string $regNumber Registration number to check
     * @return bool True if exists, false otherwise
     */
    public function checkRegNumberExists(string $regNumber): bool {
        try {
            $sql = "SELECT COUNT(*) FROM cars WHERE reg_number = :reg_number";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':reg_number' => strtoupper(trim($regNumber))]);
            
            return (bool)$stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Database error checking registration number: " . $e->getMessage());
            throw new Exception('Error checking registration number');
        }
    }

    /**
     * Get car details by ID
     * 
     * @param int $carId Car ID
     * @return array|null Car details or null if not found
     */
    public function getById(int $carId): ?array {
        try {
            $sql = "SELECT * FROM cars WHERE id = :car_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':car_id' => $carId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;

        } catch (PDOException $e) {
            error_log("Database error getting car details: " . $e->getMessage());
            throw new Exception('Error retrieving car details');
        }
    }

    /**
     * Get all cars for a user
     * 
     * @param int $userId User ID
     * @return array List of cars
     */
    public function getByUserId(int $userId): array {
        try {
            $sql = "SELECT * FROM cars WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Database error getting user cars: " . $e->getMessage());
            throw new Exception('Error retrieving user cars');
        }
    }

    /**
     * Update car details
     * 
     * @param int $carId Car ID
     * @param array $data Updated car data
     * @return bool True on success, false on failure
     */
    // Also update the update() method to use the correct column name
    public function update(int $carId, array $data): bool {
        try {
            $this->validateCarData($data, true);
    
            $sql = "UPDATE cars SET 
                    make = :make,
                    model = :model,
                    year = :year,
                    reg_number = :reg_number,
                    mileage = :mileage,
                    last_service_date = :last_service_date,  -- Changed column name
                    updated_at = NOW()
                   WHERE id = :car_id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            
            $params = [
                ':car_id' => $carId,
                ':user_id' => $data['user_id'],
                ':make' => $data['make'],
                ':model' => $data['model'],
                ':year' => $data['year'],
                ':reg_number' => $data['reg_number'],
                ':mileage' => $data['mileage'],
                ':last_service_date' => $data['last_service'] // Keep parameter name for compatibility
            ];
    
            error_log("Updating car record ID: {$carId} for user ID: {$data['user_id']}");
    
            return $stmt->execute($params);
    
        } catch (PDOException $e) {
            error_log("Database error updating car: " . $e->getMessage());
            throw new Exception('Failed to update car details');
        }
    }

    /**
     * Delete a car record
     * 
     * @param int $carId Car ID
     * @param int $userId User ID (for verification)
     * @return bool True on success, false on failure
     */
    public function delete(int $carId, int $userId): bool {
        try {
            $sql = "DELETE FROM cars WHERE id = :car_id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            
            error_log("Deleting car record ID: {$carId} for user ID: {$userId}");

            return $stmt->execute([
                ':car_id' => $carId,
                ':user_id' => $userId
            ]);

        } catch (PDOException $e) {
            error_log("Database error deleting car: " . $e->getMessage());
            throw new Exception('Failed to delete car record');
        }
    }

    /**
     * Update car mileage
     * 
     * @param int $carId Car ID
     * @param int $mileage New mileage value
     * @param int $userId User ID (for verification)
     * @return bool True on success, false on failure
     */
    public function updateMileage(int $carId, int $mileage, int $userId): bool {
        try {
            if ($mileage < 0 || $mileage > 999999) {
                throw new Exception('Invalid mileage value (must be between 0 and 999,999)');
            }

            $sql = "UPDATE cars SET mileage = :mileage, updated_at = NOW() 
                   WHERE id = :car_id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            
            error_log("Updating mileage for car ID: {$carId}, User ID: {$userId}, New mileage: {$mileage}");

            return $stmt->execute([
                ':car_id' => $carId,
                ':user_id' => $userId,
                ':mileage' => $mileage
            ]);

        } catch (PDOException $e) {
            error_log("Database error updating mileage: " . $e->getMessage());
            throw new Exception('Failed to update mileage');
        }
    }

    /**
     * Validate car data
     * 
     * @param array $data Car data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @throws Exception If validation fails
     */
    private function validateCarData(array $data, bool $isUpdate = false): void {
        $required = ['user_id', 'make', 'model', 'year', 'reg_number', 'mileage'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || ($data[$field] === '' && $field !== 'last_service')) {
                throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
            }
        }

        // Validate year
        $year = (int)$data['year'];
        $currentYear = (int)date('Y');
        if ($year < 1900 || $year > ($currentYear + 1)) {
            throw new Exception('Invalid vehicle year');
        }

        // Validate mileage
        $mileage = (int)$data['mileage'];
        if ($mileage < 0 || $mileage > 999999) {
            throw new Exception('Invalid mileage value (must be between 0 and 999,999)');
        }

        // Validate registration number format
        if (!preg_match('/^[A-Z0-9-]{1,15}$/', strtoupper(trim($data['reg_number'])))) {
            throw new Exception('Invalid registration number format');
        }

        // Check for duplicate registration number
        if (!$isUpdate && $this->checkRegNumberExists($data['reg_number'])) {
            throw new Exception('Vehicle registration number already exists');
        }

        // Validate last service date if provided
        if (!empty($data['last_service'])) {
            $date = strtotime($data['last_service']);
            if ($date === false || $date > time()) {
                throw new Exception('Invalid last service date');
            }
        }
    }

    /**
     * Get maintenance schedule for a car
     * 
     * @param int $carId Car ID
     * @return array Maintenance schedule
     */
    public function getMaintenanceSchedule(int $carId): array {
        try {
            $car = $this->getById($carId);
            if (!$car) {
                throw new Exception('Car not found');
            }
    
            $lastService = $car['last_service_date'] ? new DateTime($car['last_service_date']) : null;
            $nextService = $lastService ? (clone $lastService)->modify('+6 months') : null;
            
            return [
                'car_id' => $carId,
                'current_mileage' => $car['mileage'],
                'last_service_date' => $lastService ? $lastService->format('Y-m-d') : null,
                'next_service' => $nextService ? $nextService->format('Y-m-d') : null,
                'service_interval_months' => 6,
                'service_interval_miles' => 5000,
                'needs_service' => $this->checkIfServiceNeeded($car)
            ];
        } catch (Exception $e) {
            error_log("Error getting maintenance schedule: " . $e->getMessage());
            throw new Exception('Failed to get maintenance schedule');
        }
    }

    /**
     * Check if a car needs service
     * 
     * @param array $car Car data
     * @return bool True if service needed, false otherwise
     */
    private function checkIfServiceNeeded(array $car): bool {
        // Update to use last_service_date instead of last_service
        if (empty($car['last_service_date'])) {
            return true;
        }

        $lastService = new DateTime($car['last_service_date']);
        $sixMonthsAgo = new DateTime('-6 months');
        
        if ($lastService < $sixMonthsAgo) {
            return true;
        }

        $mileageSinceService = isset($car['last_service_mileage']) 
            ? $car['mileage'] - $car['last_service_mileage']
            : 5001;

        return $mileageSinceService > 5000;
    }
}