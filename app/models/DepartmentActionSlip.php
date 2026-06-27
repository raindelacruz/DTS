<?php

class DepartmentActionSlip
{
    private $db;

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_RELEASED = 'Released';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_DELEGATED = 'Delegated';
    public const STATUS_FOR_ACTION = 'For Action';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_RETURNED = 'Returned';
    public const STATUS_CANCELLED = 'Cancelled';

    public function __construct()
    {
        $this->db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

    }

    public static function statuses()
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_RELEASED,
            self::STATUS_RECEIVED,
            self::STATUS_DELEGATED,
            self::STATUS_FOR_ACTION,
            self::STATUS_COMPLETED,
            self::STATUS_RETURNED,
            self::STATUS_CANCELLED
        ];
    }

    public static function actionOptions()
    {
        return [
            'For initial/signature',
            'For appropriate action',
            'For meeting attendance',
            'For coordination',
            'For review/comments',
            'For reference/filing'
        ];
    }

    private function ensureSchema()
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS department_action_slip_sequences (
                id INT(11) NOT NULL AUTO_INCREMENT,
                department_id INT(11) NOT NULL,
                year INT(11) NOT NULL,
                month INT(11) NOT NULL,
                last_number INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY unique_das_sequence (department_id, year, month)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS department_action_slips (
                id INT(11) NOT NULL AUTO_INCREMENT,
                slip_number VARCHAR(100) NOT NULL,
                external_source VARCHAR(255) NOT NULL,
                date_received DATE NOT NULL,
                subject VARCHAR(255) NOT NULL,
                reference_number VARCHAR(120) DEFAULT NULL,
                receiving_level VARCHAR(20) NOT NULL DEFAULT 'Department',
                urgent TINYINT(1) NOT NULL DEFAULT 0,
                attachment VARCHAR(255) DEFAULT NULL,
                required_action TEXT NOT NULL,
                deadline DATE DEFAULT NULL,
                receiving_department_id INT(11) NOT NULL,
                receiving_division_id INT(11) DEFAULT NULL,
                current_department_id INT(11) NOT NULL,
                current_division_id INT(11) DEFAULT NULL,
                assigned_staff_id INT(11) DEFAULT NULL,
                remarks TEXT DEFAULT NULL,
                created_by INT(11) NOT NULL,
                status VARCHAR(80) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                completed_by INT(11) DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL,
                closed_by INT(11) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_department_action_slips_number (slip_number),
                KEY idx_das_receiving_department (receiving_department_id),
                KEY idx_das_receiving_division (receiving_division_id),
                KEY idx_das_current_department (current_department_id),
                KEY idx_das_current_division (current_division_id),
                KEY idx_das_assigned_staff (assigned_staff_id),
                KEY idx_das_status (status),
                KEY idx_das_date_received (date_received),
                KEY idx_das_deadline (deadline)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS department_action_slip_events (
                id INT(11) NOT NULL AUTO_INCREMENT,
                slip_id INT(11) NOT NULL,
                action VARCHAR(120) NOT NULL,
                actor_user_id INT(11) NOT NULL,
                actor_department_id INT(11) NOT NULL,
                from_department_id INT(11) DEFAULT NULL,
                to_department_id INT(11) DEFAULT NULL,
                from_user_id INT(11) DEFAULT NULL,
                to_user_id INT(11) DEFAULT NULL,
                old_status VARCHAR(80) DEFAULT NULL,
                new_status VARCHAR(80) DEFAULT NULL,
                remarks TEXT DEFAULT NULL,
                attachment VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_das_events_slip (slip_id),
                KEY idx_das_events_actor (actor_user_id),
                KEY idx_das_events_to_department (to_department_id),
                KEY idx_das_events_to_user (to_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureColumn('department_action_slips', 'receiving_level', "ALTER TABLE department_action_slips ADD COLUMN receiving_level VARCHAR(20) NOT NULL DEFAULT 'Department' AFTER reference_number");
        $this->ensureColumn('department_action_slips', 'urgent', "ALTER TABLE department_action_slips ADD COLUMN urgent TINYINT(1) NOT NULL DEFAULT 0 AFTER receiving_level");
        $this->ensureColumn('department_action_slips', 'receiving_division_id', "ALTER TABLE department_action_slips ADD COLUMN receiving_division_id INT(11) DEFAULT NULL AFTER receiving_department_id");
        $this->ensureColumn('department_action_slips', 'current_department_id', "ALTER TABLE department_action_slips ADD COLUMN current_department_id INT(11) DEFAULT NULL AFTER receiving_division_id");
        $this->ensureColumn('department_action_slips', 'current_division_id', "ALTER TABLE department_action_slips ADD COLUMN current_division_id INT(11) DEFAULT NULL AFTER current_department_id");
        $this->ensureColumn('department_action_slips', 'remarks', "ALTER TABLE department_action_slips ADD COLUMN remarks TEXT DEFAULT NULL AFTER assigned_staff_id");
        $this->ensureColumn('department_action_slips', 'closed_at', "ALTER TABLE department_action_slips ADD COLUMN closed_at DATETIME DEFAULT NULL AFTER completed_by");
        $this->ensureColumn('department_action_slips', 'closed_by', "ALTER TABLE department_action_slips ADD COLUMN closed_by INT(11) DEFAULT NULL AFTER closed_at");

        $this->db->exec("
            UPDATE department_action_slips
            SET current_department_id = receiving_department_id
            WHERE current_department_id IS NULL OR current_department_id = 0
        ");

        $this->db->exec("
            UPDATE department_action_slips
            SET current_division_id = receiving_division_id
            WHERE receiving_level IN ('Division', 'Staff')
            AND receiving_division_id IS NOT NULL
            AND (current_division_id IS NULL OR current_division_id = 0)
        ");

        $this->db->exec("
            UPDATE department_action_slips
            SET status = CASE
                WHEN status IN ('Created') THEN 'Draft'
                WHEN status IN ('Routed to Department', 'Reassigned') THEN 'Released'
                WHEN status IN ('Received by Department', 'Received by Receiving Department', 'Received by Division') THEN 'Received'
                WHEN status IN ('Delegated to Division', 'Delegated to Staff') THEN 'Delegated'
                WHEN status IN ('For Staff Action') THEN 'For Action'
                WHEN status IN ('Staff Completed', 'Division Confirmed', 'Department Completed', 'Closed') THEN 'Completed'
                ELSE status
            END
            WHERE status NOT IN ('Draft', 'Released', 'Received', 'Delegated', 'For Action', 'Completed', 'Returned', 'Cancelled')
        ");
    }

    private function ensureColumn($table, $column, $sql)
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->exec($sql);
        }
    }

    private function columnExists($table, $column)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :schema_name
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            'schema_name' => DB_NAME,
            'table_name' => $table,
            'column_name' => $column
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getNextSlipNumber($departmentId)
    {
        $departmentId = (int) $departmentId;
        $prefix = $this->getSlipNumberDepartmentPrefix($departmentId);
        $year = (int) date('Y');
        $month = (int) date('m');

        $stmt = $this->db->prepare("
            SELECT last_number
            FROM department_action_slip_sequences
            WHERE department_id = :department_id
            AND year = :year
            AND month = :month
            LIMIT 1
        ");
        $stmt->execute([
            'department_id' => $departmentId,
            'year' => $year,
            'month' => $month
        ]);

        $next = ((int) $stmt->fetchColumn()) + 1;
        return sprintf('DAS-%s-%04d-%02d-%04d', $prefix ?: $departmentId, $year, $month, $next);
    }

    private function reserveSlipNumber($departmentId)
    {
        $departmentId = (int) $departmentId;
        $year = (int) date('Y');
        $month = (int) date('m');

        $stmt = $this->db->prepare("
            SELECT id, last_number
            FROM department_action_slip_sequences
            WHERE department_id = :department_id
            AND year = :year
            AND month = :month
            FOR UPDATE
        ");
        $stmt->execute([
            'department_id' => $departmentId,
            'year' => $year,
            'month' => $month
        ]);
        $sequence = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sequence) {
            $next = (int) $sequence['last_number'] + 1;
            $update = $this->db->prepare("UPDATE department_action_slip_sequences SET last_number = :last_number WHERE id = :id");
            $update->execute(['last_number' => $next, 'id' => (int) $sequence['id']]);
        } else {
            $next = 1;
            $insert = $this->db->prepare("
                INSERT INTO department_action_slip_sequences (department_id, year, month, last_number)
                VALUES (:department_id, :year, :month, :last_number)
            ");
            $insert->execute([
                'department_id' => $departmentId,
                'year' => $year,
                'month' => $month,
                'last_number' => $next
            ]);
        }

        $prefix = $this->getSlipNumberDepartmentPrefix($departmentId);
        return sprintf('DAS-%s-%04d-%02d-%04d', $prefix ?: $departmentId, $year, $month, $next);
    }

    private function getDepartmentCode($departmentId)
    {
        $stmt = $this->db->prepare("SELECT code FROM departments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int) $departmentId]);
        return (string) $stmt->fetchColumn();
    }

    private function getSlipNumberDepartmentPrefix($departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT child.code, parent.code AS parent_code
            FROM departments child
            LEFT JOIN departments parent ON parent.id = child.parent_id
            WHERE child.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => (int) $departmentId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$department) {
            return '';
        }

        $code = trim((string) ($department['code'] ?? ''));
        $parentCode = trim((string) ($department['parent_code'] ?? ''));
        if ($parentCode === '') {
            return $code;
        }

        $divisionCode = $code;
        $parentPrefix = $parentCode . '-';
        if (stripos($divisionCode, $parentPrefix) === 0) {
            $divisionCode = substr($divisionCode, strlen($parentPrefix));
        }

        return $divisionCode !== '' ? $parentCode . '-' . $divisionCode : $parentCode;
    }

    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            $originDepartmentId = (int) ($data['actor_department_id'] ?? $data['receiving_department_id']);
            $receivingDepartmentId = (int) $data['receiving_department_id'];
            $receivingDivisionId = !empty($data['receiving_division_id']) ? (int) $data['receiving_division_id'] : null;
            $currentDivisionId = in_array($data['receiving_level'], ['Division', 'Staff'], true) ? $receivingDivisionId : null;
            $assignedStaffId = !empty($data['assigned_staff_id']) ? (int) $data['assigned_staff_id'] : null;
            $slipNumber = $this->reserveSlipNumber($originDepartmentId);
            $releaseAction = $data['release_action'] ?? 'Released';

            $stmt = $this->db->prepare("
                INSERT INTO department_action_slips (
                    slip_number,
                    external_source,
                    date_received,
                    subject,
                    reference_number,
                    receiving_level,
                    urgent,
                    attachment,
                    required_action,
                    deadline,
                    receiving_department_id,
                    receiving_division_id,
                    current_department_id,
                    current_division_id,
                    assigned_staff_id,
                    remarks,
                    created_by,
                    status
                ) VALUES (
                    :slip_number,
                    :external_source,
                    :date_received,
                    :subject,
                    :reference_number,
                    :receiving_level,
                    :urgent,
                    :attachment,
                    :required_action,
                    :deadline,
                    :receiving_department_id,
                    :receiving_division_id,
                    :current_department_id,
                    :current_division_id,
                    :assigned_staff_id,
                    :remarks,
                    :created_by,
                    :status
                )
            ");

            $stmt->execute([
                'slip_number' => $slipNumber,
                'external_source' => $data['external_source'] ?? 'Internal',
                'date_received' => $data['date_received'],
                'subject' => $data['subject'] ?? $data['required_action'],
                'reference_number' => $data['reference_number'] ?: null,
                'receiving_level' => $data['receiving_level'],
                'urgent' => !empty($data['urgent']) ? 1 : 0,
                'attachment' => $data['attachment'] ?: null,
                'required_action' => $data['required_action'],
                'deadline' => $data['deadline'] ?: null,
                'receiving_department_id' => $receivingDepartmentId,
                'receiving_division_id' => $receivingDivisionId,
                'current_department_id' => $receivingDepartmentId,
                'current_division_id' => $currentDivisionId,
                'assigned_staff_id' => $assignedStaffId,
                'remarks' => $data['remarks'] ?: null,
                'created_by' => (int) $data['created_by'],
                'status' => self::STATUS_RELEASED
            ]);

            $slipId = (int) $this->db->lastInsertId();
            $this->logEvent($slipId, [
                'action' => 'Created',
                'actor_user_id' => (int) $data['created_by'],
                'actor_department_id' => $originDepartmentId,
                'to_department_id' => $receivingDepartmentId,
                'to_user_id' => $assignedStaffId,
                'new_status' => self::STATUS_DRAFT,
                'remarks' => 'Action slip created.'
            ]);
            $this->logEvent($slipId, [
                'action' => $releaseAction,
                'actor_user_id' => (int) $data['created_by'],
                'actor_department_id' => $originDepartmentId,
                'from_department_id' => $originDepartmentId,
                'to_department_id' => $currentDivisionId ?: $receivingDepartmentId,
                'to_user_id' => $assignedStaffId,
                'old_status' => self::STATUS_DRAFT,
                'new_status' => self::STATUS_RELEASED,
                'remarks' => $data['remarks'] ?: null
            ]);

            $this->db->commit();
            return $slipId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function createDraft($data)
    {
        try {
            $this->db->beginTransaction();

            $actorDepartmentId = (int) $data['actor_department_id'];
            $receivingDepartmentId = (int) ($data['receiving_department_id'] ?? $actorDepartmentId);
            $receivingDivisionId = !empty($data['receiving_division_id']) ? (int) $data['receiving_division_id'] : null;
            $slipNumber = $this->reserveSlipNumber($actorDepartmentId);

            $stmt = $this->db->prepare("
                INSERT INTO department_action_slips (
                    slip_number,
                    external_source,
                    date_received,
                    subject,
                    reference_number,
                    receiving_level,
                    urgent,
                    attachment,
                    required_action,
                    deadline,
                    receiving_department_id,
                    receiving_division_id,
                    current_department_id,
                    current_division_id,
                    assigned_staff_id,
                    remarks,
                    created_by,
                    status
                ) VALUES (
                    :slip_number,
                    :external_source,
                    :date_received,
                    :subject,
                    :reference_number,
                    :receiving_level,
                    :urgent,
                    :attachment,
                    :required_action,
                    :deadline,
                    :receiving_department_id,
                    :receiving_division_id,
                    :current_department_id,
                    :current_division_id,
                    :assigned_staff_id,
                    :remarks,
                    :created_by,
                    :status
                )
            ");

            $stmt->execute([
                'slip_number' => $slipNumber,
                'external_source' => 'Staff Draft',
                'date_received' => $data['date_received'],
                'subject' => 'Draft action slip',
                'reference_number' => null,
                'receiving_level' => 'Department',
                'urgent' => 0,
                'attachment' => $data['attachment'] ?: null,
                'required_action' => 'Pending manager action',
                'deadline' => null,
                'receiving_department_id' => $receivingDepartmentId,
                'receiving_division_id' => $receivingDivisionId,
                'current_department_id' => $receivingDepartmentId,
                'current_division_id' => $receivingDivisionId,
                'assigned_staff_id' => null,
                'remarks' => null,
                'created_by' => (int) $data['created_by'],
                'status' => self::STATUS_DRAFT
            ]);

            $slipId = (int) $this->db->lastInsertId();
            $this->logEvent($slipId, [
                'action' => 'Draft Created',
                'actor_user_id' => (int) $data['created_by'],
                'actor_department_id' => $actorDepartmentId,
                'to_department_id' => $receivingDivisionId ?: $receivingDepartmentId,
                'new_status' => self::STATUS_DRAFT,
                'remarks' => 'Draft action slip created by staff.'
            ]);

            $this->db->commit();
            return $slipId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getVisible($userId, $departmentId, $role, $filters = [])
    {
        $params = [];

        $where = [];
        if ($role === 'staff') {
            $params['user_id'] = (int) $userId;
            $where[] = "(
                das.assigned_staff_id = :user_id
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_events e
                    WHERE e.slip_id = das.id
                    AND (e.actor_user_id = :user_id OR e.to_user_id = :user_id)
                )
            )";
        } elseif ($role !== 'admin') {
            $params['user_id'] = (int) $userId;
            $params['department_id'] = (int) $departmentId;
            $where[] = "(
                das.receiving_department_id = :department_id
                OR das.receiving_division_id = :department_id
                OR das.current_department_id = :department_id
                OR das.current_division_id = :department_id
                OR das.assigned_staff_id = :user_id
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_events e
                    WHERE e.slip_id = das.id
                    AND (
                        e.actor_department_id = :department_id
                        OR e.from_department_id = :department_id
                        OR e.to_department_id = :department_id
                        OR e.to_user_id = :user_id
                    )
                )
            )";
        }

        $this->applyFilters($where, $params, $filters);
        $sql = $this->baseSelect() . $this->buildWhere($where) . "
            ORDER BY
                CASE WHEN das.status = '" . self::STATUS_COMPLETED . "' THEN 1 ELSE 0 END,
                das.updated_at DESC,
                das.id DESC
        ";
        if (isset($filters['_limit'])) {
            $limit = max(1, min(100, (int) $filters['_limit']));
            $offset = max(0, (int) ($filters['_offset'] ?? 0));
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countVisible($userId, $departmentId, $role, $filters = [])
    {
        $params = [];
        $where = [];
        if ($role === 'staff') {
            $params['user_id'] = (int) $userId;
            $where[] = "(das.assigned_staff_id = :user_id OR EXISTS (SELECT 1 FROM department_action_slip_events e WHERE e.slip_id = das.id AND (e.actor_user_id = :user_id OR e.to_user_id = :user_id)))";
        } elseif ($role !== 'admin') {
            $params['user_id'] = (int) $userId;
            $params['department_id'] = (int) $departmentId;
            $where[] = "(das.receiving_department_id = :department_id OR das.receiving_division_id = :department_id OR das.current_department_id = :department_id OR das.current_division_id = :department_id OR das.assigned_staff_id = :user_id OR EXISTS (SELECT 1 FROM department_action_slip_events e WHERE e.slip_id = das.id AND (e.actor_department_id = :department_id OR e.from_department_id = :department_id OR e.to_department_id = :department_id OR e.to_user_id = :user_id)))";
        }
        $this->applyFilters($where, $params, $filters);
        $stmt = $this->db->prepare('SELECT COUNT(DISTINCT das.id) FROM department_action_slips das' . $this->buildWhere($where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countByVisibleStatus($userId, $departmentId, $role)
    {
        $rows = $this->getVisible($userId, $departmentId, $role);
        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        return $counts;
    }

    private function baseSelect()
    {
        return "
            SELECT
                das.*,
                receiving.division_name AS receiving_department_name,
                receiving_division.division_name AS receiving_division_name,
                current_dept.division_name AS current_department_name,
                current_division.division_name AS current_division_name,
                CONCAT(creator.firstname, ' ', IFNULL(CONCAT(creator.middle_initial, '. '), ''), creator.lastname) AS created_by_name,
                CONCAT(staff.firstname, ' ', IFNULL(CONCAT(staff.middle_initial, '. '), ''), staff.lastname) AS assigned_staff_name
            FROM department_action_slips das
            JOIN departments receiving ON receiving.id = das.receiving_department_id
            LEFT JOIN departments receiving_division ON receiving_division.id = das.receiving_division_id
            LEFT JOIN departments current_dept ON current_dept.id = das.current_department_id
            LEFT JOIN departments current_division ON current_division.id = das.current_division_id
            JOIN users creator ON creator.id = das.created_by
            LEFT JOIN users staff ON staff.id = das.assigned_staff_id
        ";
    }

    private function applyFilters(&$where, &$params, $filters)
    {
        if (trim($filters['keyword'] ?? '') !== '') {
            $where[] = "(das.slip_number LIKE :keyword OR das.remarks LIKE :keyword)";
            $params['keyword'] = '%' . trim($filters['keyword']) . '%';
        }

        if (trim($filters['status'] ?? '') !== '') {
            $where[] = "das.status = :status";
            $params['status'] = trim($filters['status']);
        }

        if ((int) ($filters['department_id'] ?? 0) > 0) {
            $where[] = "(das.receiving_department_id = :filter_department_id OR das.current_department_id = :filter_department_id)";
            $params['filter_department_id'] = (int) $filters['department_id'];
        }

        if ((int) ($filters['division_id'] ?? 0) > 0) {
            $where[] = "(das.receiving_division_id = :filter_division_id OR das.current_division_id = :filter_division_id)";
            $params['filter_division_id'] = (int) $filters['division_id'];
        }

        if ((int) ($filters['assigned_staff_id'] ?? 0) > 0) {
            $where[] = "das.assigned_staff_id = :filter_assigned_staff_id";
            $params['filter_assigned_staff_id'] = (int) $filters['assigned_staff_id'];
        }
    }

    private function buildWhere($where)
    {
        return !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    public function findById($id)
    {
        $stmt = $this->db->prepare($this->baseSelect() . " WHERE das.id = :id LIMIT 1");
        $stmt->execute(['id' => (int) $id]);
        $slip = $stmt->fetch(PDO::FETCH_ASSOC);
        return $slip ?: null;
    }

    public function canUserView($slipId, $userId, $departmentId, $role)
    {
        if ($role === 'admin') {
            return true;
        }

        if ($role === 'staff') {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM department_action_slips das
                WHERE das.id = :slip_id
                AND (
                    das.assigned_staff_id = :assigned_user_id
                    OR EXISTS (
                        SELECT 1
                        FROM department_action_slip_events e
                        WHERE e.slip_id = das.id
                        AND (e.actor_user_id = :actor_user_id OR e.to_user_id = :event_to_user_id)
                    )
                )
            ");
            $stmt->execute([
                'slip_id' => (int) $slipId,
                'assigned_user_id' => (int) $userId,
                'actor_user_id' => (int) $userId,
                'event_to_user_id' => (int) $userId
            ]);
            return (int) $stmt->fetchColumn() > 0;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slips das
            WHERE das.id = :slip_id
            AND (
                das.receiving_department_id = :receiving_department_id
                OR das.receiving_division_id = :receiving_division_id
                OR das.current_department_id = :current_department_id
                OR das.current_division_id = :current_division_id
                OR das.assigned_staff_id = :assigned_user_id
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_events e
                    WHERE e.slip_id = das.id
                    AND (
                        e.actor_department_id = :event_actor_department_id
                        OR e.from_department_id = :event_from_department_id
                        OR e.to_department_id = :event_to_department_id
                        OR e.to_user_id = :event_to_user_id
                    )
                )
            )
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'receiving_department_id' => (int) $departmentId,
            'receiving_division_id' => (int) $departmentId,
            'current_department_id' => (int) $departmentId,
            'current_division_id' => (int) $departmentId,
            'assigned_user_id' => (int) $userId,
            'event_actor_department_id' => (int) $departmentId,
            'event_from_department_id' => (int) $departmentId,
            'event_to_department_id' => (int) $departmentId,
            'event_to_user_id' => (int) $userId
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getEvents($slipId)
    {
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                CONCAT(actor.firstname, ' ', IFNULL(CONCAT(actor.middle_initial, '. '), ''), actor.lastname) AS actor_name,
                actor_dept.division_name AS actor_department_name,
                from_dept.division_name AS from_department_name,
                to_dept.division_name AS to_department_name,
                CONCAT(from_user.firstname, ' ', IFNULL(CONCAT(from_user.middle_initial, '. '), ''), from_user.lastname) AS from_user_name,
                CONCAT(to_user.firstname, ' ', IFNULL(CONCAT(to_user.middle_initial, '. '), ''), to_user.lastname) AS to_user_name
            FROM department_action_slip_events e
            JOIN users actor ON actor.id = e.actor_user_id
            JOIN departments actor_dept ON actor_dept.id = e.actor_department_id
            LEFT JOIN departments from_dept ON from_dept.id = e.from_department_id
            LEFT JOIN departments to_dept ON to_dept.id = e.to_department_id
            LEFT JOIN users from_user ON from_user.id = e.from_user_id
            LEFT JOIN users to_user ON to_user.id = e.to_user_id
            WHERE e.slip_id = :slip_id
            ORDER BY e.created_at DESC, e.id DESC
        ");
        $stmt->execute(['slip_id' => (int) $slipId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEventById($eventId)
    {
        $stmt = $this->db->prepare("SELECT * FROM department_action_slip_events WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int) $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        return $event ?: null;
    }

    public function getActiveManagersByDepartment($departmentId)
    {
        return $this->getActiveUsersByDepartmentAndRole($departmentId, 'manager');
    }

    public function getActiveStaffByDepartment($departmentId)
    {
        return $this->getActiveUsersByDepartmentAndRole($departmentId, 'staff');
    }

    private function getActiveUsersByDepartmentAndRole($departmentId, $role)
    {
        $stmt = $this->db->prepare("
            SELECT id, firstname, middle_initial, lastname, email, role, department_id
            FROM users
            WHERE department_id = :department_id
            AND role = :role
            AND status = 'active'
            ORDER BY lastname ASC, firstname ASC
        ");
        $stmt->execute(['department_id' => (int) $departmentId, 'role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveUserInDepartment($userId, $departmentId, $role = null)
    {
        $sql = "
            SELECT id, firstname, middle_initial, lastname, email, role, department_id
            FROM users
            WHERE id = :user_id
            AND department_id = :department_id
            AND status = 'active'
        ";
        $params = ['user_id' => (int) $userId, 'department_id' => (int) $departmentId];

        if ($role !== null) {
            $sql .= " AND role = :role";
            $params['role'] = $role;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public function receiveByDepartment($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $this->transition($slipId, self::STATUS_RECEIVED, 'Received by Department', $actorUserId, $actorDepartmentId, $remarks);
    }

    public function releaseDraft($slipId, $data)
    {
        $slip = $this->requireSlip($slipId);
        $receivingDepartmentId = (int) $data['receiving_department_id'];
        $receivingDivisionId = !empty($data['receiving_division_id']) ? (int) $data['receiving_division_id'] : null;
        $currentDivisionId = in_array($data['receiving_level'], ['Division', 'Staff'], true) ? $receivingDivisionId : null;
        $assignedStaffId = !empty($data['assigned_staff_id']) ? (int) $data['assigned_staff_id'] : null;
        $releaseAction = $data['release_action'] ?? 'Released';

        $this->updateSlipAndLog($slipId, [
            'external_source' => $data['external_source'] ?? 'Internal Action Slip',
            'subject' => $data['subject'] ?? $data['required_action'],
            'reference_number' => $data['reference_number'] ?: null,
            'date_received' => $data['date_received'],
            'receiving_level' => $data['receiving_level'],
            'urgent' => !empty($data['urgent']) ? 1 : 0,
            'required_action' => $data['required_action'],
            'deadline' => $data['deadline'] ?: null,
            'receiving_department_id' => $receivingDepartmentId,
            'receiving_division_id' => $receivingDivisionId,
            'current_department_id' => $receivingDepartmentId,
            'current_division_id' => $currentDivisionId,
            'assigned_staff_id' => $assignedStaffId,
            'remarks' => $data['remarks'] ?: null,
            'status' => self::STATUS_RELEASED,
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => $releaseAction,
            'actor_user_id' => (int) $data['actor_user_id'],
            'actor_department_id' => (int) $data['actor_department_id'],
            'from_department_id' => (int) $data['actor_department_id'],
            'to_department_id' => $currentDivisionId ?: $receivingDepartmentId,
            'to_user_id' => $assignedStaffId,
            'old_status' => $slip['status'],
            'new_status' => self::STATUS_RELEASED,
            'remarks' => $data['remarks'] ?: null
        ]);
    }

    public function cancelDraft($slipId, $actorUserId, $actorDepartmentId)
    {
        $slip = $this->requireSlip($slipId);
        if (($slip['status'] ?? '') !== self::STATUS_DRAFT) {
            throw new RuntimeException('Only draft action slips can be cancelled.');
        }

        $this->transition(
            $slipId,
            self::STATUS_CANCELLED,
            'Draft Cancelled',
            $actorUserId,
            $actorDepartmentId,
            'Draft action slip cancelled by manager.'
        );
    }

    public function routeToDepartment($slipId, $targetDepartmentId, $actorUserId, $actorDepartmentId, $remarks = '', $status = self::STATUS_RELEASED, $action = 'Released to Department')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];

        $this->updateSlipAndLog($slipId, [
            'current_department_id' => (int) $targetDepartmentId,
            'current_division_id' => null,
            'assigned_staff_id' => null,
            'status' => $status,
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $targetDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'remarks' => $remarks
        ]);
    }

    public function delegateToDivision($slipId, $divisionId, $actorUserId, $actorDepartmentId, $remarks = '', $status = self::STATUS_DELEGATED, $action = 'Delegated to Division')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];

        $this->updateSlipAndLog($slipId, [
            'current_department_id' => (int) $actorDepartmentId,
            'current_division_id' => (int) $divisionId,
            'assigned_staff_id' => null,
            'status' => $status,
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $divisionId,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'remarks' => $remarks
        ]);
    }

    public function receiveByDivision($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $this->transition($slipId, self::STATUS_RECEIVED, 'Received by Division', $actorUserId, $actorDepartmentId, $remarks);
    }

    public function delegateToStaff($slipId, $staffUserId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];

        $this->updateSlipAndLog($slipId, [
            'assigned_staff_id' => (int) $staffUserId,
            'status' => self::STATUS_DELEGATED
        ], [
            'action' => 'Further Delegated to Staff',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $actorDepartmentId,
            'to_user_id' => $staffUserId,
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_DELEGATED,
            'remarks' => $remarks
        ]);
    }

    public function startStaffAction($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $this->transition($slipId, self::STATUS_FOR_ACTION, 'Received by Staff', $actorUserId, $actorDepartmentId, $remarks);
    }

    public function completeByStaff($slipId, $actorUserId, $actorDepartmentId, $remarks = '', $attachment = null)
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $action = $oldStatus === self::STATUS_RETURNED ? 'Resubmitted by Staff' : 'Completed by Staff';

        $this->updateSlipAndLog($slipId, [
            'status' => self::STATUS_COMPLETED,
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => (int) $actorUserId
        ], [
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_COMPLETED,
            'remarks' => $remarks,
            'attachment' => $attachment
        ]);
    }

    public function returnStaffCompletion($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $staffUserId = (int) ($slip['assigned_staff_id'] ?? 0);

        $this->updateSlipAndLog($slipId, [
            'status' => self::STATUS_RETURNED,
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => 'Returned by Division Manager',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $actorDepartmentId,
            'to_user_id' => $staffUserId,
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_RETURNED,
            'remarks' => $remarks
        ]);

        return $staffUserId;
    }

    public function confirmByDivisionManager($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];

        $this->updateSlipAndLog($slipId, [
            'current_division_id' => null,
            'assigned_staff_id' => null,
            'status' => self::STATUS_COMPLETED,
            'completed_at' => $slip['completed_at'] ?: date('Y-m-d H:i:s'),
            'completed_by' => !empty($slip['completed_by']) ? (int) $slip['completed_by'] : (int) $actorUserId
        ], [
            'action' => 'Completed by Division',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_COMPLETED,
            'remarks' => $remarks
        ]);
    }

    public function completeByDepartmentManager($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];

        $this->updateSlipAndLog($slipId, [
            'status' => self::STATUS_COMPLETED,
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => (int) $actorUserId
        ], [
            'action' => 'Completed by Department',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_COMPLETED,
            'remarks' => $remarks
        ]);
    }

    public function returnSlip($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $returnTargetDepartmentId = $this->getReturnTargetDepartmentId($slipId, $actorDepartmentId);
        $isDivisionReturn = (int) ($slip['current_division_id'] ?? 0) === (int) $actorDepartmentId;

        $this->updateSlipAndLog($slipId, [
            'current_department_id' => $returnTargetDepartmentId,
            'current_division_id' => null,
            'assigned_staff_id' => null,
            'status' => self::STATUS_RETURNED,
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => 'Returned',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $returnTargetDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_RETURNED,
            'remarks' => $remarks
        ]);

        return $isDivisionReturn ? (int) ($slip['current_department_id'] ?: $returnTargetDepartmentId) : $returnTargetDepartmentId;
    }

    public function closeSlip($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];

        $this->updateSlipAndLog($slipId, [
            'status' => self::STATUS_COMPLETED,
            'closed_at' => date('Y-m-d H:i:s'),
            'closed_by' => (int) $actorUserId
        ], [
            'action' => 'Completed',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => self::STATUS_COMPLETED,
            'remarks' => $remarks
        ]);
    }

    private function transition($slipId, $newStatus, $action, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);

        $this->updateSlipAndLog($slipId, ['status' => $newStatus], [
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $slip['status'],
            'new_status' => $newStatus,
            'remarks' => $remarks
        ]);
    }

    private function getReturnTargetDepartmentId($slipId, $actorDepartmentId)
    {
        $stmt = $this->db->prepare("
            SELECT from_department_id
            FROM department_action_slip_events
            WHERE slip_id = :slip_id
            AND to_department_id = :target_actor_department_id
            AND from_department_id IS NOT NULL
            AND from_department_id <> :excluded_actor_department_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'target_actor_department_id' => (int) $actorDepartmentId,
            'excluded_actor_department_id' => (int) $actorDepartmentId
        ]);

        $targetDepartmentId = (int) $stmt->fetchColumn();
        if ($targetDepartmentId > 0) {
            return $targetDepartmentId;
        }

        $slip = $this->requireSlip($slipId);
        return (int) ($slip['current_department_id'] ?: $slip['receiving_department_id']);
    }

    private function updateSlipAndLog($slipId, $fields, $event)
    {
        try {
            $this->db->beginTransaction();

            $sets = [];
            $params = ['id' => (int) $slipId];
            foreach ($fields as $field => $value) {
                $sets[] = $field . ' = :' . $field;
                $params[$field] = $value;
            }

            if (!empty($sets)) {
                $stmt = $this->db->prepare("UPDATE department_action_slips SET " . implode(', ', $sets) . " WHERE id = :id");
                $stmt->execute($params);
            }

            $this->logEvent($slipId, $event);
            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function logEvent($slipId, $event)
    {
        $stmt = $this->db->prepare("
            INSERT INTO department_action_slip_events (
                slip_id,
                action,
                actor_user_id,
                actor_department_id,
                from_department_id,
                to_department_id,
                from_user_id,
                to_user_id,
                old_status,
                new_status,
                remarks,
                attachment
            ) VALUES (
                :slip_id,
                :action,
                :actor_user_id,
                :actor_department_id,
                :from_department_id,
                :to_department_id,
                :from_user_id,
                :to_user_id,
                :old_status,
                :new_status,
                :remarks,
                :attachment
            )
        ");

        $stmt->execute([
            'slip_id' => (int) $slipId,
            'action' => $event['action'],
            'actor_user_id' => (int) $event['actor_user_id'],
            'actor_department_id' => (int) $event['actor_department_id'],
            'from_department_id' => !empty($event['from_department_id']) ? (int) $event['from_department_id'] : null,
            'to_department_id' => !empty($event['to_department_id']) ? (int) $event['to_department_id'] : null,
            'from_user_id' => !empty($event['from_user_id']) ? (int) $event['from_user_id'] : null,
            'to_user_id' => !empty($event['to_user_id']) ? (int) $event['to_user_id'] : null,
            'old_status' => $event['old_status'] ?? null,
            'new_status' => $event['new_status'] ?? null,
            'remarks' => trim((string) ($event['remarks'] ?? '')) !== '' ? trim((string) $event['remarks']) : null,
            'attachment' => !empty($event['attachment']) ? $event['attachment'] : null
        ]);
    }

    private function requireSlip($slipId)
    {
        $slip = $this->findById((int) $slipId);
        if (!$slip) {
            throw new RuntimeException('Action slip not found.');
        }
        return $slip;
    }
}
