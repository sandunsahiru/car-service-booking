<?php
declare(strict_types=1);

class Service {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get all available services
     */
    public function getAllServices(): array {
        try {
            $stmt = $this->db->query("SELECT * FROM services ORDER BY price ASC");
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure price is always a float
            foreach ($services as &$service) {
                if (isset($service['price'])) {
                    $service['price'] = (float)$service['price'];
                } else {
                    $service['price'] = 0.00;
                }
            }
            
            return $services;
        } catch (PDOException $e) {
            error_log("Error getting services: " . $e->getMessage());
            throw new Exception('Failed to get services');
        }
    }

    /**
     * Get service by ID
     */
    public function getById(int $serviceId): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM services WHERE id = ?");
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($service) {
                // Ensure price is a float
                $service['price'] = isset($service['price']) ? (float)$service['price'] : 0.00;
            }
            
            return $service ?: null;
        } catch (PDOException $e) {
            error_log("Error getting service: " . $e->getMessage());
            throw new Exception('Failed to get service details');
        }
    }

    /**
     * Get service price
     */
    public function getServicePrice(int $serviceId): float {
        try {
            $stmt = $this->db->prepare("SELECT price FROM services WHERE id = ?");
            $stmt->execute([$serviceId]);
            $price = $stmt->fetchColumn();
            
            // Ensure we return a float, default to 0.00 if null
            return $price !== false ? (float)$price : 0.00;
        } catch (PDOException $e) {
            error_log("Error getting service price: " . $e->getMessage());
            throw new Exception('Failed to get service price');
        }
    }
}