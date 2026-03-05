<?php

class User {

    private $db;

    public function __construct() {
        $this->db = new Database;
    }

    public function login($id_number, $password) {

        $this->db->query("SELECT * FROM users WHERE id_number = :id_number");
        $this->db->bind(':id_number', $id_number);

        $row = $this->db->single();

        if($row) {
            if(password_verify($password, $row->password)) {
                return $row;
            }
        }

        return false;
    }
}
