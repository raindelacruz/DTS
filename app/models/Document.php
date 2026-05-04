<?php

class Document
{
    private $db;

    public function __construct()
    {
        $this->db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );

        $this->ensureRoutingTable();
        $this->ensureSchema();
    }

    private function ensureRoutingTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS document_routes (
                id INT(11) NOT NULL AUTO_INCREMENT,
                document_id INT(11) NOT NULL,
                from_department_id INT(11) NOT NULL,
                to_department_id INT(11) NOT NULL,
                routing_type ENUM('TO','THRU','CC','DELEGATE') NOT NULL,
                instructions TEXT NULL,
                status ENUM('Pending','Received') DEFAULT 'Pending',
                routed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                received_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY document_id (document_id),
                KEY from_department_id (from_department_id),
                KEY to_department_id (to_department_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        $this->db->exec($sql);
    }

    private function ensureSchema()
    {
        if (!$this->columnExists('documents', 'particulars')) {
            $this->db->exec("
                ALTER TABLE documents
                ADD COLUMN particulars TEXT DEFAULT NULL
                AFTER title
            ");
        }

        if (!$this->columnExists('documents', 'reference_document_id')) {
            $this->db->exec("
                ALTER TABLE documents
                ADD COLUMN reference_document_id INT(11) DEFAULT NULL
                AFTER destination_department_id
            ");
        }

        if (!$this->columnExists('documents', 'qr_token')) {
            $this->db->exec("
                ALTER TABLE documents
                ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL
                AFTER attachment
            ");
        }

        if (!$this->indexExists('documents', 'uq_documents_qr_token')) {
            $this->db->exec("
                ALTER TABLE documents
                ADD UNIQUE KEY uq_documents_qr_token (qr_token)
            ");
        }

        if (!$this->enumColumnIncludes('documents', 'status', 'Returned') || !$this->enumColumnIncludes('documents', 'status', 'Re-released')) {
            $this->db->exec("
                ALTER TABLE documents
                MODIFY status ENUM('Draft','Released','Received','Returned','Re-released') DEFAULT 'Draft'
            ");
        }

        if (!$this->enumColumnIncludes('document_routes', 'status', 'Returned')) {
            $this->db->exec("
                ALTER TABLE document_routes
                MODIFY status ENUM('Pending','Received','Returned') DEFAULT 'Pending'
            ");
        }

        $this->ensureReturnTables();
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

    private function enumColumnIncludes($table, $column, $value)
    {
        $stmt = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :schema_name
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
            LIMIT 1
        ");

        $stmt->execute([
            'schema_name' => DB_NAME,
            'table_name' => $table,
            'column_name' => $column
        ]);

        $columnType = (string) $stmt->fetchColumn();
        return strpos($columnType, "'" . str_replace("'", "''", $value) . "'") !== false;
    }

    private function ensureReturnTables()
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS document_returns (
                id INT(11) NOT NULL AUTO_INCREMENT,
                document_id INT(11) NOT NULL,
                route_id INT(11) DEFAULT NULL,
                returned_by INT(11) NOT NULL,
                returned_department_id INT(11) NOT NULL,
                releasing_department_id INT(11) NOT NULL,
                return_reason VARCHAR(150) NOT NULL,
                attachment_issue VARCHAR(80) DEFAULT NULL,
                remarks TEXT NOT NULL,
                status ENUM('Open','Resolved') NOT NULL DEFAULT 'Open',
                returned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME DEFAULT NULL,
                resolved_by INT(11) DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_document_returns_document (document_id),
                KEY idx_document_returns_status (status),
                KEY idx_document_returns_route (route_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS document_attachment_history (
                id INT(11) NOT NULL AUTO_INCREMENT,
                document_id INT(11) NOT NULL,
                return_id INT(11) DEFAULT NULL,
                old_filename VARCHAR(255) DEFAULT NULL,
                new_filename VARCHAR(255) NOT NULL,
                uploaded_by INT(11) NOT NULL,
                uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                replacement_reason TEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                KEY idx_document_attachment_history_document (document_id),
                KEY idx_document_attachment_history_return (return_id),
                KEY idx_document_attachment_history_active (document_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function generateQrToken()
    {
        return bin2hex(random_bytes(24));
    }

    public function ensureQrToken($documentId)
    {
        $documentId = (int) $documentId;
        $document = $this->findById($documentId);

        if (!$document) {
            throw new RuntimeException('Document not found.');
        }

        $existingToken = trim((string) ($document['qr_token'] ?? ''));
        if ($existingToken !== '') {
            return $existingToken;
        }

        do {
            $token = $this->generateQrToken();
            $stmt = $this->db->prepare("
                UPDATE documents
                SET qr_token = :qr_token
                WHERE id = :id
                AND (qr_token IS NULL OR qr_token = '')
            ");

            $updated = false;

            try {
                $stmt->execute([
                    'qr_token' => $token,
                    'id' => $documentId
                ]);
                $updated = $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? null) != 1062) {
                    throw $e;
                }
            }

            if ($updated) {
                return $token;
            }

            $document = $this->findById($documentId);
            $existingToken = trim((string) ($document['qr_token'] ?? ''));
            if ($existingToken !== '') {
                return $existingToken;
            }
        } while (true);
    }

    private function insertRoute($documentId, $fromDepartmentId, $toDepartmentId, $routingType, $instructions = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO document_routes (
                document_id,
                from_department_id,
                to_department_id,
                routing_type,
                instructions,
                status,
                routed_at
            ) VALUES (
                :document_id,
                :from_department_id,
                :to_department_id,
                :routing_type,
                :instructions,
                'Pending',
                NOW()
            )
        ");

        $stmt->execute([
            'document_id' => $documentId,
            'from_department_id' => $fromDepartmentId,
            'to_department_id' => $toDepartmentId,
            'routing_type' => $routingType,
            'instructions' => $instructions
        ]);
    }


    private function isChildDepartmentOf($childDepartmentId, $parentDepartmentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM departments
            WHERE id = :child_department_id
            AND parent_id = :parent_department_id
        ");

        $stmt->execute([
            'child_department_id' => $childDepartmentId,
            'parent_department_id' => $parentDepartmentId
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function formatActionSlipText($actionSlip = [])
    {
        $urgent = !empty($actionSlip['urgent']) ? 'Yes' : 'No';
        $actionType = trim($actionSlip['action_type'] ?? '');
        $deadline = trim($actionSlip['deadline_date'] ?? '');
        $instruction = trim($actionSlip['instruction'] ?? '');

        return implode("\n", [
            'Urgent: ' . $urgent,
            'Action: ' . $actionType,
            'Deadline: ' . $deadline,
            'Instruction: ' . $instruction
        ]);
    }

    public function getNextPrefix($departmentId)
    {
        $departmentId = (int) $departmentId;

        $stmt = $this->db->prepare("SELECT code FROM departments WHERE id = :department_id");
        $stmt->execute(['department_id' => $departmentId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            throw new Exception('Invalid Department.');
        }

        $year = date('Y');
        $month = date('m');

        $stmt = $this->db->prepare("
            SELECT last_number
            FROM document_sequences
            WHERE department_id = :department_id
            AND year = :year
            AND month = :month
        ");

        $stmt->execute([
            'department_id' => $departmentId,
            'year' => $year,
            'month' => $month
        ]);

        $lastNumber = (int) ($stmt->fetchColumn() ?: 0);
        $formattedNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

        return "{$department['code']}-{$year}-{$month}-{$formattedNumber}";
    }

    private function hasThru($documentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_routes
            WHERE document_id = :document_id
            AND routing_type = 'THRU'
        ");

        $stmt->execute(['document_id' => $documentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function isThruCleared($documentId)
    {
        if (!$this->hasThru($documentId)) {
            return true;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_routes
            WHERE document_id = :document_id
            AND routing_type = 'THRU'
            AND status = 'Received'
        ");

        $stmt->execute(['document_id' => $documentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function areAllToReceived($documentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_routes
            WHERE document_id = :document_id
            AND routing_type = 'TO'
            AND status = 'Pending'
        ");

        $stmt->execute(['document_id' => $documentId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    private function hasToRoute($documentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_routes
            WHERE document_id = :document_id
            AND routing_type = 'TO'
        ");

        $stmt->execute(['document_id' => $documentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function areAllDelegateReceived($documentId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_routes
            WHERE document_id = :document_id
            AND routing_type = 'DELEGATE'
            AND status = 'Pending'
        ");

        $stmt->execute(['document_id' => $documentId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    private function visibilityWhereClause()
    {
        return "
            (
                d.origin_department_id = :dept
                OR EXISTS (
                    SELECT 1
                    FROM document_logs l
                    WHERE l.document_id = d.id
                    AND l.department_id = :dept
                )
                OR (
                    d.status <> 'Draft'
                    AND (
                        EXISTS (
                            SELECT 1
                            FROM document_routes r_thru
                            WHERE r_thru.document_id = d.id
                            AND r_thru.to_department_id = :dept
                            AND r_thru.routing_type = 'THRU'
                        )
                        OR (
                            EXISTS (
                                SELECT 1
                                FROM document_routes r_target
                                WHERE r_target.document_id = d.id
                                AND r_target.to_department_id = :dept
                                AND r_target.routing_type IN ('TO','CC','DELEGATE')
                            )
                            AND (
                                NOT EXISTS (
                                    SELECT 1
                                    FROM document_routes r_has_thru
                                    WHERE r_has_thru.document_id = d.id
                                    AND r_has_thru.routing_type = 'THRU'
                                )
                                OR EXISTS (
                                    SELECT 1
                                    FROM document_routes r_thru_cleared
                                    WHERE r_thru_cleared.document_id = d.id
                                    AND r_thru_cleared.routing_type = 'THRU'
                                    AND r_thru_cleared.status = 'Received'
                                )
                            )
                        )
                        OR (
                            NOT EXISTS (
                                SELECT 1
                                FROM document_routes r_legacy
                                WHERE r_legacy.document_id = d.id
                            )
                            AND d.destination_department_id = :dept
                        )
                    )
                )
            )
        ";
    }

    public function canDepartmentViewDocument($documentId, $departmentId)
    {
        $sql = "
            SELECT COUNT(*)
            FROM documents d
            WHERE d.id = :document_id
            AND " . $this->visibilityWhereClause();

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'document_id' => $documentId,
            'dept' => $departmentId
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getDepartmentRouteRole($documentId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT routing_type AS route_type, status
            FROM document_routes
            WHERE document_id = :document_id
            AND to_department_id = :department_id
            ORDER BY CASE routing_type
                WHEN 'THRU' THEN 1
                WHEN 'TO' THEN 2
                WHEN 'CC' THEN 3
                WHEN 'DELEGATE' THEN 4
                ELSE 5
            END, id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'document_id' => $documentId,
            'department_id' => $departmentId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'route_type' => $row['route_type'],
            'is_cleared' => $row['status'] === 'Received' ? 1 : 0
        ];
    }

    public function getLatestRouteForDepartment($documentId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT r.instructions, r.routed_at, from_dept.division_name AS from_department_name
            FROM document_routes r
            JOIN departments from_dept ON r.from_department_id = from_dept.id
            WHERE r.document_id = :document_id
            AND r.to_department_id = :department_id
            ORDER BY r.id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'document_id' => $documentId,
            'department_id' => $departmentId
        ]);

        $route = $stmt->fetch(PDO::FETCH_ASSOC);
        return $route ?: null;
    }

    public function getLatestRouteRecordForDepartment($documentId, $departmentId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM document_routes
            WHERE document_id = :document_id
            AND to_department_id = :department_id
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'document_id' => $documentId,
            'department_id' => $departmentId
        ]);

        $route = $stmt->fetch(PDO::FETCH_ASSOC);
        return $route ?: null;
    }


    public function getRoutingByDocument($documentId)
    {
        $stmt = $this->db->prepare("
            SELECT
                r.routing_type,
                r.status,
                d.id AS department_id,
                d.division_name
            FROM document_routes r
            JOIN departments d ON r.to_department_id = d.id
            WHERE r.document_id = :document_id
            ORDER BY r.id ASC
        ");

        $stmt->execute(['document_id' => $documentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $routing = [
            'THRU' => [],
            'TO' => [],
            'CC' => [],
            'DELEGATE' => []
        ];

        foreach ($rows as $row) {
            if (!isset($routing[$row['routing_type']])) {
                continue;
            }

            $routing[$row['routing_type']][] = [
                'department_id' => (int) $row['department_id'],
                'division_name' => $row['division_name'],
                'is_cleared' => $row['status'] === 'Received'
            ];
        }

        return $routing;
    }

    public function createDocument($data)
    {
        try {
            $this->db->beginTransaction();

            $departmentId = (int) $data['origin_department_id'];

            $stmt = $this->db->prepare("SELECT code FROM departments WHERE id = :department_id");
            $stmt->execute(['department_id' => $departmentId]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$department) {
                throw new Exception('Invalid Department.');
            }

            $code = $department['code'];
            $year = date('Y');
            $month = date('m');

            $stmt = $this->db->prepare("
                SELECT last_number
                FROM document_sequences
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
                $newNumber = $sequence['last_number'] + 1;
                $update = $this->db->prepare("
                    UPDATE document_sequences
                    SET last_number = :last_number
                    WHERE department_id = :department_id
                    AND year = :year
                    AND month = :month
                ");

                $update->execute([
                    'last_number' => $newNumber,
                    'department_id' => $departmentId,
                    'year' => $year,
                    'month' => $month
                ]);
            } else {
                $newNumber = 1;
                $insertSeq = $this->db->prepare("
                    INSERT INTO document_sequences
                    (department_id, year, month, last_number)
                    VALUES (:department_id, :year, :month, :last_number)
                ");

                $insertSeq->execute([
                    'department_id' => $departmentId,
                    'year' => $year,
                    'month' => $month,
                    'last_number' => $newNumber
                ]);
            }

            $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
            $prefix = "{$code}-{$year}-{$month}-{$formattedNumber}";

            $thruDepartmentId = !empty($data['thru_department_id']) ? (int) $data['thru_department_id'] : null;
            $toDepartmentIds = $data['to_department_ids'] ?? [];
            $ccDepartmentIds = $data['cc_department_ids'] ?? [];
            $delegateDepartmentIds = $data['delegate_department_ids'] ?? [];

            $primaryDestination = !empty($toDepartmentIds)
                ? (int) $toDepartmentIds[0]
                : (!empty($delegateDepartmentIds) ? (int) $delegateDepartmentIds[0] : (int) $data['destination_department_id']);

            $insertDoc = $this->db->prepare("
                INSERT INTO documents (
                    prefix,
                    sequence_number,
                    title,
                    particulars,
                    qr_token,
                    type,
                    origin_department_id,
                    destination_department_id,
                    reference_document_id,
                    created_by,
                    attachment,
                    status
                ) VALUES (
                    :prefix,
                    :sequence_number,
                    :title,
                    :particulars,
                    :qr_token,
                    :type,
                    :origin_department_id,
                    :destination_department_id,
                    :reference_document_id,
                    :created_by,
                    :attachment,
                    'Draft'
                )
            ");

            $insertDoc->execute([
                'prefix' => $prefix,
                'sequence_number' => $newNumber,
                'title' => $data['title'],
                'particulars' => $data['particulars'] !== '' ? $data['particulars'] : null,
                // Temporarily Disabled – QR Code Printing Feature
                'qr_token' => $data['qr_token'] ?? ((defined('ENABLE_QR_PRINT') && ENABLE_QR_PRINT === true) ? $this->generateQrToken() : null),
                'type' => $data['type'],
                'origin_department_id' => $departmentId,
                'destination_department_id' => $primaryDestination,
                'reference_document_id' => !empty($data['reference_document_id']) ? (int) $data['reference_document_id'] : null,
                'created_by' => $data['created_by'],
                'attachment' => $data['attachment'] ?? null
            ]);

            $documentId = (int) $this->db->lastInsertId();

            if ($thruDepartmentId) {
                $this->insertRoute($documentId, $departmentId, $thruDepartmentId, 'THRU');
            }

            foreach ($toDepartmentIds as $toDepartmentId) {
                $this->insertRoute($documentId, $departmentId, (int) $toDepartmentId, 'TO');
            }

            foreach ($ccDepartmentIds as $ccDepartmentId) {
                $this->insertRoute($documentId, $departmentId, (int) $ccDepartmentId, 'CC');
            }

            foreach ($delegateDepartmentIds as $delegateDepartmentId) {
                $this->insertRoute($documentId, $departmentId, (int) $delegateDepartmentId, 'DELEGATE');
            }

            $log = $this->db->prepare("
                INSERT INTO document_logs (
                    document_id,
                    action,
                    action_by,
                    department_id,
                    remarks
                ) VALUES (
                    :document_id,
                    'Created',
                    :action_by,
                    :department_id,
                    :remarks
                )
            ");

            $remarks = ($thruDepartmentId || !empty($toDepartmentIds) || !empty($ccDepartmentIds) || !empty($delegateDepartmentIds))
                ? 'Document created with routing'
                : 'Document created';

            $log->execute([
                'document_id' => $documentId,
                'action_by' => $data['created_by'],
                'department_id' => $departmentId,
                'remarks' => $remarks
            ]);

            $this->db->commit();
            return $prefix;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function updateDraftDocument($documentId, $data)
    {
        try {
            $this->db->beginTransaction();

            $existingDocument = $this->findById($documentId);
            if (!$existingDocument) {
                throw new Exception('Document not found.');
            }

            if (($existingDocument['status'] ?? '') !== 'Draft') {
                throw new Exception('Only draft documents can be edited.');
            }

            $departmentId = (int) $existingDocument['origin_department_id'];
            $thruDepartmentId = !empty($data['thru_department_id']) ? (int) $data['thru_department_id'] : null;
            $toDepartmentIds = array_values(array_map('intval', $data['to_department_ids'] ?? []));
            $ccDepartmentIds = array_values(array_map('intval', $data['cc_department_ids'] ?? []));
            $delegateDepartmentIds = array_values(array_map('intval', $data['delegate_department_ids'] ?? []));
            $primaryDestination = !empty($toDepartmentIds)
                ? (int) $toDepartmentIds[0]
                : (!empty($delegateDepartmentIds) ? (int) $delegateDepartmentIds[0] : (int) $existingDocument['destination_department_id']);
            $attachment = array_key_exists('attachment', $data)
                ? $data['attachment']
                : $existingDocument['attachment'];

            $updateDoc = $this->db->prepare("
                UPDATE documents
                SET title = :title,
                    particulars = :particulars,
                    type = :type,
                    destination_department_id = :destination_department_id,
                    reference_document_id = :reference_document_id,
                    attachment = :attachment
                WHERE id = :id
            ");

            $updateDoc->execute([
                'id' => $documentId,
                'title' => $data['title'],
                'particulars' => $data['particulars'] !== '' ? $data['particulars'] : null,
                'type' => $data['type'],
                'destination_department_id' => $primaryDestination,
                'reference_document_id' => !empty($data['reference_document_id']) ? (int) $data['reference_document_id'] : null,
                'attachment' => $attachment
            ]);

            $deleteRoutes = $this->db->prepare("
                DELETE FROM document_routes
                WHERE document_id = :document_id
                AND routing_type IN ('THRU', 'TO', 'CC', 'DELEGATE')
            ");
            $deleteRoutes->execute(['document_id' => $documentId]);

            if ($thruDepartmentId) {
                $this->insertRoute($documentId, $departmentId, $thruDepartmentId, 'THRU');
            }

            foreach ($toDepartmentIds as $toDepartmentId) {
                $this->insertRoute($documentId, $departmentId, (int) $toDepartmentId, 'TO');
            }

            foreach ($ccDepartmentIds as $ccDepartmentId) {
                $this->insertRoute($documentId, $departmentId, (int) $ccDepartmentId, 'CC');
            }

            foreach ($delegateDepartmentIds as $delegateDepartmentId) {
                $this->insertRoute($documentId, $departmentId, (int) $delegateDepartmentId, 'DELEGATE');
            }

            $remarks = ($thruDepartmentId || !empty($toDepartmentIds) || !empty($ccDepartmentIds) || !empty($delegateDepartmentIds))
                ? 'Draft document updated with routing'
                : 'Draft document updated';

            $log = $this->db->prepare("
                INSERT INTO document_logs (
                    document_id,
                    action,
                    action_by,
                    department_id,
                    remarks
                ) VALUES (
                    :document_id,
                    'Updated',
                    :action_by,
                    :department_id,
                    :remarks
                )
            ");

            $log->execute([
                'document_id' => $documentId,
                'action_by' => $data['updated_by'],
                'department_id' => $departmentId,
                'remarks' => $remarks
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function findByPrefix($prefix)
    {
        $stmt = $this->db->prepare("
            SELECT d.*,
                   o.division_name AS origin_division,
                   dest.division_name AS destination_division
            FROM documents d
            JOIN departments o ON d.origin_department_id = o.id
            LEFT JOIN departments dest ON d.destination_department_id = dest.id
            WHERE d.prefix = :prefix
        ");

        $stmt->execute(['prefix' => $prefix]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function receiveDocument($id, $userId, $departmentId)
    {
        try {
            $this->db->beginTransaction();

            $route = $this->getDepartmentRouteRole($id, $departmentId);

            if ($route) {
                if ((int) $route['is_cleared'] === 1) {
                    $this->db->commit();
                    return true;
                }

                if ($route['route_type'] !== 'THRU' && !$this->isThruCleared($id)) {
                    throw new Exception('Document is waiting for THRU clearance.');
                }

                $updateRoute = $this->db->prepare("
                    UPDATE document_routes
                    SET status = 'Received',
                        received_at = NOW()
                    WHERE document_id = :document_id
                    AND to_department_id = :department_id
                    AND routing_type = :routing_type
                    AND status = 'Pending'
                ");

                $updateRoute->execute([
                    'document_id' => $id,
                    'department_id' => $departmentId,
                    'routing_type' => $route['route_type']
                ]);

                $action = 'Received';
                $remarks = 'Document received';


                $log = $this->db->prepare("
                    INSERT INTO document_logs
                    (document_id, action, action_by, department_id, remarks)
                    VALUES
                    (:document_id, :action, :action_by, :department_id, :remarks)
                ");

                $log->execute([
                    'document_id' => $id,
                    'action' => $action,
                    'action_by' => $userId,
                    'department_id' => $departmentId,
                    'remarks' => $remarks
                ]);

                $allPrimaryRoutesReceived = ($route['route_type'] === 'TO' && $this->areAllToReceived($id))
                    || ($route['route_type'] === 'DELEGATE' && !$this->hasToRoute($id) && $this->areAllDelegateReceived($id));

                if ($allPrimaryRoutesReceived) {
                    $markDoc = $this->db->prepare("
                        UPDATE documents
                        SET status = 'Received',
                            received_by = :user_id,
                            received_at = NOW()
                        WHERE id = :id
                    ");

                    $markDoc->execute([
                        'id' => $id,
                        'user_id' => $userId
                    ]);
                }

                $this->db->commit();
                return true;
            }

            $stmt = $this->db->prepare("
                UPDATE documents
                SET status = 'Received',
                    received_by = :user_id,
                    received_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $id,
                'user_id' => $userId
            ]);

            $log = $this->db->prepare("
                INSERT INTO document_logs
                (document_id, action, action_by, department_id, remarks)
                VALUES
                (:document_id, 'Received', :action_by, :department_id, 'Document received')
            ");

            $log->execute([
                'document_id' => $id,
                'action_by' => $userId,
                'department_id' => $departmentId
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function returnDocument($id, $userId, $departmentId, $returnReason, $attachmentIssue, $remarks)
    {
        try {
            $this->db->beginTransaction();

            $document = $this->findById($id);
            if (!$document) {
                throw new Exception('Document not found.');
            }

            if (($document['status'] ?? '') === 'Returned') {
                throw new Exception('Document has already been returned.');
            }

            $route = $this->getLatestRouteRecordForDepartment($id, $departmentId);
            $routeId = $route ? (int) $route['id'] : null;
            $releasingDepartmentId = $route ? (int) $route['from_department_id'] : (int) $document['origin_department_id'];

            if ($route && ($route['status'] ?? '') !== 'Pending') {
                throw new Exception('Only pending documents can be returned.');
            }

            if (!$route && (
                (int) $document['destination_department_id'] !== (int) $departmentId
                || !in_array($document['status'] ?? '', ['Released', 'Re-released'], true)
            )) {
                throw new Exception('Only released documents can be returned.');
            }

            $updateDocument = $this->db->prepare("
                UPDATE documents
                SET status = 'Returned'
                WHERE id = :id
            ");
            $updateDocument->execute(['id' => $id]);

            if ($routeId !== null) {
                $updateRoute = $this->db->prepare("
                    UPDATE document_routes
                    SET status = 'Returned'
                    WHERE id = :id
                ");
                $updateRoute->execute(['id' => $routeId]);
            }

            $insertReturn = $this->db->prepare("
                INSERT INTO document_returns (
                    document_id,
                    route_id,
                    returned_by,
                    returned_department_id,
                    releasing_department_id,
                    return_reason,
                    attachment_issue,
                    remarks
                ) VALUES (
                    :document_id,
                    :route_id,
                    :returned_by,
                    :returned_department_id,
                    :releasing_department_id,
                    :return_reason,
                    :attachment_issue,
                    :remarks
                )
            ");

            $insertReturn->execute([
                'document_id' => $id,
                'route_id' => $routeId,
                'returned_by' => $userId,
                'returned_department_id' => $departmentId,
                'releasing_department_id' => $releasingDepartmentId,
                'return_reason' => $returnReason,
                'attachment_issue' => $attachmentIssue !== '' ? $attachmentIssue : null,
                'remarks' => $remarks
            ]);

            $returnId = (int) $this->db->lastInsertId();
            $issueLine = $attachmentIssue !== '' ? "Attachment issue: {$attachmentIssue}\n" : '';

            $this->addDocumentLog(
                $id,
                'Returned',
                $userId,
                $departmentId,
                "Returned due to attachment issue\nReason: {$returnReason}\n{$issueLine}Details: {$remarks}"
            );

            $this->db->commit();
            return $returnId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getOpenReturn($documentId)
    {
        $stmt = $this->db->prepare("
            SELECT
                dr.*,
                returned_dept.division_name AS returned_department_name,
                releasing_dept.division_name AS releasing_department_name,
                CONCAT(u.firstname, ' ', IFNULL(CONCAT(u.middle_initial, '. '), ''), u.lastname) AS returned_by_name
            FROM document_returns dr
            JOIN departments returned_dept ON dr.returned_department_id = returned_dept.id
            JOIN departments releasing_dept ON dr.releasing_department_id = releasing_dept.id
            JOIN users u ON dr.returned_by = u.id
            WHERE dr.document_id = :document_id
            AND dr.status = 'Open'
            ORDER BY dr.returned_at DESC, dr.id DESC
            LIMIT 1
        ");

        $stmt->execute(['document_id' => $documentId]);
        $return = $stmt->fetch(PDO::FETCH_ASSOC);
        return $return ?: null;
    }

    public function getAttachmentHistory($documentId)
    {
        $stmt = $this->db->prepare("
            SELECT
                ah.*,
                ret.return_reason,
                CONCAT(u.firstname, ' ', IFNULL(CONCAT(u.middle_initial, '. '), ''), u.lastname) AS uploaded_by_name
            FROM document_attachment_history ah
            LEFT JOIN document_returns ret ON ah.return_id = ret.id
            JOIN users u ON ah.uploaded_by = u.id
            WHERE ah.document_id = :document_id
            ORDER BY ah.uploaded_at DESC, ah.id DESC
        ");

        $stmt->execute(['document_id' => $documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasReplacementForReturn($returnId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_attachment_history
            WHERE return_id = :return_id
        ");

        $stmt->execute(['return_id' => $returnId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function replaceReturnedAttachment($documentId, $newFilename, $userId, $departmentId, $replacementReason)
    {
        try {
            $this->db->beginTransaction();

            $document = $this->findById($documentId);
            $openReturn = $this->getOpenReturn($documentId);

            if (!$document || !$openReturn) {
                throw new Exception('Returned document not found.');
            }

            if ((int) $openReturn['releasing_department_id'] !== (int) $departmentId) {
                throw new Exception('Only the releasing department can replace this attachment.');
            }

            $oldFilename = $document['attachment'] ?? null;

            $clearActive = $this->db->prepare("
                UPDATE document_attachment_history
                SET is_active = 0
                WHERE document_id = :document_id
            ");
            $clearActive->execute(['document_id' => $documentId]);

            $updateDocument = $this->db->prepare("
                UPDATE documents
                SET attachment = :attachment
                WHERE id = :id
            ");
            $updateDocument->execute([
                'id' => $documentId,
                'attachment' => $newFilename
            ]);

            $insertHistory = $this->db->prepare("
                INSERT INTO document_attachment_history (
                    document_id,
                    return_id,
                    old_filename,
                    new_filename,
                    uploaded_by,
                    replacement_reason,
                    is_active
                ) VALUES (
                    :document_id,
                    :return_id,
                    :old_filename,
                    :new_filename,
                    :uploaded_by,
                    :replacement_reason,
                    1
                )
            ");

            $insertHistory->execute([
                'document_id' => $documentId,
                'return_id' => (int) $openReturn['id'],
                'old_filename' => $oldFilename,
                'new_filename' => $newFilename,
                'uploaded_by' => $userId,
                'replacement_reason' => $replacementReason
            ]);

            $this->addDocumentLog(
                $documentId,
                'Attachment Replaced',
                $userId,
                $departmentId,
                $openReturn['return_reason'] ?: $replacementReason
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function reReleaseReturnedDocument($documentId, $userId, $departmentId)
    {
        try {
            $this->db->beginTransaction();

            $openReturn = $this->getOpenReturn($documentId);
            if (!$openReturn) {
                throw new Exception('No open return record found.');
            }

            if ((int) $openReturn['releasing_department_id'] !== (int) $departmentId) {
                throw new Exception('Only the releasing department can re-release this document.');
            }

            if (!$this->hasReplacementForReturn((int) $openReturn['id'])) {
                throw new Exception('Upload a corrected attachment before re-releasing this document.');
            }

            $updateDocument = $this->db->prepare("
                UPDATE documents
                SET status = 'Re-released',
                    released_by = :released_by,
                    released_at = NOW(),
                    received_by = NULL,
                    received_at = NULL
                WHERE id = :id
            ");

            $updateDocument->execute([
                'id' => $documentId,
                'released_by' => $userId
            ]);

            if (!empty($openReturn['route_id'])) {
                $updateRoute = $this->db->prepare("
                    UPDATE document_routes
                    SET status = 'Pending',
                        received_at = NULL,
                        routed_at = NOW()
                    WHERE id = :route_id
                ");
                $updateRoute->execute(['route_id' => (int) $openReturn['route_id']]);
            }

            $resolveReturn = $this->db->prepare("
                UPDATE document_returns
                SET status = 'Resolved',
                    resolved_at = NOW(),
                    resolved_by = :resolved_by
                WHERE id = :id
            ");

            $resolveReturn->execute([
                'id' => (int) $openReturn['id'],
                'resolved_by' => $userId
            ]);

            $this->addDocumentLog(
                $documentId,
                'Re-released',
                $userId,
                $departmentId,
                'Document Re-released'
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM documents WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByQrToken($qrToken)
    {
        $stmt = $this->db->prepare("
            SELECT d.*,
                   origin.division_name AS origin_division,
                   destination.division_name AS destination_division
            FROM documents d
            JOIN departments origin ON d.origin_department_id = origin.id
            LEFT JOIN departments destination ON d.destination_department_id = destination.id
            WHERE d.qr_token = :qr_token
            LIMIT 1
        ");

        $stmt->execute(['qr_token' => $qrToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByCreator($user_id)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents
            WHERE created_by = :user_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getToRelease($department_id)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents
            WHERE origin_department_id = :department_id
            AND status = 'Draft'
            ORDER BY created_at DESC
        ");
        $stmt->execute(['department_id' => $department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getToReceive($department_id)
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT d.*
            FROM documents d
            LEFT JOIN document_routes r
                ON d.id = r.document_id
                AND r.to_department_id = :department_id
            WHERE d.status IN ('Released', 'Re-released', 'Received')
            AND (
                (r.routing_type = 'THRU' AND r.status = 'Pending')
                OR (
                    r.routing_type IN ('TO','CC','DELEGATE')
                    AND r.status = 'Pending'
                    AND (
                        NOT EXISTS (
                            SELECT 1
                            FROM document_routes t
                            WHERE t.document_id = d.id
                            AND t.routing_type = 'THRU'
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM document_routes t
                            WHERE t.document_id = d.id
                            AND t.routing_type = 'THRU'
                            AND t.status = 'Received'
                        )
                    )
                )
                OR (
                    r.id IS NULL
                    AND d.destination_department_id = :department_id
                    AND d.status IN ('Released', 'Re-released')
                )
            )
            ORDER BY d.released_at DESC
        ");

        $stmt->execute(['department_id' => $department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOutgoingByDepartment($department_id, $filters = [])
    {
        $sql = "
            SELECT DISTINCT d.*
            FROM documents d
            WHERE d.origin_department_id = :department_id
        ";

        $params = ['department_id' => $department_id];
        $sql .= $this->buildDocumentFilterClause($filters, $params);
        $sql .= " ORDER BY COALESCE(d.released_at, d.created_at) DESC, d.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getIncomingByDepartment($department_id, $filters = [])
    {
        $sql = "
            SELECT DISTINCT d.*
            FROM documents d
            LEFT JOIN document_routes r
                ON d.id = r.document_id
                AND r.to_department_id = :department_id
            WHERE d.status IN ('Released', 'Re-released', 'Received')
            AND (
                (r.routing_type = 'THRU' AND r.status = 'Pending')
                OR (
                    r.routing_type IN ('TO','CC','DELEGATE')
                    AND r.status = 'Pending'
                    AND (
                        NOT EXISTS (
                            SELECT 1
                            FROM document_routes t
                            WHERE t.document_id = d.id
                            AND t.routing_type = 'THRU'
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM document_routes t
                            WHERE t.document_id = d.id
                            AND t.routing_type = 'THRU'
                            AND t.status = 'Received'
                        )
                    )
                )
                OR (
                    r.id IS NULL
                    AND d.destination_department_id = :department_id
                    AND d.status IN ('Released', 'Re-released')
                )
            )
        ";

        $params = ['department_id' => $department_id];
        $sql .= $this->buildDocumentFilterClause($filters, $params);
        $sql .= " ORDER BY d.released_at DESC, d.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildDocumentFilterClause($filters, &$params)
    {
        $sql = '';

        $keyword = trim($filters['keyword'] ?? '');
        $status = trim($filters['status'] ?? '');
        $type = trim($filters['type'] ?? '');
        $dateFrom = trim($filters['date_from'] ?? '');
        $dateTo = trim($filters['date_to'] ?? '');

        if ($keyword !== '') {
            $sql .= " AND (d.prefix LIKE :keyword OR d.title LIKE :keyword OR d.particulars LIKE :keyword)";
            $params['keyword'] = "%{$keyword}%";
        }

        if ($status !== '') {
            $sql .= " AND d.status = :status";
            $params['status'] = $status;
        }

        if ($type !== '') {
            $sql .= " AND d.type = :type";
            $params['type'] = $type;
        }

        if ($dateFrom !== '') {
            $sql .= " AND DATE(COALESCE(d.released_at, d.created_at)) >= :date_from";
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND DATE(COALESCE(d.released_at, d.created_at)) <= :date_to";
            $params['date_to'] = $dateTo;
        }

        return $sql;
    }

    public function getRecent($limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByDepartment($department_id)
    {
        $sql = "
            SELECT DISTINCT d.*
            FROM documents d
            WHERE " . $this->visibilityWhereClause() . "
            ORDER BY
                CASE d.status
                    WHEN 'Released' THEN 1
                    WHEN 'Re-released' THEN 2
                    WHEN 'Draft' THEN 3
                    WHEN 'Returned' THEN 4
                    WHEN 'Received' THEN 5
                    ELSE 6
                END,
                COALESCE(d.released_at, d.created_at) DESC,
                d.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dept' => $department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLogs($document_id)
    {
        $stmt = $this->db->prepare("
            SELECT
                dl.*, CONCAT(
                    u.firstname, ' ',
                    IFNULL(CONCAT(u.middle_initial, '. '), ''),
                    u.lastname
                ) AS full_name,
                d.division_name
            FROM document_logs dl
            JOIN users u ON dl.action_by = u.id
            JOIN departments d ON dl.department_id = d.id
            WHERE dl.document_id = :document_id
            ORDER BY dl.timestamp ASC
        ");

        $stmt->execute(['document_id' => $document_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByDepartment($department_id)
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM documents d
            WHERE " . $this->visibilityWhereClause();

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dept' => $department_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function countAll()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM documents");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function countByStatus($status)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM documents WHERE status = :status");
        $stmt->execute(['status' => $status]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function search($department_id, $filters = [])
    {
        $sql = "
            SELECT DISTINCT d.*
            FROM documents d
            WHERE " . $this->visibilityWhereClause();

        $params = ['dept' => $department_id];

        $keyword = trim($filters['keyword'] ?? '');
        $status = trim($filters['status'] ?? '');
        $type = trim($filters['type'] ?? '');
        $dateFrom = trim($filters['date_from'] ?? '');
        $dateTo = trim($filters['date_to'] ?? '');

        if ($keyword !== '') {
            $sql .= " AND (d.prefix LIKE :keyword OR d.title LIKE :keyword OR d.particulars LIKE :keyword)";
            $params['keyword'] = "%{$keyword}%";
        }

        if ($status !== '') {
            $sql .= " AND d.status = :status";
            $params['status'] = $status;
        }

        if ($type !== '') {
            $sql .= " AND d.type = :type";
            $params['type'] = $type;
        }

        if ($dateFrom !== '') {
            $sql .= " AND DATE(COALESCE(d.released_at, d.created_at)) >= :date_from";
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND DATE(COALESCE(d.released_at, d.created_at)) <= :date_to";
            $params['date_to'] = $dateTo;
        }

        $sql .= "
            ORDER BY
                CASE d.status
                    WHEN 'Released' THEN 1
                    WHEN 'Re-released' THEN 2
                    WHEN 'Draft' THEN 3
                    WHEN 'Returned' THEN 4
                    WHEN 'Received' THEN 5
                    ELSE 6
                END,
                COALESCE(d.released_at, d.created_at) DESC,
                d.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableTypes($department_id)
    {
        $sql = "
            SELECT DISTINCT d.type
            FROM documents d
            WHERE d.type IS NOT NULL
            AND d.type <> ''
            AND " . $this->visibilityWhereClause() . "
            ORDER BY d.type ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dept' => $department_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getAllByDepartment($department_id)
    {
        return $this->getByDepartment($department_id);
    }

    public function getDocumentLogs($document_id)
    {
        $stmt = $this->db->prepare("
            SELECT
                dl.*,
                CONCAT(
                    u.firstname, ' ',
                    IFNULL(CONCAT(u.middle_initial, '. '), ''),
                    u.lastname
                ) AS user_name,
                d.division_name AS department_name
            FROM document_logs dl
            JOIN users u ON dl.action_by = u.id
            JOIN departments d ON dl.department_id = d.id
            WHERE dl.document_id = :document_id
            ORDER BY dl.timestamp ASC
        ");

        $stmt->execute(['document_id' => $document_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function releaseDocument($id, $user_id, $department_id)
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE documents
                SET status = 'Released',
                    released_by = :user_id,
                    released_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $id,
                'user_id' => $user_id
            ]);

            $log = $this->db->prepare("
                INSERT INTO document_logs
                (document_id, action, action_by, department_id, remarks)
                VALUES
                (:document_id, 'Released', :action_by, :department_id, 'Document released')
            ");

            $log->execute([
                'document_id' => $id,
                'action_by' => $user_id,
                'department_id' => $department_id
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function forwardDocument($id, $new_department_ids, $user_id, $current_department_id, $actionSlip = [])
    {
        try {
            $this->db->beginTransaction();

            $new_department_ids = array_values(array_unique(array_filter(array_map('intval', (array) $new_department_ids))));
            if (empty($new_department_ids)) {
                throw new Exception('No forward targets selected.');
            }

            $actionSlipText = $this->formatActionSlipText($actionSlip);

            $stmt = $this->db->prepare("
                UPDATE documents
                SET destination_department_id = :new_department_id,
                    status = 'Released',
                    received_by = NULL,
                    received_at = NULL
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $id,
                'new_department_id' => $new_department_ids[0]
            ]);

            $clearTo = $this->db->prepare("
                UPDATE document_routes
                SET status = 'Received',
                    received_at = NOW()
                WHERE document_id = :document_id
                AND to_department_id = :department_id
                AND routing_type IN ('TO', 'DELEGATE')
                AND status = 'Pending'
            ");

            $clearTo->execute([
                'document_id' => $id,
                'department_id' => $current_department_id
            ]);

            $existing = $this->db->prepare("
                SELECT id
                FROM document_routes
                WHERE document_id = :document_id
                AND to_department_id = :to_department_id
                AND routing_type = :routing_type
                ORDER BY id DESC
                LIMIT 1
            ");

            $reset = $this->db->prepare("
                UPDATE document_routes
                SET from_department_id = :from_department_id,
                    instructions = :instructions,
                    status = 'Pending',
                    received_at = NULL,
                    routed_at = NOW()
                WHERE id = :id
            ");

            foreach ($new_department_ids as $targetDepartmentId) {
                $routingType = $this->isChildDepartmentOf($targetDepartmentId, $current_department_id)
                    ? 'DELEGATE'
                    : 'TO';

                $existing->execute([
                    'document_id' => $id,
                    'to_department_id' => $targetDepartmentId,
                    'routing_type' => $routingType
                ]);

                $existingId = $existing->fetchColumn();

                if ($existingId) {
                    $reset->execute([
                        'id' => $existingId,
                        'from_department_id' => $current_department_id,
                        'instructions' => $actionSlipText
                    ]);
                } else {
                    $this->insertRoute($id, $current_department_id, $targetDepartmentId, $routingType, $actionSlipText);
                }
            }

            $codePlaceholders = implode(', ', array_fill(0, count($new_department_ids), '?'));
            $codeStmt = $this->db->prepare("SELECT code FROM departments WHERE id IN ($codePlaceholders) ORDER BY code ASC");
            $codeStmt->execute($new_department_ids);
            $targetCodes = $codeStmt->fetchAll(PDO::FETCH_COLUMN);
            $targetSummary = !empty($targetCodes)
                ? implode(', ', $targetCodes)
                : implode(', ', $new_department_ids);

            $log = $this->db->prepare("
                INSERT INTO document_logs
                (document_id, action, action_by, department_id, remarks)
                VALUES
                (:document_id, 'Forwarded', :action_by, :department_id, :remarks)
            ");

            $log->execute([
                'document_id' => $id,
                'action_by' => $user_id,
                'department_id' => $current_department_id,
                'remarks' => "Document forwarded to departments: " . $targetSummary . "\n" . $actionSlipText
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
    public function hasDelegatedToChild($document_id, $parent_department_id)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_routes dr
            JOIN departments d ON dr.to_department_id = d.id
            WHERE dr.document_id = :document_id
            AND dr.from_department_id = :parent_department_id
            AND dr.routing_type = 'DELEGATE'
            AND d.parent_id = :parent_department_id
        ");

        $stmt->execute([
            'document_id' => $document_id,
            'parent_department_id' => $parent_department_id
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
    public function getAllDepartments()
    {
        $stmt = $this->db->query("SELECT * FROM departments ORDER BY division_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function departmentHandledDocument($document_id, $department_id)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_logs
            WHERE document_id = :doc_id
            AND department_id = :dept_id
        ");

        $stmt->execute([
            'doc_id' => $document_id,
            'dept_id' => $department_id
        ]);

        return $stmt->fetchColumn() > 0;
    }

    public function departmentHandledDocumentByOtherUser($document_id, $department_id, $exclude_user_id)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM document_logs
            WHERE document_id = :doc_id
            AND department_id = :dept_id
            AND action_by <> :exclude_user_id
        ");

        $stmt->execute([
            'doc_id' => $document_id,
            'dept_id' => $department_id,
            'exclude_user_id' => $exclude_user_id
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function departmentHandledDocumentWithActions($document_id, $department_id, $actions = [])
    {
        $sql = "
            SELECT COUNT(*)
            FROM document_logs
            WHERE document_id = :doc_id
            AND department_id = :dept_id
        ";

        $params = [
            'doc_id' => $document_id,
            'dept_id' => $department_id
        ];

        if (!empty($actions)) {
            $placeholders = [];
            foreach (array_values($actions) as $index => $action) {
                $key = 'action_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $action;
            }

            $sql .= " AND action IN (" . implode(', ', $placeholders) . ")";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function userHandledDocument($document_id, $department_id, $user_id, $actions = [])
    {
        $sql = "
            SELECT COUNT(*)
            FROM document_logs
            WHERE document_id = :doc_id
            AND department_id = :dept_id
            AND action_by = :user_id
        ";

        $params = [
            'doc_id' => $document_id,
            'dept_id' => $department_id,
            'user_id' => $user_id
        ];

        if (!empty($actions)) {
            $placeholders = [];
            foreach (array_values($actions) as $index => $action) {
                $key = 'action_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $action;
            }

            $sql .= " AND action IN (" . implode(', ', $placeholders) . ")";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addDocumentLog($document_id, $action, $user_id, $department_id, $remarks)
    {
        $stmt = $this->db->prepare("
            INSERT INTO document_logs
            (document_id, action, action_by, department_id, remarks)
            VALUES
            (:document_id, :action, :action_by, :department_id, :remarks)
        ");

        return $stmt->execute([
            'document_id' => $document_id,
            'action' => $action,
            'action_by' => $user_id,
            'department_id' => $department_id,
            'remarks' => $remarks
        ]);
    }
}

