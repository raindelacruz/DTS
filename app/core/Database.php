<?php

class Database {

    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;

    private $dbh;
    private $stmt;

    public function __construct() {
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;
        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    public function query($sql) {
        $this->stmt = $this->dbh->prepare($sql);
    }

    public function bind($param, $value) {
        $this->stmt->bindValue($param, $value);
    }

    public function execute() {
        return $this->stmt->execute();
    }

    public function single() {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }
}
