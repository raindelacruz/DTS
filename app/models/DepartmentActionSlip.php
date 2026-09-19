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
    private const ASSIGNMENT_CONFIRMED = 'Confirmed';

    public function __construct()
    {
        $this->db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->ensureSchema();
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
                title VARCHAR(255) DEFAULT NULL,
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
                parent_slip_id INT(11) DEFAULT NULL,
                current_department_id INT(11) NOT NULL,
                current_division_id INT(11) DEFAULT NULL,
                current_unit_id INT(11) DEFAULT NULL,
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
                KEY idx_das_parent_slip (parent_slip_id),
                KEY idx_das_current_department (current_department_id),
                KEY idx_das_current_division (current_division_id),
                KEY idx_das_current_unit (current_unit_id),
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

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS department_action_slip_assignments (
                id INT(11) NOT NULL AUTO_INCREMENT,
                slip_id INT(11) NOT NULL,
                assignment_type VARCHAR(20) NOT NULL,
                department_id INT(11) DEFAULT NULL,
                user_id INT(11) DEFAULT NULL,
                assigned_by INT(11) NOT NULL,
                assigned_by_department_id INT(11) NOT NULL,
                status VARCHAR(80) NOT NULL,
                remarks TEXT DEFAULT NULL,
                assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                received_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                completed_by INT(11) DEFAULT NULL,
                completion_attachment VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_das_assignments_slip (slip_id),
                KEY idx_das_assignments_department (department_id),
                KEY idx_das_assignments_user (user_id),
                KEY idx_das_assignments_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureColumn('department_action_slips', 'title', "ALTER TABLE department_action_slips ADD COLUMN title VARCHAR(255) DEFAULT NULL AFTER slip_number");
        $this->db->exec("
            UPDATE department_action_slips
            SET title = slip_number
            WHERE title IS NULL OR TRIM(title) = ''
        ");
        $this->ensureColumn('department_action_slips', 'receiving_level', "ALTER TABLE department_action_slips ADD COLUMN receiving_level VARCHAR(20) NOT NULL DEFAULT 'Department' AFTER reference_number");
        $this->ensureColumn('department_action_slips', 'urgent', "ALTER TABLE department_action_slips ADD COLUMN urgent TINYINT(1) NOT NULL DEFAULT 0 AFTER receiving_level");
        $this->ensureColumn('department_action_slips', 'receiving_division_id', "ALTER TABLE department_action_slips ADD COLUMN receiving_division_id INT(11) DEFAULT NULL AFTER receiving_department_id");
        $this->ensureColumn('department_action_slips', 'parent_slip_id', "ALTER TABLE department_action_slips ADD COLUMN parent_slip_id INT(11) DEFAULT NULL AFTER receiving_division_id");
        $this->ensureColumn('department_action_slips', 'current_department_id', "ALTER TABLE department_action_slips ADD COLUMN current_department_id INT(11) DEFAULT NULL AFTER receiving_division_id");
        $this->ensureColumn('department_action_slips', 'current_division_id', "ALTER TABLE department_action_slips ADD COLUMN current_division_id INT(11) DEFAULT NULL AFTER current_department_id");
        $this->ensureColumn('department_action_slips', 'current_unit_id', "ALTER TABLE department_action_slips ADD COLUMN current_unit_id INT(11) DEFAULT NULL AFTER current_division_id");
        $this->ensureIndex('department_action_slips', 'idx_das_parent_slip', "ALTER TABLE department_action_slips ADD KEY idx_das_parent_slip (parent_slip_id)");
        $this->ensureIndex('department_action_slips', 'idx_das_current_unit', "ALTER TABLE department_action_slips ADD KEY idx_das_current_unit (current_unit_id)");
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

    private function ensureIndex($table, $index, $sql)
    {
        if (!$this->indexExists($table, $index)) {
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

    private function indexExists($table, $index)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :schema_name
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name
        ");
        $stmt->execute([
            'schema_name' => DB_NAME,
            'table_name' => $table,
            'index_name' => $index
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

        $childSegments = array_values(array_filter(explode('-', $code), 'strlen'));
        $parentSegments = array_values(array_filter(explode('-', $parentCode), 'strlen'));

        if (!empty($childSegments) && !empty($parentSegments)) {
            $parentPrefix = $parentCode . '-';
            if (stripos($code, $parentPrefix) === 0) {
                $childSegments = array_values(array_filter(explode('-', substr($code, strlen($parentPrefix))), 'strlen'));
            } elseif (strcasecmp($childSegments[0], $parentSegments[count($parentSegments) - 1]) === 0) {
                array_shift($childSegments);
            }
        }

        $suffix = implode('-', $childSegments);
        return $suffix !== '' ? $parentCode . '-' . $suffix : $parentCode;
    }

    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            $originDepartmentId = (int) ($data['actor_department_id'] ?? $data['receiving_department_id']);
            $receivingDepartmentId = (int) $data['receiving_department_id'];
            $receivingDivisionId = !empty($data['receiving_division_id']) ? (int) $data['receiving_division_id'] : null;
            $currentDivisionId = in_array($data['receiving_level'], ['Division', 'Staff'], true) ? $receivingDivisionId : null;
            $currentUnitId = !empty($data['current_unit_id']) ? (int) $data['current_unit_id'] : null;
            $assignedStaffId = !empty($data['assigned_staff_id']) ? (int) $data['assigned_staff_id'] : null;
            $slipNumber = $this->reserveSlipNumber($originDepartmentId);
            $releaseAction = $data['release_action'] ?? 'Released';
            $initialStatus = $data['status'] ?? self::STATUS_RELEASED;

            $stmt = $this->db->prepare("
                INSERT INTO department_action_slips (
                    slip_number,
                    title,
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
                    parent_slip_id,
                    current_department_id,
                    current_division_id,
                    current_unit_id,
                    assigned_staff_id,
                    remarks,
                    created_by,
                    status
                ) VALUES (
                    :slip_number,
                    :title,
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
                    :parent_slip_id,
                    :current_department_id,
                    :current_division_id,
                    :current_unit_id,
                    :assigned_staff_id,
                    :remarks,
                    :created_by,
                    :status
                )
            ");

            $stmt->execute([
                'slip_number' => $slipNumber,
                'title' => trim((string) ($data['title'] ?? '')),
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
                'parent_slip_id' => !empty($data['parent_slip_id']) ? (int) $data['parent_slip_id'] : null,
                'current_department_id' => $receivingDepartmentId,
                'current_division_id' => $currentDivisionId,
                'current_unit_id' => $currentUnitId,
                'assigned_staff_id' => $assignedStaffId,
                'remarks' => $data['remarks'] ?: null,
                'created_by' => (int) $data['created_by'],
                'status' => $initialStatus
            ]);

            $slipId = (int) $this->db->lastInsertId();
            if ($assignedStaffId > 0) {
                $this->upsertAssignment($slipId, 'staff', $currentUnitId ?: $currentDivisionId ?: $receivingDepartmentId, $assignedStaffId, (int) $data['created_by'], $originDepartmentId, $initialStatus, $data['remarks'] ?: null);
            }
            if (($data['receiving_level'] ?? '') === 'Department') {
                $this->upsertAssignment($slipId, 'department', $receivingDepartmentId, null, (int) $data['created_by'], $originDepartmentId, $initialStatus, $data['remarks'] ?: null);
            }
            if (($data['receiving_level'] ?? '') === 'Division' && $receivingDivisionId) {
                $this->upsertAssignment($slipId, 'division', $receivingDivisionId, null, (int) $data['created_by'], $originDepartmentId, $initialStatus, $data['remarks'] ?: null);
            }
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
                'new_status' => $initialStatus,
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
                    title,
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
                    parent_slip_id,
                    current_department_id,
                    current_division_id,
                    assigned_staff_id,
                    remarks,
                    created_by,
                    status
                ) VALUES (
                    :slip_number,
                    :title,
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
                    :parent_slip_id,
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
                'title' => trim((string) ($data['title'] ?? $slipNumber)),
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
                'parent_slip_id' => !empty($data['parent_slip_id']) ? (int) $data['parent_slip_id'] : null,
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

        $where = ["das.parent_slip_id IS NULL"];
        if ($role === 'staff') {
            $params['user_id'] = (int) $userId;
            $where[] = "(
                das.created_by = :user_id
                OR das.completed_by = :user_id
                OR
                das.assigned_staff_id = :user_id
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_assignments a
                    WHERE a.slip_id = das.id
                    AND a.user_id = :user_id
                )
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_events e
                    WHERE e.slip_id = das.id
                    AND (e.actor_user_id = :user_id OR e.to_user_id = :user_id)
                )
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slips child
                    WHERE child.parent_slip_id = das.id
                    AND (
                        child.created_by = :user_id
                        OR child.completed_by = :user_id
                        OR child.assigned_staff_id = :user_id
                        OR EXISTS (
                            SELECT 1
                            FROM department_action_slip_events child_event
                            WHERE child_event.slip_id = child.id
                            AND (child_event.actor_user_id = :user_id OR child_event.to_user_id = :user_id)
                        )
                    )
                )
            )";
        } elseif ($role !== 'admin') {
            $params['user_id'] = (int) $userId;
            $params['department_id'] = (int) $departmentId;
            $where[] = "(
                das.created_by = :user_id
                OR das.completed_by = :user_id
                OR
                das.receiving_department_id = :department_id
                OR das.receiving_division_id = :department_id
                OR das.current_department_id = :department_id
                OR das.current_division_id = :department_id
                OR das.current_unit_id = :department_id
                OR das.assigned_staff_id = :user_id
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_assignments a
                    WHERE a.slip_id = das.id
                    AND (a.department_id = :department_id OR a.user_id = :user_id)
                )
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
        $where = ["das.parent_slip_id IS NULL"];
        if ($role === 'staff') {
            $params['user_id'] = (int) $userId;
            $where[] = "(das.created_by = :user_id OR das.completed_by = :user_id OR das.assigned_staff_id = :user_id OR EXISTS (SELECT 1 FROM department_action_slip_assignments a WHERE a.slip_id = das.id AND a.user_id = :user_id) OR EXISTS (SELECT 1 FROM department_action_slip_events e WHERE e.slip_id = das.id AND (e.actor_user_id = :user_id OR e.to_user_id = :user_id)) OR EXISTS (SELECT 1 FROM department_action_slips child WHERE child.parent_slip_id = das.id AND (child.created_by = :user_id OR child.completed_by = :user_id OR child.assigned_staff_id = :user_id OR EXISTS (SELECT 1 FROM department_action_slip_events child_event WHERE child_event.slip_id = child.id AND (child_event.actor_user_id = :user_id OR child_event.to_user_id = :user_id)))))";
        } elseif ($role !== 'admin') {
            $params['user_id'] = (int) $userId;
            $params['department_id'] = (int) $departmentId;
            $where[] = "(das.created_by = :user_id OR das.completed_by = :user_id OR das.receiving_department_id = :department_id OR das.receiving_division_id = :department_id OR das.current_department_id = :department_id OR das.current_division_id = :department_id OR das.current_unit_id = :department_id OR das.assigned_staff_id = :user_id OR EXISTS (SELECT 1 FROM department_action_slip_assignments a WHERE a.slip_id = das.id AND (a.department_id = :department_id OR a.user_id = :user_id)) OR EXISTS (SELECT 1 FROM department_action_slip_events e WHERE e.slip_id = das.id AND (e.actor_department_id = :department_id OR e.from_department_id = :department_id OR e.to_department_id = :department_id OR e.actor_user_id = :user_id OR e.to_user_id = :user_id)))";
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
                current_unit.division_name AS current_unit_name,
                parent.slip_number AS parent_slip_number,
                parent.title AS parent_slip_title,
                CONCAT(creator.firstname, ' ', IFNULL(CONCAT(creator.middle_initial, '. '), ''), creator.lastname) AS created_by_name,
                COALESCE(
                    CONCAT(staff.firstname, ' ', IFNULL(CONCAT(staff.middle_initial, '. '), ''), staff.lastname),
                    (
                        SELECT GROUP_CONCAT(CONCAT(assigned_staff.firstname, ' ', IFNULL(CONCAT(assigned_staff.middle_initial, '. '), ''), assigned_staff.lastname) ORDER BY assigned_staff.lastname ASC, assigned_staff.firstname ASC SEPARATOR ', ')
                        FROM department_action_slip_assignments staff_assignment
                        JOIN users assigned_staff ON assigned_staff.id = staff_assignment.user_id
                        WHERE staff_assignment.slip_id = das.id
                        AND staff_assignment.assignment_type = 'staff'
                        AND staff_assignment.status <> '" . self::STATUS_CANCELLED . "'
                    )
                ) AS assigned_staff_name
            FROM department_action_slips das
            JOIN departments receiving ON receiving.id = das.receiving_department_id
            LEFT JOIN departments receiving_division ON receiving_division.id = das.receiving_division_id
            LEFT JOIN departments current_dept ON current_dept.id = das.current_department_id
            LEFT JOIN departments current_division ON current_division.id = das.current_division_id
            LEFT JOIN departments current_unit ON current_unit.id = das.current_unit_id
            LEFT JOIN department_action_slips parent ON parent.id = das.parent_slip_id
            JOIN users creator ON creator.id = das.created_by
            LEFT JOIN users staff ON staff.id = das.assigned_staff_id
        ";
    }

    private function applyFilters(&$where, &$params, $filters)
    {
        if (trim($filters['keyword'] ?? '') !== '') {
            $where[] = "(das.slip_number LIKE :keyword OR das.title LIKE :keyword OR das.remarks LIKE :keyword OR EXISTS (SELECT 1 FROM department_action_slips child_filter WHERE child_filter.parent_slip_id = das.id AND child_filter.slip_number LIKE :keyword))";
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
            $where[] = "(das.assigned_staff_id = :filter_assigned_staff_id OR EXISTS (SELECT 1 FROM department_action_slip_assignments filter_assignment WHERE filter_assignment.slip_id = das.id AND filter_assignment.user_id = :filter_assigned_staff_id))";
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
                    das.created_by = :actor_user_id
                    OR das.completed_by = :actor_user_id
                    OR
                    das.assigned_staff_id = :assigned_user_id
                    OR EXISTS (
                        SELECT 1
                        FROM department_action_slip_assignments a
                        WHERE a.slip_id = das.id
                        AND a.user_id = :assigned_user_id
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM department_action_slip_events e
                        WHERE e.slip_id = das.id
                        AND (e.actor_user_id = :actor_user_id OR e.to_user_id = :event_to_user_id)
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM department_action_slips child
                        WHERE child.parent_slip_id = das.id
                        AND (
                            child.created_by = :actor_user_id
                            OR child.completed_by = :actor_user_id
                            OR child.assigned_staff_id = :assigned_user_id
                            OR EXISTS (
                                SELECT 1
                                FROM department_action_slip_events child_event
                                WHERE child_event.slip_id = child.id
                                AND (child_event.actor_user_id = :actor_user_id OR child_event.to_user_id = :event_to_user_id)
                            )
                        )
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
                das.created_by = :event_to_user_id
                OR das.completed_by = :event_to_user_id
                OR
                das.receiving_department_id = :receiving_department_id
                OR das.receiving_division_id = :receiving_division_id
                OR das.current_department_id = :current_department_id
                OR das.current_division_id = :current_division_id
                OR das.current_unit_id = :current_unit_id
                OR das.assigned_staff_id = :assigned_user_id
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_assignments a
                    WHERE a.slip_id = das.id
                    AND (a.department_id = :current_unit_id OR a.user_id = :assigned_user_id)
                )
                OR EXISTS (
                    SELECT 1
                    FROM department_action_slip_events e
                    WHERE e.slip_id = das.id
                    AND (
                        e.actor_department_id = :event_actor_department_id
                        OR e.from_department_id = :event_from_department_id
                        OR e.to_department_id = :event_to_department_id
                        OR e.actor_user_id = :event_to_user_id
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
            'current_unit_id' => (int) $departmentId,
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

    public function getActiveManagersByChildUnits($divisionId)
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.firstname,
                u.middle_initial,
                u.lastname,
                u.email,
                u.role,
                u.department_id,
                d.division_name AS unit_name
            FROM users u
            JOIN departments d ON d.id = u.department_id
            WHERE d.parent_id = :division_id
            AND d.office_level = 'unit'
            AND u.role = 'manager'
            AND u.status = 'active'
            ORDER BY d.division_name ASC, u.lastname ASC, u.firstname ASC
        ");
        $stmt->execute(['division_id' => (int) $divisionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveManagerInChildUnit($userId, $divisionId)
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.firstname,
                u.middle_initial,
                u.lastname,
                u.email,
                u.role,
                u.department_id,
                d.division_name AS unit_name
            FROM users u
            JOIN departments d ON d.id = u.department_id
            WHERE u.id = :user_id
            AND d.parent_id = :division_id
            AND d.office_level = 'unit'
            AND u.role = 'manager'
            AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => (int) $userId,
            'division_id' => (int) $divisionId
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
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

    public function getAssignments($slipId)
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                assigned_dept.division_name AS department_name,
                CONCAT(assigned_user.firstname, ' ', IFNULL(CONCAT(assigned_user.middle_initial, '. '), ''), assigned_user.lastname) AS user_name,
                CONCAT(assigner.firstname, ' ', IFNULL(CONCAT(assigner.middle_initial, '. '), ''), assigner.lastname) AS assigned_by_name,
                assigner_dept.division_name AS assigned_by_department_name
            FROM department_action_slip_assignments a
            LEFT JOIN departments assigned_dept ON assigned_dept.id = a.department_id
            LEFT JOIN users assigned_user ON assigned_user.id = a.user_id
            JOIN users assigner ON assigner.id = a.assigned_by
            JOIN departments assigner_dept ON assigner_dept.id = a.assigned_by_department_id
            WHERE a.slip_id = :slip_id
            ORDER BY a.assigned_at ASC, a.id ASC
        ");
        $stmt->execute(['slip_id' => (int) $slipId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findStaffAssignmentForUser($slipId, $userId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'staff'
            AND user_id = :user_id
            AND status <> :cancelled
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'user_id' => (int) $userId,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $assignment ?: null;
    }

    public function findUnitAssignmentForDepartment($slipId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'unit'
            AND department_id = :department_id
            AND status <> :cancelled
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $assignment ?: null;
    }

    public function findDivisionAssignmentForDepartment($slipId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'division'
            AND department_id = :department_id
            AND status <> :cancelled
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $assignment ?: null;
    }

    public function findDepartmentAssignmentForDepartment($slipId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'department'
            AND department_id = :department_id
            AND status <> :cancelled
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $assignment ?: null;
    }

    public function hasCompletedStaffAssignments($slipId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'staff'
            AND status = :completed
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'completed' => self::STATUS_COMPLETED
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasCompletedStaffAssignmentsForDepartment($slipId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'staff'
            AND department_id = :department_id
            AND status = :completed
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'completed' => self::STATUS_COMPLETED
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasUnresolvedStaffAssignmentsForDepartment($slipId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'staff'
            AND department_id = :department_id
            AND status NOT IN (:confirmed, :cancelled)
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'confirmed' => self::ASSIGNMENT_CONFIRMED,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasActiveStaffAssignmentsForDepartment($slipId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'staff'
            AND department_id = :department_id
            AND status <> :cancelled
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getCompletedStaffAssignmentsForDepartment($slipId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT
                assignment.*,
                CONCAT(staff.firstname, ' ', IFNULL(CONCAT(staff.middle_initial, '. '), ''), staff.lastname) AS staff_name
            FROM department_action_slip_assignments assignment
            JOIN users staff ON staff.id = assignment.user_id
            WHERE assignment.slip_id = :slip_id
            AND assignment.assignment_type = 'staff'
            AND assignment.department_id = :department_id
            AND assignment.status = :completed
            ORDER BY assignment.completed_at ASC, assignment.id ASC
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'completed' => self::STATUS_COMPLETED
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findCompletedStaffAssignmentForDepartment($slipId, $assignmentId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM department_action_slip_assignments
            WHERE id = :assignment_id
            AND slip_id = :slip_id
            AND assignment_type = 'staff'
            AND department_id = :department_id
            AND status = :completed
            LIMIT 1
        ");
        $stmt->execute([
            'assignment_id' => (int) $assignmentId,
            'slip_id' => (int) $slipId,
            'department_id' => (int) $departmentId,
            'completed' => self::STATUS_COMPLETED
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $assignment ?: null;
    }

    public function hasCompletedUnitAssignmentsForDivision($slipId, $divisionId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slip_assignments unit_assignment
            JOIN departments unit ON unit.id = unit_assignment.department_id
            WHERE unit_assignment.slip_id = :slip_id
            AND unit_assignment.assignment_type = 'unit'
            AND unit.parent_id = :division_id
            AND unit_assignment.status = :completed
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'division_id' => (int) $divisionId,
            'completed' => self::STATUS_COMPLETED
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getCompletedUnitAssignmentsForDivision($slipId, $divisionId)
    {
        $stmt = $this->db->prepare("
            SELECT
                unit_assignment.*,
                unit.division_name AS unit_name,
                CONCAT(assignee.firstname, ' ', IFNULL(CONCAT(assignee.middle_initial, '. '), ''), assignee.lastname) AS assignee_name
            FROM department_action_slip_assignments unit_assignment
            JOIN departments unit ON unit.id = unit_assignment.department_id
            LEFT JOIN users assignee ON assignee.id = unit_assignment.user_id
            WHERE unit_assignment.slip_id = :slip_id
            AND unit_assignment.assignment_type = 'unit'
            AND unit.parent_id = :division_id
            AND unit_assignment.status = :completed
            ORDER BY unit_assignment.completed_at ASC, unit_assignment.id ASC
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'division_id' => (int) $divisionId,
            'completed' => self::STATUS_COMPLETED
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findCompletedUnitAssignmentForDivision($slipId, $assignmentId, $divisionId)
    {
        $stmt = $this->db->prepare("
            SELECT unit_assignment.*
            FROM department_action_slip_assignments unit_assignment
            JOIN departments unit ON unit.id = unit_assignment.department_id
            WHERE unit_assignment.id = :assignment_id
            AND unit_assignment.slip_id = :slip_id
            AND unit_assignment.assignment_type = 'unit'
            AND unit.parent_id = :division_id
            AND unit_assignment.status = :completed
            LIMIT 1
        ");
        $stmt->execute([
            'assignment_id' => (int) $assignmentId,
            'slip_id' => (int) $slipId,
            'division_id' => (int) $divisionId,
            'completed' => self::STATUS_COMPLETED
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $assignment ?: null;
    }

    private function hasUnresolvedUnitAssignmentsForDivision($slipId, $divisionId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slip_assignments unit_assignment
            JOIN departments unit ON unit.id = unit_assignment.department_id
            WHERE unit_assignment.slip_id = :slip_id
            AND unit_assignment.assignment_type = 'unit'
            AND unit.parent_id = :division_id
            AND unit_assignment.status NOT IN (:confirmed, :cancelled)
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'division_id' => (int) $divisionId,
            'confirmed' => self::ASSIGNMENT_CONFIRMED,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function upsertAssignment($slipId, $type, $departmentId, $userId, $assignedBy, $assignedByDepartmentId, $status, $remarks = '')
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = :assignment_type
            AND ((department_id <=> :department_id) AND (user_id <=> :user_id))
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'assignment_type' => $type,
            'department_id' => $departmentId ? (int) $departmentId : null,
            'user_id' => $userId ? (int) $userId : null
        ]);
        $assignmentId = (int) $stmt->fetchColumn();

        if ($assignmentId > 0) {
            $update = $this->db->prepare("
                UPDATE department_action_slip_assignments
                SET assigned_by = :assigned_by,
                    assigned_by_department_id = :assigned_by_department_id,
                    status = :status,
                    remarks = :remarks,
                    assigned_at = NOW(),
                    received_at = NULL,
                    completed_at = NULL,
                    completed_by = NULL,
                    completion_attachment = NULL
                WHERE id = :id
            ");
            $update->execute([
                'assigned_by' => (int) $assignedBy,
                'assigned_by_department_id' => (int) $assignedByDepartmentId,
                'status' => $status,
                'remarks' => trim((string) $remarks) !== '' ? trim((string) $remarks) : null,
                'id' => $assignmentId
            ]);
            return $assignmentId;
        }

        $insert = $this->db->prepare("
            INSERT INTO department_action_slip_assignments (
                slip_id,
                assignment_type,
                department_id,
                user_id,
                assigned_by,
                assigned_by_department_id,
                status,
                remarks
            ) VALUES (
                :slip_id,
                :assignment_type,
                :department_id,
                :user_id,
                :assigned_by,
                :assigned_by_department_id,
                :status,
                :remarks
            )
        ");
        $insert->execute([
            'slip_id' => (int) $slipId,
            'assignment_type' => $type,
            'department_id' => $departmentId ? (int) $departmentId : null,
            'user_id' => $userId ? (int) $userId : null,
            'assigned_by' => (int) $assignedBy,
            'assigned_by_department_id' => (int) $assignedByDepartmentId,
            'status' => $status,
            'remarks' => trim((string) $remarks) !== '' ? trim((string) $remarks) : null
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function updateStaffAssignmentStatus($slipId, $userId, $status, $fields = [])
    {
        $assignment = $this->findStaffAssignmentForUser($slipId, $userId);
        if (!$assignment) {
            return false;
        }

        $sets = ['status = :status'];
        $params = [
            'id' => (int) $assignment['id'],
            'status' => $status
        ];
        foreach ($fields as $field => $value) {
            $sets[] = $field . ' = :' . $field;
            $params[$field] = $value;
        }

        $stmt = $this->db->prepare("UPDATE department_action_slip_assignments SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
        return true;
    }

    private function updateAssignmentStatus($slipId, $type, $departmentId, $userId, $status, $fields = [])
    {
        $conditions = [
            'slip_id = :slip_id',
            'assignment_type = :assignment_type',
            'status <> :cancelled'
        ];
        $params = [
            'slip_id' => (int) $slipId,
            'assignment_type' => $type,
            'cancelled' => self::STATUS_CANCELLED
        ];

        if ($departmentId !== null) {
            $conditions[] = 'department_id = :department_id';
            $params['department_id'] = (int) $departmentId;
        }
        if ($userId !== null) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = (int) $userId;
        }

        $sets = ['status = :status'];
        $params['status'] = $status;
        foreach ($fields as $field => $value) {
            $sets[] = $field . ' = :' . $field;
            $params[$field] = $value;
        }

        $stmt = $this->db->prepare("
            UPDATE department_action_slip_assignments
            SET " . implode(', ', $sets) . "
            WHERE " . implode(' AND ', $conditions) . "
        ");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    private function updateCompletedAssignmentsStatus($slipId, $type, $departmentId, $status, $fields = [])
    {
        $sets = ['status = :status'];
        $params = [
            'slip_id' => (int) $slipId,
            'assignment_type' => $type,
            'department_id' => (int) $departmentId,
            'current_status' => self::STATUS_COMPLETED,
            'status' => $status
        ];
        foreach ($fields as $field => $value) {
            $sets[] = $field . ' = :' . $field;
            $params[$field] = $value;
        }

        $stmt = $this->db->prepare("
            UPDATE department_action_slip_assignments
            SET " . implode(', ', $sets) . "
            WHERE slip_id = :slip_id
            AND assignment_type = :assignment_type
            AND department_id = :department_id
            AND status = :current_status
        ");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    private function hasPendingStaffAssignments($slipId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND assignment_type = 'staff'
            AND status NOT IN (:completed, :confirmed, :cancelled)
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'completed' => self::STATUS_COMPLETED,
            'confirmed' => self::ASSIGNMENT_CONFIRMED,
            'cancelled' => self::STATUS_CANCELLED
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function rollupAssignmentStatus($slipId, $fallbackStatus = self::STATUS_FOR_ACTION)
    {
        $stmt = $this->db->prepare("
            SELECT
                SUM(status = :returned) AS returned_count,
                SUM(status NOT IN (:completed, :confirmed, :cancelled)) AS active_count,
                COUNT(*) AS total_count
            FROM department_action_slip_assignments
            WHERE slip_id = :slip_id
            AND status <> :cancelled_filter
        ");
        $stmt->execute([
            'slip_id' => (int) $slipId,
            'returned' => self::STATUS_RETURNED,
            'completed' => self::STATUS_COMPLETED,
            'confirmed' => self::ASSIGNMENT_CONFIRMED,
            'cancelled' => self::STATUS_CANCELLED,
            'cancelled_filter' => self::STATUS_CANCELLED
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ((int) ($row['total_count'] ?? 0) === 0) {
            return $fallbackStatus;
        }
        if ((int) ($row['returned_count'] ?? 0) > 0) {
            return self::STATUS_RETURNED;
        }
        if ((int) ($row['active_count'] ?? 0) > 0) {
            return self::STATUS_FOR_ACTION;
        }
        return self::STATUS_COMPLETED;
    }

    public function receiveByDepartment($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $this->updateAssignmentStatus($slipId, 'department', $actorDepartmentId, null, self::STATUS_RECEIVED, [
            'received_at' => date('Y-m-d H:i:s')
        ]);
        $this->transition($slipId, $this->rollupAssignmentStatus($slipId, self::STATUS_RECEIVED), 'Received by Department', $actorUserId, $actorDepartmentId, $remarks);
    }

    public function releaseDraft($slipId, $data)
    {
        $slip = $this->requireSlip($slipId);
        $receivingDepartmentId = (int) $data['receiving_department_id'];
        $receivingDivisionId = !empty($data['receiving_division_id']) ? (int) $data['receiving_division_id'] : null;
        $currentDivisionId = in_array($data['receiving_level'], ['Division', 'Staff'], true) ? $receivingDivisionId : null;
        $assignedStaffId = !empty($data['assigned_staff_id']) ? (int) $data['assigned_staff_id'] : null;
        $releaseAction = $data['release_action'] ?? 'Released';

        if ($assignedStaffId > 0) {
            $this->upsertAssignment($slipId, 'staff', $data['current_unit_id'] ?? ($currentDivisionId ?: $receivingDepartmentId), $assignedStaffId, (int) $data['actor_user_id'], (int) $data['actor_department_id'], self::STATUS_RELEASED, $data['remarks'] ?: null);
        }
        if (($data['receiving_level'] ?? '') === 'Division' && $receivingDivisionId) {
            $this->upsertAssignment($slipId, 'division', $receivingDivisionId, null, (int) $data['actor_user_id'], (int) $data['actor_department_id'], self::STATUS_RELEASED, $data['remarks'] ?: null);
        }
        if (($data['receiving_level'] ?? '') === 'Department') {
            $this->upsertAssignment($slipId, 'department', $receivingDepartmentId, null, (int) $data['actor_user_id'], (int) $data['actor_department_id'], self::STATUS_RELEASED, $data['remarks'] ?: null);
        }

        $this->updateSlipAndLog($slipId, [
            'external_source' => $data['external_source'] ?? 'Internal Action Slip',
            'title' => trim((string) ($data['title'] ?? $slip['title'] ?? $slip['slip_number'])),
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
            'current_unit_id' => null,
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
        $this->upsertAssignment($slipId, 'department', $targetDepartmentId, null, $actorUserId, $actorDepartmentId, $status, $remarks);

        $this->updateSlipAndLog($slipId, [
            'current_department_id' => (int) $targetDepartmentId,
            'current_division_id' => null,
            'current_unit_id' => null,
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
        $this->upsertAssignment($slipId, 'division', $divisionId, null, $actorUserId, $actorDepartmentId, $status, $remarks);

        $this->updateSlipAndLog($slipId, [
            'current_department_id' => (int) $actorDepartmentId,
            'current_division_id' => (int) $divisionId,
            'current_unit_id' => null,
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
        $this->updateAssignmentStatus($slipId, 'division', $actorDepartmentId, null, self::STATUS_RECEIVED, [
            'received_at' => date('Y-m-d H:i:s')
        ]);
        $this->transition($slipId, $this->rollupAssignmentStatus($slipId, self::STATUS_RECEIVED), 'Received by Division', $actorUserId, $actorDepartmentId, $remarks);
    }

    public function delegateToUnit($slipId, $unitId, $unitManagerUserId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $this->upsertAssignment($slipId, 'unit', $unitId, $unitManagerUserId, $actorUserId, $actorDepartmentId, self::STATUS_DELEGATED, $remarks);
        $this->updateAssignmentStatus($slipId, 'division', $actorDepartmentId, null, self::STATUS_FOR_ACTION);

        $this->updateSlipAndLog($slipId, [
            'current_unit_id' => (int) $unitId,
            'assigned_staff_id' => null,
            'status' => $this->rollupAssignmentStatus($slipId, self::STATUS_DELEGATED),
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => 'Delegated to Unit',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $unitId,
            'to_user_id' => $unitManagerUserId,
            'old_status' => $oldStatus,
            'new_status' => $this->rollupAssignmentStatus($slipId, self::STATUS_DELEGATED),
            'remarks' => $remarks
        ]);
    }

    public function receiveByUnit($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $this->updateAssignmentStatus($slipId, 'unit', $actorDepartmentId, null, self::STATUS_RECEIVED, [
            'received_at' => date('Y-m-d H:i:s')
        ]);
        $this->transition($slipId, $this->rollupAssignmentStatus($slipId, self::STATUS_RECEIVED), 'Received by Unit', $actorUserId, $actorDepartmentId, $remarks);
    }

    public function delegateToStaff($slipId, $staffUserId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $this->upsertAssignment($slipId, 'staff', $actorDepartmentId, $staffUserId, $actorUserId, $actorDepartmentId, self::STATUS_DELEGATED, $remarks);
        $this->updateAssignmentStatus($slipId, 'unit', $actorDepartmentId, null, self::STATUS_FOR_ACTION);
        $assignedStaffId = !empty($slip['assigned_staff_id']) ? (int) $slip['assigned_staff_id'] : (int) $staffUserId;
        $newStatus = $this->rollupAssignmentStatus($slipId, self::STATUS_DELEGATED);

        $this->updateSlipAndLog($slipId, [
            'assigned_staff_id' => $assignedStaffId,
            'status' => $newStatus
        ], [
            'action' => 'Further Delegated to Staff',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $actorDepartmentId,
            'to_user_id' => $staffUserId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks
        ]);
    }

    public function startStaffAction($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $this->updateStaffAssignmentStatus($slipId, $actorUserId, self::STATUS_FOR_ACTION, [
            'received_at' => date('Y-m-d H:i:s')
        ]);
        $this->transition($slipId, $this->rollupAssignmentStatus($slipId, self::STATUS_FOR_ACTION), 'Received by Staff', $actorUserId, $actorDepartmentId, $remarks);
    }

    public function completeByStaff($slipId, $actorUserId, $actorDepartmentId, $remarks = '', $attachment = null)
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $action = $oldStatus === self::STATUS_RETURNED ? 'Resubmitted by Staff' : 'Completed by Staff';
        $this->updateStaffAssignmentStatus($slipId, $actorUserId, self::STATUS_COMPLETED, [
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => (int) $actorUserId,
            'completion_attachment' => $attachment
        ]);
        $newStatus = $this->rollupAssignmentStatus($slipId, self::STATUS_COMPLETED);

        $this->updateSlipAndLog($slipId, [
            'status' => $newStatus,
            'completed_at' => $newStatus === self::STATUS_COMPLETED ? date('Y-m-d H:i:s') : null,
            'completed_by' => $newStatus === self::STATUS_COMPLETED ? (int) $actorUserId : null
        ], [
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks,
            'attachment' => $attachment
        ]);
    }

    public function returnStaffCompletion($slipId, $actorUserId, $actorDepartmentId, $remarks = '', $assignmentId = null)
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $assignment = $assignmentId ? $this->findCompletedStaffAssignmentForDepartment($slipId, $assignmentId, $actorDepartmentId) : null;
        if (!$assignment) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM department_action_slip_assignments
                WHERE slip_id = :slip_id
                AND assignment_type = 'staff'
                AND department_id = :department_id
                AND status = :completed
                ORDER BY completed_at DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([
                'slip_id' => (int) $slipId,
                'department_id' => (int) $actorDepartmentId,
                'completed' => self::STATUS_COMPLETED
            ]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $staffUserId = (int) (($assignment['user_id'] ?? null) ?: ($slip['assigned_staff_id'] ?? 0));
        $action = !empty($slip['current_unit_id']) ? 'Returned by Unit Manager' : 'Returned by Division Manager';
        if ($staffUserId > 0) {
            $this->updateStaffAssignmentStatus($slipId, $staffUserId, self::STATUS_RETURNED, [
                'completed_at' => null,
                'completed_by' => null,
                'completion_attachment' => null
            ]);
        }

        $this->updateSlipAndLog($slipId, [
            'status' => self::STATUS_RETURNED,
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => $action,
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

    public function confirmByUnitManager($slipId, $actorUserId, $actorDepartmentId, $remarks = '', $assignmentId = null)
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $assignment = $assignmentId ? $this->findCompletedStaffAssignmentForDepartment($slipId, $assignmentId, $actorDepartmentId) : null;
        if (!$assignment) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM department_action_slip_assignments
                WHERE slip_id = :slip_id
                AND assignment_type = 'staff'
                AND department_id = :department_id
                AND status = :completed
                ORDER BY completed_at ASC, id ASC
                LIMIT 1
            ");
            $stmt->execute([
                'slip_id' => (int) $slipId,
                'department_id' => (int) $actorDepartmentId,
                'completed' => self::STATUS_COMPLETED
            ]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$assignment) {
            throw new RuntimeException('No completed staff assignment is available for confirmation.');
        }

        $this->updateStaffAssignmentStatus($slipId, (int) $assignment['user_id'], self::ASSIGNMENT_CONFIRMED, [
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => (int) $actorUserId
        ]);
        $hasUnresolvedStaffAssignments = $this->hasUnresolvedStaffAssignmentsForDepartment($slipId, $actorDepartmentId);
        $unitStatus = $hasUnresolvedStaffAssignments ? self::STATUS_RECEIVED : self::STATUS_COMPLETED;
        $unitFields = $hasUnresolvedStaffAssignments
            ? ['received_at' => date('Y-m-d H:i:s')]
            : ['completed_at' => date('Y-m-d H:i:s'), 'completed_by' => (int) $actorUserId];
        $this->updateAssignmentStatus($slipId, 'unit', $actorDepartmentId, null, $unitStatus, $unitFields);
        $newStatus = $this->rollupAssignmentStatus($slipId, $unitStatus);

        $this->updateSlipAndLog($slipId, [
            'assigned_staff_id' => null,
            'status' => $newStatus,
            'completed_at' => null,
            'completed_by' => null
        ], [
            'action' => 'Staff Completion Confirmed',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'to_department_id' => $actorDepartmentId,
            'to_user_id' => (int) $assignment['user_id'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks
        ]);
    }

    public function completeByUnitManager($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $this->updateAssignmentStatus($slipId, 'unit', $actorDepartmentId, null, self::STATUS_COMPLETED, [
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => (int) $actorUserId
        ]);
        $newStatus = $this->rollupAssignmentStatus($slipId, self::STATUS_COMPLETED);

        $this->updateSlipAndLog($slipId, [
            'assigned_staff_id' => null,
            'status' => $newStatus,
            'completed_at' => $newStatus === self::STATUS_COMPLETED ? date('Y-m-d H:i:s') : null,
            'completed_by' => $newStatus === self::STATUS_COMPLETED ? (int) $actorUserId : null
        ], [
            'action' => 'Completed by Unit',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks
        ]);
    }

    public function returnUnitCompletion($slipId, $actorUserId, $actorDepartmentId, $remarks = '', $assignmentId = null)
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $assignment = $assignmentId ? $this->findCompletedUnitAssignmentForDivision($slipId, $assignmentId, $actorDepartmentId) : null;
        if (!$assignment) {
            $stmt = $this->db->prepare("
                SELECT unit_assignment.*
                FROM department_action_slip_assignments unit_assignment
                JOIN departments unit ON unit.id = unit_assignment.department_id
                WHERE unit_assignment.slip_id = :slip_id
                AND unit_assignment.assignment_type = 'unit'
                AND unit.parent_id = :division_id
                AND unit_assignment.status = :completed
                ORDER BY unit_assignment.completed_at ASC, unit_assignment.id ASC
                LIMIT 1
            ");
            $stmt->execute([
                'slip_id' => (int) $slipId,
                'division_id' => (int) $actorDepartmentId,
                'completed' => self::STATUS_COMPLETED
            ]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$assignment) {
            throw new RuntimeException('No completed unit assignment is available for return.');
        }
        $unitId = (int) $assignment['department_id'];
        $this->updateAssignmentById((int) $assignment['id'], self::STATUS_RETURNED, [
            'completed_at' => null,
            'completed_by' => null,
            'completion_attachment' => null
        ]);
        $newStatus = $this->rollupAssignmentStatus($slipId, self::STATUS_RETURNED);

        $this->updateSlipAndLog($slipId, [
            'assigned_staff_id' => null,
            'status' => $newStatus,
            'completed_at' => null,
            'completed_by' => null,
            'closed_at' => null,
            'closed_by' => null
        ], [
            'action' => 'Returned by Division Manager',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'from_department_id' => $actorDepartmentId,
            'to_department_id' => $unitId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks
        ]);

        return $unitId;
    }

    public function confirmByDivisionManager($slipId, $actorUserId, $actorDepartmentId, $remarks = '', $assignmentId = null)
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $confirmedUnitCompletion = false;
        $confirmedUnitId = null;
        $assignment = $assignmentId ? $this->findCompletedUnitAssignmentForDivision($slipId, $assignmentId, $actorDepartmentId) : null;
        if (!$assignment && $assignmentId) {
            throw new RuntimeException('No completed unit assignment is available for confirmation.');
        }

        if ($assignment) {
            $this->updateAssignmentById((int) $assignment['id'], self::ASSIGNMENT_CONFIRMED, [
                'completed_at' => date('Y-m-d H:i:s'),
                'completed_by' => (int) $actorUserId
            ]);
            $confirmedUnitCompletion = true;
            $confirmedUnitId = (int) $assignment['department_id'];
        }

        $divisionStatus = (!$confirmedUnitCompletion || !$this->hasUnresolvedUnitAssignmentsForDivision($slipId, $actorDepartmentId))
            ? self::STATUS_COMPLETED
            : self::STATUS_FOR_ACTION;
        $divisionFields = $divisionStatus === self::STATUS_COMPLETED
            ? ['completed_at' => date('Y-m-d H:i:s'), 'completed_by' => (int) $actorUserId]
            : ['completed_at' => null, 'completed_by' => null];
        $this->updateAssignmentStatus($slipId, 'division', $actorDepartmentId, null, $divisionStatus, $divisionFields);
        $newStatus = $this->rollupAssignmentStatus($slipId, $divisionStatus);

        $this->updateSlipAndLog($slipId, [
            'current_division_id' => null,
            'current_unit_id' => null,
            'assigned_staff_id' => null,
            'status' => $newStatus,
            'completed_at' => $newStatus === self::STATUS_COMPLETED ? ($slip['completed_at'] ?: date('Y-m-d H:i:s')) : null,
            'completed_by' => $newStatus === self::STATUS_COMPLETED ? (!empty($slip['completed_by']) ? (int) $slip['completed_by'] : (int) $actorUserId) : null
        ], [
            'action' => $confirmedUnitCompletion ? 'Unit Completion Confirmed' : 'Completed by Division',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'to_department_id' => $confirmedUnitId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks
        ]);
    }

    public function completeByDepartmentManager($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $this->updateAssignmentStatus($slipId, 'department', $actorDepartmentId, null, self::STATUS_COMPLETED, [
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => (int) $actorUserId
        ]);
        $newStatus = $this->rollupAssignmentStatus($slipId, self::STATUS_COMPLETED);

        $this->updateSlipAndLog($slipId, [
            'status' => $newStatus,
            'completed_at' => $newStatus === self::STATUS_COMPLETED ? date('Y-m-d H:i:s') : null,
            'completed_by' => $newStatus === self::STATUS_COMPLETED ? (int) $actorUserId : null
        ], [
            'action' => 'Completed by Department',
            'actor_user_id' => $actorUserId,
            'actor_department_id' => $actorDepartmentId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks
        ]);
    }

    public function returnSlip($slipId, $actorUserId, $actorDepartmentId, $remarks = '')
    {
        $slip = $this->requireSlip($slipId);
        $oldStatus = $slip['status'];
        $returnTargetDepartmentId = $this->getReturnTargetDepartmentId($slipId, $actorDepartmentId);
        $isDivisionReturn = (int) ($slip['current_division_id'] ?? 0) === (int) $actorDepartmentId;
        if ($isDivisionReturn) {
            $this->updateAssignmentStatus($slipId, 'division', $actorDepartmentId, null, self::STATUS_RETURNED, [
                'completed_at' => null,
                'completed_by' => null,
                'completion_attachment' => null
            ]);
        } else {
            $this->updateAssignmentStatus($slipId, 'department', $actorDepartmentId, null, self::STATUS_RETURNED, [
                'completed_at' => null,
                'completed_by' => null,
                'completion_attachment' => null
            ]);
        }
        $newStatus = $this->rollupAssignmentStatus($slipId, self::STATUS_RETURNED);

        $this->updateSlipAndLog($slipId, [
            'current_department_id' => $returnTargetDepartmentId,
            'current_division_id' => null,
            'current_unit_id' => null,
            'assigned_staff_id' => null,
            'status' => $newStatus,
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
            'new_status' => $newStatus,
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
