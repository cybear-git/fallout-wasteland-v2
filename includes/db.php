<?php
/**
 * Database Connection Wrapper
 * 
 * This file provides a unified interface for database connections.
 * It wraps the main config/database.php to ensure consistent usage across the project.
 * 
 * Usage:
 *   require_once __DIR__ . '/db.php';
 *   $pdo = getDbConnection();
 */

// Prevent multiple inclusions
if (!defined('DB_WRAPPER_INCLUDED')) {
    define('DB_WRAPPER_INCLUDED', true);
    
    // Include the main database configuration
    require_once __DIR__ . '/../config/database.php';
    
    /**
     * Get database connection
     * 
     * @return PDO Database connection instance
     * @throws PDOException If connection fails
     */
    function getDbConnection() {
        static $pdo = null;
        
        if ($pdo === null) {
            $pdo = connectDB();
        }
        
        return $pdo;
    }
    
    /**
     * Execute a prepared statement with error handling
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement|false Executed statement or false on failure
     */
    function dbExecute($sql, $params = []) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * Fetch a single row
     * 
     * @param string $sql SQL query
     * @param array $params Parameters to bind
     * @return array|null Associative array or null if not found
     */
    function dbFetchOne($sql, $params = []) {
        $stmt = dbExecute($sql, $params);
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }
        return null;
    }
    
    /**
     * Fetch all rows
     * 
     * @param string $sql SQL query
     * @param array $params Parameters to bind
     * @return array Array of associative arrays
     */
    function dbFetchAll($sql, $params = []) {
        $stmt = dbExecute($sql, $params);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }
    
    /**
     * Get last insert ID
     * 
     * @return int Last insert ID
     */
    function dbLastInsertId() {
        $pdo = getDbConnection();
        return (int)$pdo->lastInsertId();
    }
}
