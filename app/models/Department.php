<?php

class Department
{
    private $db;

    public function __construct()
    {
        $this->db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM departments ORDER BY division_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByDepartment($department_id)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents
            WHERE origin_department_id = :department_id
            OR destination_department_id = :department_id
            ORDER BY created_at DESC
        ");

        $stmt->execute(['department_id' => $department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
