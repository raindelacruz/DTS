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

    public function getParentDepartments()
    {
        $stmt = $this->db->query("
            SELECT *
            FROM departments
            WHERE parent_id IS NULL
            ORDER BY division_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartmentById($department_id)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM departments
            WHERE id = :department_id
            LIMIT 1
        ");
        $stmt->execute(['department_id' => $department_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getForwardTargetsForParent($parent_department_id)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM departments
            WHERE (
                (parent_id IS NULL AND id <> :parent_department_id)
                OR parent_id = :parent_department_id
            )
            ORDER BY
                CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END,
                division_name ASC
        ");
        $stmt->execute(['parent_department_id' => $parent_department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isParentDepartment($department_id)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM departments
            WHERE id = :department_id
            AND parent_id IS NULL
        ");

        $stmt->execute(['department_id' => $department_id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function areParentDepartments($department_ids = [])
    {
        $department_ids = array_values(array_unique(array_filter(array_map('intval', $department_ids))));

        if (empty($department_ids)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($department_ids), '?'));
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM departments
            WHERE id IN ($placeholders)
            AND parent_id IS NULL
        ");

        $stmt->execute($department_ids);

        return (int) $stmt->fetchColumn() === count($department_ids);
    }

    public function isValidForwardTargetForParent($parent_department_id, $target_department_id)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM departments
            WHERE id = :target_department_id
            AND (
                (parent_id IS NULL AND id <> :parent_department_id)
                OR parent_id = :parent_department_id
            )
        ");

        $stmt->execute([
            'target_department_id' => $target_department_id,
            'parent_department_id' => $parent_department_id
        ]);

        return (int) $stmt->fetchColumn() > 0;
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
