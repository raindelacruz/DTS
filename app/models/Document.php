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

            $primaryDestination = !empty($toDepartmentIds)
                ? (int) $toDepartmentIds[0]
                : (int) $data['destination_department_id'];

            $insertDoc = $this->db->prepare("
                INSERT INTO documents (
                    prefix,
                    sequence_number,
                    title,
                    particulars,
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

            $remarks = ($thruDepartmentId || !empty($toDepartmentIds) || !empty($ccDepartmentIds))
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
            $primaryDestination = !empty($toDepartmentIds)
                ? (int) $toDepartmentIds[0]
                : (int) $existingDocument['destination_department_id'];
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
                AND routing_type IN ('THRU', 'TO', 'CC')
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

            $remarks = ($thruDepartmentId || !empty($toDepartmentIds) || !empty($ccDepartmentIds))
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

                if ($route['route_type'] === 'TO' && $this->areAllToReceived($id)) {
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

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM documents WHERE id = :id");
        $stmt->execute(['id' => $id]);
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
            WHERE d.status <> 'Draft'
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
            WHERE d.status <> 'Draft'
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
                    WHEN 'Draft' THEN 2
                    WHEN 'Received' THEN 3
                    ELSE 4
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
                    WHEN 'Draft' THEN 2
                    WHEN 'Received' THEN 3
                    ELSE 4
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

