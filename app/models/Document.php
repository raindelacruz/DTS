<?php

class Document
{
    private $db;

    public function __construct()
    {
        // Using your existing PDO connection style
        $this->db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );
    }

    /* =========================================
       CREATE DOCUMENT WITH AUTO PREFIX
    ========================================= */

    public function createDocument($data)
    {
        try {
            $this->db->beginTransaction();

            $department_id = $data['origin_department_id'];

            // 1️⃣ Get department code
            $stmt = $this->db->prepare("
                SELECT code FROM departments WHERE id = :department_id
            ");
            $stmt->execute(['department_id' => $department_id]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$department) {
                throw new Exception("Invalid Department.");
            }

            $code = $department['code'];
            $year = date('Y');
            $month = date('m');

            // 2️⃣ Lock sequence row
            $stmt = $this->db->prepare("
                SELECT last_number
                FROM document_sequences
                WHERE department_id = :department_id
                AND year = :year
                AND month = :month
                FOR UPDATE
            ");

            $stmt->execute([
                'department_id' => $department_id,
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
                    'department_id' => $department_id,
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
                    'department_id' => $department_id,
                    'year' => $year,
                    'month' => $month,
                    'last_number' => $newNumber
                ]);
            }

            // 3️⃣ Format Prefix
            $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
            $prefix = "{$code}-{$year}-{$month}-{$formattedNumber}";

            // 4️⃣ Insert Document
            $insertDoc = $this->db->prepare("
                INSERT INTO documents (
                    prefix,
                    sequence_number,
                    title,
                    type,
                    origin_department_id,
                    destination_department_id,
                    created_by,
                    attachment,
                    status
                ) VALUES (
                    :prefix,
                    :sequence_number,
                    :title,
                    :type,
                    :origin_department_id,
                    :destination_department_id,
                    :created_by,
                    :attachment,
                    'Draft'
                )
            ");

            $insertDoc->execute([
                'prefix' => $prefix,
                'sequence_number' => $newNumber,
                'title' => $data['title'],
                'type' => $data['type'],
                'origin_department_id' => $department_id,
                'destination_department_id' => $data['destination_department_id'],
                'created_by' => $data['created_by'],
                'attachment' => $data['attachment'] ?? null
            ]);
            $document_id = $this->db->lastInsertId();

            // 5️⃣ Insert Audit Log
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
                    'Document created'
                )
            ");

            $log->execute([
                'document_id' => $document_id,
                'action_by' => $data['created_by'],
                'department_id' => $department_id
            ]);

            $this->db->commit();

            return $prefix;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /* =========================================
       FIND BY PREFIX
    ========================================= */

    public function findByPrefix($prefix)
    {
        $stmt = $this->db->prepare("
            SELECT d.*, 
                   o.division_name AS origin_division,
                   dest.division_name AS destination_division
            FROM documents d
            JOIN departments o ON d.origin_department_id = o.id
            JOIN departments dest ON d.destination_department_id = dest.id
            WHERE d.prefix = :prefix
        ");

        $stmt->execute(['prefix' => $prefix]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    

    /* =========================================
       RECEIVE DOCUMENT
    ========================================= */

    public function receiveDocument($id, $user_id, $department_id)
    {
        try {
            $this->db->beginTransaction();

            // Update document
            $stmt = $this->db->prepare("
                UPDATE documents
                SET status = 'Received',
                    received_by = :user_id,
                    received_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $id,
                'user_id' => $user_id
            ]);

            // Insert log
            $log = $this->db->prepare("
                INSERT INTO document_logs
                (document_id, action, action_by, department_id, remarks)
                VALUES
                (:document_id, 'Received', :action_by, :department_id, 'Document received')
            ");

            $log->execute([
                'document_id' => $id,
                'action_by' => $user_id,
                'department_id' => $department_id
            ]);

            $this->db->commit();

            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /* =========================================
       FIND BY ID
    ========================================= */

    public function findById($id)
        {
            $stmt = $this->db->prepare("
                SELECT * FROM documents WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
    }

   
    /* =========================================
       GET DOCUMENTS CREATED BY USER
    ========================================= */

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

    /* =========================================
       GET DOCUMENTS TO RELEASE
    ========================================= */

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

    /* =========================================
       GET DOCUMENTS TO RECEIVE
    ========================================= */

    public function getToReceive($department_id)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents
            WHERE destination_department_id = :department_id
            AND status = 'Released'
            ORDER BY released_at DESC
        ");
        $stmt->execute(['department_id' => $department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================
       GET RECENT DOCUMENTS
    ========================================= */

    public function getRecent($limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* =========================================
       GET DOCUMENTS BY DEPARTMENT
    ========================================= */

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


    /* =========================================
       GET DOCUMENT LOGS
    ========================================= */

    public function getLogs($document_id)
    {
        $stmt = $this->db->prepare("
            SELECT 
                dl.*,
                CONCAT(
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
            FROM documents 
            WHERE origin_department_id = :dept 
            OR destination_department_id = :dept
        ";

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


    public function search($department_id, $keyword = '', $status = '')
    {
        $sql = "
            SELECT * 
            FROM documents 
            WHERE (origin_department_id = :dept 
            OR destination_department_id = :dept)
        ";

        $params = ['dept' => $department_id];

        if (!empty($keyword)) {
            $sql .= " AND (prefix LIKE :keyword OR title LIKE :keyword)";
            $params['keyword'] = "%$keyword%";
        }

        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllByDepartment($department_id)
    {
        $sql = "
            SELECT DISTINCT d.*
            FROM documents d
            LEFT JOIN document_logs l 
                ON d.id = l.document_id
            WHERE d.origin_department_id = :dept
               OR d.destination_department_id = :dept
               OR l.department_id = :dept
            ORDER BY d.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dept' => $department_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                d.department_name AS department_name
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

            // Update document
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

            // Insert log
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
            $this->db->rollBack();
            throw $e;
        }
    }

    public function forwardDocument($id, $new_department_id, $user_id, $current_department_id)
    {
        try {
            $this->db->beginTransaction();

            // Update destination + reset receive fields
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
                'new_department_id' => $new_department_id
            ]);

            // Insert log
            $log = $this->db->prepare("
                INSERT INTO document_logs
                (document_id, action, action_by, department_id, remarks)
                VALUES
                (:document_id, 'Forwarded', :action_by, :department_id, 'Document forwarded')
            ");

            $log->execute([
                'document_id' => $id,
                'action_by' => $user_id,
                'department_id' => $current_department_id
            ]);

            $this->db->commit();

            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getAllDepartments()
    {
        $stmt = $this->db->query("SELECT * FROM departments ORDER BY department_name ASC");
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

}