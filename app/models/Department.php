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

    public function getParentDepartmentsWithDivisionCount()
    {
        $stmt = $this->db->query("
            SELECT p.*,
                   (SELECT COUNT(*) FROM departments c WHERE c.parent_id = p.id) AS division_count
            FROM departments p
            WHERE p.parent_id IS NULL
            ORDER BY p.department_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChildDepartmentsForParent($parent_department_id)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM departments
            WHERE parent_id = :parent_department_id
            ORDER BY division_name ASC
        ");

        $stmt->execute(['parent_department_id' => $parent_department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function codeExists($code, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM departments WHERE UPPER(code) = UPPER(:code)";
        $params = ['code' => $code];
        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function divisionNameExists($parentId, $divisionName, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM departments
                WHERE parent_id = :parent_id AND LOWER(division_name) = LOWER(:division_name)";
        $params = ['parent_id' => (int) $parentId, 'division_name' => $divisionName];
        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function parentDepartmentNameExists($departmentName, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM departments
                WHERE parent_id IS NULL AND LOWER(department_name) = LOWER(:department_name)";
        $params = ['department_name' => $departmentName];
        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function createParent($departmentName, $divisionName, $code, $email)
    {
        $stmt = $this->db->prepare("
            INSERT INTO departments (parent_id, department_name, division_name, code, email)
            VALUES (NULL, :department_name, :division_name, :code, :email)
        ");
        $stmt->execute([
            'department_name' => $departmentName,
            'division_name' => $divisionName,
            'code' => $code,
            'email' => $email
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateParent($id, $departmentName, $divisionName, $code, $email)
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE departments
                SET department_name = :department_name, division_name = :division_name,
                    code = :code, email = :email
                WHERE id = :id AND parent_id IS NULL
            ");
            $stmt->execute([
                'id' => (int) $id,
                'department_name' => $departmentName,
                'division_name' => $divisionName,
                'code' => $code,
                'email' => $email
            ]);

            $children = $this->db->prepare("UPDATE departments SET department_name = :department_name WHERE parent_id = :id");
            $children->execute(['department_name' => $departmentName, 'id' => (int) $id]);
            $this->db->commit();
            return $stmt->rowCount() > 0 || $children->rowCount() > 0;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function createDivision($parentId, $divisionName, $code, $email)
    {
        $parent = $this->getDepartmentById((int) $parentId);
        if (!$parent || $parent['parent_id'] !== null) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO departments (parent_id, department_name, division_name, code, email)
            VALUES (:parent_id, :department_name, :division_name, :code, :email)
        ");
        return $stmt->execute([
            'parent_id' => (int) $parentId,
            'department_name' => $parent['department_name'],
            'division_name' => $divisionName,
            'code' => $code,
            'email' => $email
        ]);
    }

    public function updateDivision($id, $parentId, $divisionName, $code, $email)
    {
        $stmt = $this->db->prepare("
            UPDATE departments
            SET division_name = :division_name, code = :code, email = :email
            WHERE id = :id AND parent_id = :parent_id
        ");
        $stmt->execute([
            'id' => (int) $id,
            'parent_id' => (int) $parentId,
            'division_name' => $divisionName,
            'code' => $code,
            'email' => $email
        ]);
        return $stmt->rowCount() > 0;
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

    public function areChildDepartmentsOfParent($department_ids = [], $parent_department_id = null)
    {
        $department_ids = array_values(array_unique(array_filter(array_map('intval', $department_ids))));

        if (empty($department_ids)) {
            return true;
        }

        if ($parent_department_id === null || !$this->isParentDepartment((int) $parent_department_id)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($department_ids), '?'));
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM departments
            WHERE id IN ($placeholders)
            AND parent_id = ?
        ");

        $params = $department_ids;
        $params[] = (int) $parent_department_id;
        $stmt->execute($params);

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
