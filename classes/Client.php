<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// FILE 4: classes/Client.php
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

class Client {
    private $db;
    private $lastError = '';
    
public function __construct() {
    global $pdo; // Assuming you have a global PDO connection
    $this->db = $pdo;
}
    
    public function update($id, $data) {
        try {
            // Prepare the SQL statement
            $sql = "UPDATE clients SET 
                    full_name = :full_name,
                    phone_number = :phone_number,
                    kra_pin = :kra_pin,
                    password = :password,
                    tax_obligations = :tax_obligations,
                    email_address = :email_address,
                    id_number = :id_number,
                    client_type = :client_type,
                    county = :county,
                    etims_status = :etims_status,
                    updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            
            // Convert tax_obligations array to JSON string
            $taxObligationsJson = json_encode($data['tax_obligations']);
            
            // Bind parameters
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':phone_number', $data['phone_number']);
            $stmt->bindParam(':kra_pin', $data['kra_pin']);
            $stmt->bindParam(':password', $data['password']);
            $stmt->bindParam(':tax_obligations', $taxObligationsJson);
            $stmt->bindParam(':email_address', $data['email_address']);
            $stmt->bindParam(':id_number', $data['id_number']);
            $stmt->bindParam(':client_type', $data['client_type']);
            $stmt->bindParam(':county', $data['county']);
            $stmt->bindParam(':etims_status', $data['etims_status']);
            
            // Execute the statement
            $result = $stmt->execute();
            
            if ($result) {
                return true;
            } else {
                $this->lastError = 'Database update failed';
                return false;
            }
            
        } catch (PDOException $e) {
            $this->lastError = 'Database error: ' . $e->getMessage();
            error_log('Client update error: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->lastError = 'General error: ' . $e->getMessage();
            error_log('Client update error: ' . $e->getMessage());
            return false;
        }
    }
        
    
public function create($data) {
    try {
        $sql = "INSERT INTO clients (
                    full_name, phone_number, kra_pin, password, 
                    tax_obligations, email_address, id_number, 
                    client_type, county, etims_status, created_by, 
                    registration_date
                ) VALUES (
                    :full_name, :phone_number, :kra_pin, :password,
                    :tax_obligations, :email_address, :id_number,
                    :client_type, :county, :etims_status, :created_by,
                    NOW()
                )";
        
        $stmt = $this->db->prepare($sql);
        
        // Convert tax_obligations array to JSON string
        $taxObligationsJson = json_encode($data['tax_obligations']);
        
        // Bind parameters
        $stmt->bindParam(':full_name', $data['full_name']);
        $stmt->bindParam(':phone_number', $data['phone_number']);
        $stmt->bindParam(':kra_pin', $data['kra_pin']);
        $stmt->bindParam(':password', $data['password']);
        $stmt->bindParam(':tax_obligations', $taxObligationsJson);
        $stmt->bindParam(':email_address', $data['email_address']);
        $stmt->bindParam(':id_number', $data['id_number']);
        $stmt->bindParam(':client_type', $data['client_type']);
        $stmt->bindParam(':county', $data['county']);
        $stmt->bindParam(':etims_status', $data['etims_status']);
        $stmt->bindParam(':created_by', $data['created_by']);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        } else {
            $this->lastError = 'Database insert failed';
            return false;
        }
        
    } catch (PDOException $e) {
        $this->lastError = 'Database error: ' . $e->getMessage();
        error_log('Client create error: ' . $e->getMessage());
        return false;
    }
}
    
    public function delete($id) {
        try {
            $sql = "DELETE FROM clients WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return true;
            } else {
                $this->lastError = 'Database delete failed';
                return false;
            }
            
        } catch (PDOException $e) {
            $this->lastError = 'Database error: ' . $e->getMessage();
            error_log('Client delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        
        if ($client && $client['tax_obligations']) {
            $client['tax_obligations'] = json_decode($client['tax_obligations'], true);
        }
        
        return $client;
    }

    public function getAll($filters = []) {
        $query = "SELECT * FROM clients WHERE 1=1";
        $params = [];

        // Search filters
        if (!empty($filters['search'])) {
            $query .= " AND (full_name LIKE ? OR kra_pin LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Client type filter
        if (!empty($filters['client_type'])) {
            $query .= " AND client_type = ?";
            $params[] = $filters['client_type'];
        }

        // Tax obligation filter
        if (!empty($filters['tax_obligation'])) {
            $query .= " AND JSON_CONTAINS(tax_obligations, ?)";
            $params[] = '"' . $filters['tax_obligation'] . '"';
        }

        // County filter
        if (!empty($filters['county'])) {
            $query .= " AND county = ?";
            $params[] = $filters['county'];
        }

        // ETIMS status filter
        if (!empty($filters['etims_status'])) {
            $query .= " AND etims_status = ?";
            $params[] = $filters['etims_status'];
        }

        $query .= " ORDER BY registration_date DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $clients = $stmt->fetchAll();

        // Decode tax obligations for each client
        foreach ($clients as &$client) {
            if ($client['tax_obligations']) {
                $client['tax_obligations'] = json_decode($client['tax_obligations'], true);
            }
        }

        return $clients;
    }

    public function getStats() {
        $stats = [];

        // Total clients
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM clients");
        $stats['total_clients'] = $stmt->fetch()['total'];

        // Clients by type
        $stmt = $this->db->query("
            SELECT client_type, COUNT(*) as count 
            FROM clients 
            GROUP BY client_type
        ");
        $stats['by_type'] = $stmt->fetchAll();

        // ETIMS registration status
        $stmt = $this->db->query("
            SELECT etims_status, COUNT(*) as count 
            FROM clients 
            GROUP BY etims_status
        ");
        $stats['etims_status'] = $stmt->fetchAll();

        // Recent registrations (last 30 days)
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM clients 
            WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['recent_registrations'] = $stmt->fetch()['count'];

        return $stats;
    }

    private function krapiExists($krapin) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE kra_pin = ?");
        $stmt->execute([$krapin]);
        return $stmt->fetchColumn() > 0;
    }

    private function idNumberExists($idNumber) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE id_number = ?");
        $stmt->execute([$idNumber]);
        return $stmt->fetchColumn() > 0;
    }

    private function krapiExistsForOther($krapin, $excludeId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE kra_pin = ? AND id != ?");
        $stmt->execute([$krapin, $excludeId]);
        return $stmt->fetchColumn() > 0;
    }

    private function idNumberExistsForOther($idNumber, $excludeId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE id_number = ? AND id != ?");
        $stmt->execute([$idNumber, $excludeId]);
        return $stmt->fetchColumn() > 0;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getAllPaginated($filters = [], $page = 1, $perPage = 25, $sortBy = 'registration_date', $sortOrder = 'DESC') {
        $validSortColumns = ['full_name', 'kra_pin', 'phone_number', 'client_type', 'county', 'etims_status', 'registration_date'];
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'registration_date';
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;

        $query = "SELECT * FROM clients WHERE 1=1";
        $countQuery = "SELECT COUNT(*) FROM clients WHERE 1=1";
        $params = [];

        // Filters
        if (!empty($filters['search'])) {
            $query .= " AND (full_name LIKE ? OR kra_pin LIKE ?)";
            $countQuery .= " AND (full_name LIKE ? OR kra_pin LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filters['client_type'])) {
            $query .= " AND client_type = ?";
            $countQuery .= " AND client_type = ?";
            $params[] = $filters['client_type'];
        }

        if (!empty($filters['tax_obligation'])) {
            $query .= " AND JSON_CONTAINS(tax_obligations, ?)";
            $countQuery .= " AND JSON_CONTAINS(tax_obligations, ?)";
            $params[] = '"' . $filters['tax_obligation'] . '"';
        }

        if (!empty($filters['county'])) {
            $query .= " AND county = ?";
            $countQuery .= " AND county = ?";
            $params[] = $filters['county'];
        }

        if (!empty($filters['etims_status'])) {
            $query .= " AND etims_status = ?";
            $countQuery .= " AND etims_status = ?";
            $params[] = $filters['etims_status'];
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $query .= " AND registration_date >= ?";
            $countQuery .= " AND registration_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND registration_date <= ?";
            $countQuery .= " AND registration_date <= ?";
            $params[] = $filters['date_to'];
        }

        $query .= " ORDER BY $sortBy $sortOrder LIMIT $perPage OFFSET $offset";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $clients = $stmt->fetchAll();

        // Decode tax obligations for each client
        foreach ($clients as &$client) {
            if ($client['tax_obligations']) {
                $client['tax_obligations'] = json_decode($client['tax_obligations'], true);
            }
        }

        // Get total count
        $countStmt = $this->db->prepare($countQuery);
        // For count query, exclude the last two params (limit and offset)
        $countParams = array_slice($params, 0, count($params) - 2);
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();

        return [
            'data' => $clients,
            'total' => $total
        ];
    }

    public function updateEtimsStatus($id, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE clients SET etims_status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch (PDOException $e) {
            $this->lastError = 'Failed to update ETIMS status: ' . $e->getMessage();
            return false;
        }
    }
    public function getClientsByCounty($filters = []) {
        $query = "SELECT 
                    county,
                    COUNT(*) as total,
                    SUM(CASE WHEN client_type = 'Company' THEN 1 ELSE 0 END) as companies,
                    SUM(CASE WHEN client_type = 'Individual' THEN 1 ELSE 0 END) as individuals,
                    SUM(CASE WHEN etims_status = 'Registered' THEN 1 ELSE 0 END) as etims_registered
                  FROM clients
                  WHERE 1=1";
        $params = [];

        // Apply date range filters if provided
        if (!empty($filters['date_from'])) {
            $query .= " AND registration_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND registration_date <= ?";
            $params[] = $filters['date_to'];
        }

        $query .= " GROUP BY county ORDER BY county ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getClientsByEtimsStatus($filters = []) {
        $query = "SELECT 
                    etims_status,
                    COUNT(*) as count
                  FROM clients
                  WHERE 1=1";
        $params = [];

        // Apply date range filters if provided
        if (!empty($filters['date_from'])) {
            $query .= " AND registration_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND registration_date <= ?";
            $params[] = $filters['date_to'];
        }

        $query .= " GROUP BY etims_status ORDER BY etims_status ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    public function getRegistrationTrends($filters = []) {
        $query = "SELECT 
                    DATE_FORMAT(registration_date, '%Y-%m') AS month,
                    COUNT(*) AS count,
                    SUM(CASE WHEN client_type = 'Company' THEN 1 ELSE 0 END) AS companies,
                    SUM(CASE WHEN client_type = 'Individual' THEN 1 ELSE 0 END) AS individuals
                  FROM clients
                  WHERE 1=1";
        $params = [];

        if (!empty($filters['date_from'])) {
            $query .= " AND registration_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND registration_date <= ?";
            $params[] = $filters['date_to'];
        }

        $query .= " GROUP BY month ORDER BY month ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    /**
     * Get clients with VAT obligations
     */
    public function getVATClients() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, full_name, kra_pin, email_address
                FROM clients 
                WHERE tax_obligations LIKE '%VAT%' 
                ORDER BY full_name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching VAT clients: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get VAT records for a specific client and year
     */
    public function getVATRecords($clientId, $year) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM vat_records 
                WHERE client_id = ? AND record_year = ? 
                ORDER BY record_month
            ");
            $stmt->execute([$clientId, $year]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching VAT records: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate yearly VAT totals for a client
     */
    public function getVATYearlyTotals($clientId, $year) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(sales_amount) as total_sales,
                    SUM(purchases_amount) as total_purchases,
                    SUM(sales_vat) as total_sales_vat,
                    SUM(purchases_vat) as total_purchases_vat,
                    SUM(net_vat) as total_net_vat
                FROM vat_records 
                WHERE client_id = ? AND record_year = ?
            ");
            $stmt->execute([$clientId, $year]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'sales_amount' => $result['total_sales'] ?? 0,
                'purchases_amount' => $result['total_purchases'] ?? 0,
                'sales_vat' => $result['total_sales_vat'] ?? 0,
                'purchases_vat' => $result['total_purchases_vat'] ?? 0,
                'net_vat' => $result['total_net_vat'] ?? 0
            ];
        } catch (PDOException $e) {
            error_log("Error calculating VAT totals: " . $e->getMessage());
            return [
                'sales_amount' => 0,
                'purchases_amount' => 0,
                'sales_vat' => 0,
                'purchases_vat' => 0,
                'net_vat' => 0
            ];
        }
    }

    /**
     * Get VAT statistics for dashboard
     */
    public function getVATStats() {
        try {
            // Count of VAT clients
            $vatClientsStmt = $this->db->prepare("
                SELECT COUNT(*) as vat_clients 
                FROM clients 
                WHERE tax_obligations LIKE '%VAT%'
            ");
            $vatClientsStmt->execute();
            $vatClients = $vatClientsStmt->fetch(PDO::FETCH_ASSOC)['vat_clients'];

            // Current month VAT records
            $currentMonth = date('n');
            $currentYear = date('Y');
            
            $currentMonthStmt = $this->db->prepare("
                SELECT COUNT(*) as current_month_records
                FROM vat_records 
                WHERE record_month = ? AND record_year = ?
            ");
            $currentMonthStmt->execute([$currentMonth, $currentYear]);
            $currentMonthRecords = $currentMonthStmt->fetch(PDO::FETCH_ASSOC)['current_month_records'];

            // Total VAT collected this year
            $yearTotalStmt = $this->db->prepare("
                SELECT SUM(net_vat) as year_total_vat
                FROM vat_records 
                WHERE record_year = ? AND net_vat > 0
            ");
            $yearTotalStmt->execute([$currentYear]);
            $yearTotalVAT = $yearTotalStmt->fetch(PDO::FETCH_ASSOC)['year_total_vat'] ?? 0;

            return [
                'vat_clients' => $vatClients,
                'current_month_records' => $currentMonthRecords,
                'year_total_vat' => $yearTotalVAT
            ];
        } catch (PDOException $e) {
            error_log("Error fetching VAT stats: " . $e->getMessage());
            return [
                'vat_clients' => 0,
                'current_month_records' => 0,
                'year_total_vat' => 0
            ];
        }
    }
}
