<?php

class User {

    private $db;

    public function __construct() {
        $this->db = new Database;
    }

    public static function roles()
    {
        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'custodian' => 'Custodian'
        ];
    }

    public static function roleExists($role)
    {
        return array_key_exists((string) $role, self::roles());
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

    public function searchWithDepartments($filters = [])
    {
        $conditions = [];
        $params = [];

        $keyword = trim($filters['q'] ?? '');
        if ($keyword !== '') {
            $conditions[] = "(
                u.id_number LIKE :keyword_id
                OR u.firstname LIKE :keyword_firstname
                OR u.lastname LIKE :keyword_lastname
                OR CONCAT_WS(' ', u.firstname, u.lastname) LIKE :keyword_full
            )";
            $keywordParam = '%' . $keyword . '%';
            $params[':keyword_id'] = $keywordParam;
            $params[':keyword_firstname'] = $keywordParam;
            $params[':keyword_lastname'] = $keywordParam;
            $params[':keyword_full'] = $keywordParam;
        }

        $departmentId = (int) ($filters['department_id'] ?? 0);
        if ($departmentId > 0) {
            $conditions[] = 'u.department_id = :department_id';
            $params[':department_id'] = $departmentId;
        }

        $role = trim($filters['role'] ?? '');
        if ($role !== '' && self::roleExists($role)) {
            $conditions[] = 'u.role = :role';
            $params[':role'] = $role;
        }

        $whereSql = '';
        if (!empty($conditions)) {
            $whereSql = 'WHERE ' . implode(' AND ', $conditions);
        }

        $limitSql = '';
        if (isset($filters['_limit'])) {
            $limit = max(1, min(100, (int) $filters['_limit']));
            $offset = max(0, (int) ($filters['_offset'] ?? 0));
            $limitSql = " LIMIT {$limit} OFFSET {$offset}";
        }
        $this->db->query("
            SELECT
                u.*,
                d.division_name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            $whereSql
            ORDER BY
                CASE WHEN u.status = 'inactive' THEN 0 ELSE 1 END,
                u.lastname ASC,
                u.firstname ASC
            $limitSql
        ");

        foreach ($params as $param => $value) {
            $this->db->bind($param, $value);
        }

        return $this->db->resultSet();
    }

    public function countSearchWithDepartments($filters = [])
    {
        $conditions = [];
        $params = [];
        $keyword = trim($filters['q'] ?? '');
        if ($keyword !== '') {
            $conditions[] = "(u.id_number LIKE :keyword_id OR u.firstname LIKE :keyword_firstname OR u.lastname LIKE :keyword_lastname OR CONCAT_WS(' ', u.firstname, u.lastname) LIKE :keyword_full)";
            $value = '%' . $keyword . '%';
            $params = [':keyword_id' => $value, ':keyword_firstname' => $value, ':keyword_lastname' => $value, ':keyword_full' => $value];
        }
        if ((int) ($filters['department_id'] ?? 0) > 0) {
            $conditions[] = 'u.department_id = :department_id';
            $params[':department_id'] = (int) $filters['department_id'];
        }
        if (($filters['role'] ?? '') !== '' && self::roleExists($filters['role'])) {
            $conditions[] = 'u.role = :role';
            $params[':role'] = $filters['role'];
        }
        $this->db->query('SELECT COUNT(*) AS total FROM users u' . (empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions)));
        foreach ($params as $param => $value) {
            $this->db->bind($param, $value);
        }
        $row = $this->db->single();
        return $row ? (int) $row->total : 0;
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

    public function updateRole($id, $role)
    {
        $this->db->query("
            UPDATE users
            SET role = :role
            WHERE id = :id
        ");
        $this->db->bind(':role', $role);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    public function updateDepartment($id, $departmentId)
    {
        $this->db->query("
            UPDATE users
            SET department_id = :department_id
            WHERE id = :id
        ");
        $this->db->bind(':department_id', $departmentId);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    public function updatePassword($id, $password)
    {
        $this->db->query("
            UPDATE users
            SET password = :password,
                password_changed_at = NOW(),
                must_change_password = 0,
                session_version = session_version + 1
            WHERE id = :id
        ");
        $this->db->bind(':password', password_hash($password, PASSWORD_DEFAULT));
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    public function findByEmail($email)
    {
        $this->db->query('SELECT * FROM users WHERE email = :email LIMIT 1');
        $this->db->bind(':email', strtolower(trim($email)));
        return $this->db->single();
    }

    public function markLoginSuccessful($id)
    {
        try {
            $this->db->query('UPDATE users SET last_login_at = NOW() WHERE id = :id');
            $this->db->bind(':id', $id);
            return $this->db->execute();
        } catch (Throwable $e) {
            appLog('error', 'Last-login update failed', ['user_id' => (int) $id, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function configureMfa($id, $encryptedSecret)
    {
        $this->db->query('UPDATE users SET mfa_secret = :secret, mfa_enabled = 1, session_version = session_version + 1 WHERE id = :id');
        $this->db->bind(':secret', $encryptedSecret);
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function disableMfa($id)
    {
        $this->db->query('UPDATE users SET mfa_secret = NULL, mfa_enabled = 0, session_version = session_version + 1 WHERE id = :id');
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function currentSessionVersion($id)
    {
        $this->db->query('SELECT session_version FROM users WHERE id = :id LIMIT 1');
        $this->db->bind(':id', $id);
        $row = $this->db->single();
        return $row ? (int) $row->session_version : 0;
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

    public function updateProfile($id, $email)
    {
        $this->db->query("
            UPDATE users
            SET email = :email
            WHERE id = :id
        ");
        $this->db->bind(':email', $email);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }
}
