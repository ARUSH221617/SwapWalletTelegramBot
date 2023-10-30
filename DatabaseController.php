<?php

namespace ARUSH;

class DatabaseController
{
    private $host = DB_HOST;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $database = DB_NAME;
    private $conn;

    public function __construct()
    {
        try {
            // Modify the DSN to include the charset=utf8
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8";

            $this->conn = new \PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function query($sql, $params = array(), $insert = false)
    {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                error_log("Error in query: " . $this->conn->errorInfo()[2]);
                die("Error in query: " . $this->conn->errorInfo()[2]);
            }

            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key + 1, $value, $this->getDataType($value));
                }
            }

            $stmt->execute();

            if ($insert) {
                return $this->conn->lastInsertId();
            }

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            die("Error executing query: " . $e->getMessage());
        }
    }

    public function insert($table, $data)
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $values = array_values($data);

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        return $this->query($sql, $values, true);
    }

    public function delete($table, $condition, $params = array())
    {
        $sql = "DELETE FROM $table WHERE $condition";
        return $this->query($sql, $params);
    }

    public function update($table, $data, $condition, $params = array())
    {
        $setClause = implode(", ", array_map(function ($key) {
            return "$key = ?";
        }, array_keys($data)));

        $sql = "UPDATE $table SET $setClause WHERE $condition";
        $values = array_merge(array_values($data), $params);

        return $this->query($sql, $values);
    }

    public function exists($table, $condition, $params = array())
    {
        $sql = "SELECT COUNT(*) AS count FROM $table WHERE $condition";
        $result = $this->query($sql, $params);

        if (!empty($result) && isset($result[0]['count'])) {
            return $result[0]['count'] > 0;
        }

        return false;
    }

    public function upsert($table, $data, $uniqueKeys)
    {
        $columns = implode(', ', array_keys($data));
        $values = array_values($data);

        // Create placeholders like ?, ?, ? for the values
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        // Build the SQL statement
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders) ";
        $sql .= "ON DUPLICATE KEY UPDATE ";
        $sql .= implode(', ', array_map(function ($key) {
            return "$key = VALUES($key)";
        }, array_keys($data)));

        // Execute the SQL statement
        return $this->query($sql, $values);
    }

    private function getDataType($value)
    {
        if (is_int($value)) {
            return \PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return \PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return \PDO::PARAM_NULL;
        } else {
            return \PDO::PARAM_STR;
        }
    }

    public function close()
    {
        $this->conn = null;
    }
}