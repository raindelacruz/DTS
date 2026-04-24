<?php

class User {

    private $db;

    public function __construct() {
        $this->db = new Database;
        $this->ensureSchema();
        $this->ensureManagerRoles();
    }

    public function login($id_number, $password) {
        $this->db->query("SELECT * FROM users WHERE id_number = :id_number LIMIT 1");
        $this->db->bind(':id_number', $id_number);

        $row = $this->db->single();

        if($row) {
            if(password_verify($password, $row->password)) {
                return $row;
            }
        }

        return false;
    }

    public function register($data)
    {
        $this->db->query("
            INSERT INTO users (
                id_number,
                firstname,
                lastname,
                email,
                department_id,
                role,
                status,
                password
            ) VALUES (
                :id_number,
                :firstname,
                :lastname,
                :email,
                :department_id,
                :role,
                :status,
                :password
            )
        ");

        $this->db->bind(':id_number', $data['id_number']);
        $this->db->bind(':firstname', $data['firstname']);
        $this->db->bind(':lastname', $data['lastname']);
        $this->db->bind(':email', $data['email']);
        $this->db->bind(':department_id', $data['department_id']);
        $this->db->bind(':role', $data['role']);
        $this->db->bind(':status', $data['status']);
        $this->db->bind(':password', password_hash($data['password'], PASSWORD_DEFAULT));

        return $this->db->execute();
    }

    public function findByIdNumber($idNumber)
    {
        $this->db->query("SELECT * FROM users WHERE id_number = :id_number LIMIT 1");
        $this->db->bind(':id_number', $idNumber);
        return $this->db->single();
    }

    public function getAllWithDepartments()
    {
        $this->db->query("
            SELECT
                u.*,
                d.division_name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            ORDER BY
                CASE WHEN u.status = 'inactive' THEN 0 ELSE 1 END,
                u.lastname ASC,
                u.firstname ASC
        ");

        return $this->db->resultSet();
    }

    public function updateStatus($id, $status)
    {
        $this->db->query("
            UPDATE users
            SET status = :status
            WHERE id = :id
        ");
        $this->db->bind(':status', $status);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    public function findById($id)
    {
        $this->db->query("SELECT * FROM users WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    public function findWithDepartmentById($id)
    {
        $this->db->query("
            SELECT
                u.*,
                d.division_name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = :id
            LIMIT 1
        ");
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    public function emailExistsForOtherUser($email, $userId)
    {
        $this->db->query("
            SELECT id
            FROM users
            WHERE email = :email
            AND id <> :id
            LIMIT 1
        ");
        $this->db->bind(':email', $email);
        $this->db->bind(':id', $userId);

        return (bool) $this->db->single();
    }

    public function updateProfile($id, $departmentId, $email)
    {
        $this->db->query("
            UPDATE users
            SET department_id = :department_id,
                email = :email
            WHERE id = :id
        ");
        $this->db->bind(':department_id', $departmentId);
        $this->db->bind(':email', $email);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    private function ensureSchema()
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_number VARCHAR(50) NOT NULL UNIQUE,
                firstname VARCHAR(100) NOT NULL,
                lastname VARCHAR(100) NOT NULL,
                email VARCHAR(150) DEFAULT NULL,
                department_id INT(11) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                status VARCHAR(20) NOT NULL DEFAULT 'inactive',
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->db->execute();

        if (!$this->columnExists('users', 'status')) {
            $this->db->query("
                ALTER TABLE users
                ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'inactive'
                AFTER role
            ");
            $this->db->execute();
        }

        if (!$this->columnExists('users', 'created_at')) {
            $this->db->query("
                ALTER TABLE users
                ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ");
            $this->db->execute();
        }

        if (!$this->columnExists('users', 'email')) {
            $this->db->query("
                ALTER TABLE users
                ADD COLUMN email VARCHAR(150) DEFAULT NULL
                AFTER lastname
            ");
            $this->db->execute();
        }
    }

    private function ensureManagerRoles()
    {
        $managerIds = ['000001', '000002', '000006'];
        $placeholders = implode(',', array_fill(0, count($managerIds), '?'));

        $this->db->query("UPDATE users SET role = 'manager' WHERE id_number IN ($placeholders)");
        foreach ($managerIds as $index => $idNumber) {
            $this->db->bind($index + 1, $idNumber);
        }
        $this->db->execute();
    }

    private function columnExists($table, $column)
    {
        $this->db->query("
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :schema_name
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");
        $this->db->bind(':schema_name', DB_NAME);
        $this->db->bind(':table_name', $table);
        $this->db->bind(':column_name', $column);

        $result = $this->db->single();
        return $result && (int) $result->total > 0;
    }
}
