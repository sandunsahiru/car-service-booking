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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
            return (float)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting service price: " . $e->getMessage());
            throw new Exception('Failed to get service price');
        }
    }
}