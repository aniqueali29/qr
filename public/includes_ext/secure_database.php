<?php
/**
 * Secure Database Query Builder
 * Provides safe database operations with built-in SQL injection protection
 */

class SecureDatabase {
    private $pdo;
    private $last_query;
    private $last_params;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function query($sql, $params = []) {
        try {
            $this->last_query = $sql;
            $this->last_params = $params;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage() . " | Query: " . $sql . " | Params: " . json_encode($params));
            throw new Exception("Database operation failed");
        }
    }
    
    /**
     * Fetch a single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert a record and return the last insert ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "`{$column}` = ?";
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete records
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Check if a value exists in a table
     */
    public function exists($table, $where, $params = []) {
        $sql = "SELECT 1 FROM `{$table}` WHERE {$where} LIMIT 1";
        $stmt = $this->query($sql, $params);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Count records
     */
    public function count($table, $where = '1=1', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM `{$table}` WHERE {$where}";
        $result = $this->fetchOne($sql, $params);
        return (int)$result['count'];
    }
    
    /**
     * Validate table name to prevent injection
     */
    private function validateTableName($table) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new Exception("Invalid table name: {$table}");
        }
        return $table;
    }
    
    /**
     * Validate column name to prevent injection
     */
    private function validateColumnName($column) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new Exception("Invalid column name: {$column}");
        }
        return $column;
    }
    
    /**
     * Sanitize input for database operations
     */
    public function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        
        if (is_string($input)) {
            return trim($input);
        }
        
        return $input;
    }
    
    /**
     * Get the last executed query for debugging
     */
    public function getLastQuery() {
        return [
            'query' => $this->last_query,
            'params' => $this->last_params
        ];
    }
    
    /**
     * Execute a raw query with proper error handling
     */
    public function rawQuery($sql, $params = []) {
        try {
            $this->last_query = $sql;
            $this->last_params = $params;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("Raw query failed: " . $e->getMessage() . " | Query: " . $sql . " | Params: " . json_encode($params));
            throw new Exception("Database operation failed");
        }
    }
}

/**
 * Input validation functions
 */
class InputValidator {
    
    /**
     * Validate and sanitize integer input
     */
    public static function validateInt($value, $min = null, $max = null) {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            throw new Exception("Invalid integer value");
        }
        
        if ($min !== null && $int < $min) {
            throw new Exception("Value must be at least {$min}");
        }
        
        if ($max !== null && $int > $max) {
            throw new Exception("Value must be at most {$max}");
        }
        
        return $int;
    }
    
    /**
     * Validate and sanitize string input
     */
    public static function validateString($value, $maxLength = null, $allowEmpty = true) {
        if (!$allowEmpty && empty(trim($value))) {
            throw new Exception("Value cannot be empty");
        }
        
        $string = trim($value);
        
        if ($maxLength !== null && strlen($string) > $maxLength) {
            throw new Exception("Value must be at most {$maxLength} characters");
        }
        
        return $string;
    }
    
    /**
     * Validate and sanitize email input
     */
    public static function validateEmail($value) {
        $email = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw new Exception("Invalid email format");
        }
        return $email;
    }
    
    /**
     * Validate and sanitize date input
     */
    public static function validateDate($value, $format = 'Y-m-d') {
        $date = DateTime::createFromFormat($format, $value);
        if ($date === false || $date->format($format) !== $value) {
            throw new Exception("Invalid date format. Expected: {$format}");
        }
        return $date->format($format);
    }
    
    /**
     * Validate and sanitize student ID format
     */
    public static function validateStudentId($value) {
        $pattern = '/^\d{2}-[A-Z]{3}-\d{3}$/' ;
        if (!preg_match($pattern, $value)) {
            throw new Exception("Invalid student ID format. Expected: XX-XXX-XXX");
        }
        return strtoupper($value);
    }
    
    /**
     * Validate array of IDs for bulk operations
     */
    public static function validateIdArray($ids, $maxCount = 100) {
        if (!is_array($ids)) {
            throw new Exception("IDs must be an array");
        }
        
        if (count($ids) > $maxCount) {
            throw new Exception("Too many IDs. Maximum allowed: {$maxCount}");
        }
        
        $validatedIds = [];
        foreach ($ids as $id) {
            $validatedIds[] = self::validateInt($id, 1);
        }
        
        return $validatedIds;
    }
}
?>

