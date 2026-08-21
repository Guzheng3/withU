<?php

class MediaDatabase {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $config = require ROOT_PATH . '/config/media_database.php';
        $host = $config['host'] ?? '127.0.0.1';
        $port = (int)($config['port'] ?? 3306);
        $dbname = $config['dbname'] ?? 'withu_media';
        $charset = $config['charset'] ?? 'utf8mb4';
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    }

    public static function getInstance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getPDO() { return $this->pdo; }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_map(static function ($field) { return ':' . $field; }, $fields);
        $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)', $table, implode('`, `', $fields), implode(', ', $placeholders));
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $field) $set[] = "`{$field}` = :{$field}";
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
        $this->query($sql, array_merge($data, $whereParams));
        return true;
    }

    public function delete($table, $where, $params = []) {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        $this->query($sql, $params);
        return true;
    }

    public function count($table, $where = '1=1', $params = []) {
        $row = $this->fetch(sprintf('SELECT COUNT(*) AS c FROM `%s` WHERE %s', $table, $where), $params);
        return (int)($row['c'] ?? 0);
    }
}
