<?php

class Notification
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

        $this->ensureTable();
    }

    private function ensureTable()
    {
        $this->db->exec(" 
            CREATE TABLE IF NOT EXISTS notifications (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                link VARCHAR(255) DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME DEFAULT NULL,
                KEY user_id (user_id),
                KEY is_read (is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function create($userId, $title, $message, $link = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, link)
            VALUES (:user_id, :title, :message, :link)
        ");

        return $stmt->execute([
            'user_id' => (int) $userId,
            'title' => $title,
            'message' => $message,
            'link' => $link
        ]);
    }

    public function createMany($userIds, $title, $message, $link = null, $excludeUserId = null)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', (array) $userIds))));

        foreach ($userIds as $userId) {
            if ($excludeUserId !== null && $userId === (int) $excludeUserId) {
                continue;
            }

            $this->create($userId, $title, $message, $link);
        }
    }

    public function notifyDepartmentUsers($departmentIds, $title, $message, $link = null, $excludeUserId = null)
    {
        $this->notifyDepartmentRoleUsers($departmentIds, null, $title, $message, $link, $excludeUserId);
    }

    public function notifyDepartmentStaffUsers($departmentIds, $title, $message, $link = null, $excludeUserId = null)
    {
        $departmentIds = array_values(array_unique(array_filter(array_map('intval', (array) $departmentIds))));

        if (empty($departmentIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
        $sql = "
            SELECT id
            FROM users
            WHERE department_id IN ($placeholders)
            AND status = 'active'
            AND role NOT IN ('manager', 'admin')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($departmentIds);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->createMany($userIds, $title, $message, $link, $excludeUserId);
    }


    public function notifyDepartmentManagers($departmentIds, $title, $message, $link = null, $excludeUserId = null)
    {
        $this->notifyDepartmentRoleUsers($departmentIds, 'manager', $title, $message, $link, $excludeUserId);
    }

    public function notifyDepartmentRoleUsers($departmentIds, $role, $title, $message, $link = null, $excludeUserId = null)
    {
        $departmentIds = array_values(array_unique(array_filter(array_map('intval', (array) $departmentIds))));

        if (empty($departmentIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
        $sql = "
            SELECT id
            FROM users
            WHERE department_id IN ($placeholders)
            AND status = 'active'
        ";

        $params = $departmentIds;

        if ($role !== null) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->createMany($userIds, $title, $message, $link, $excludeUserId);
    }

    public function notifyAdmins($title, $message, $link = null, $excludeUserId = null)
    {
        $stmt = $this->db->query(" 
            SELECT id
            FROM users
            WHERE role = 'admin'
            AND status = 'active'
        ");

        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->createMany($userIds, $title, $message, $link, $excludeUserId);
    }

    public function getRecentByUser($userId, $limit = 8)
    {
        $stmt = $this->db->prepare(" 
            SELECT *
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', (int) $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnreadByUser($userId)
    {
        $stmt = $this->db->prepare(" 
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id
            AND is_read = 0
        ");
        $stmt->execute(['user_id' => (int) $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function markAsRead($id, $userId)
    {
        $stmt = $this->db->prepare(" 
            UPDATE notifications
            SET is_read = 1,
                read_at = COALESCE(read_at, NOW())
            WHERE id = :id
            AND user_id = :user_id
        ");

        return $stmt->execute([
            'id' => (int) $id,
            'user_id' => (int) $userId
        ]);
    }

    public function markAllAsRead($userId)
    {
        $stmt = $this->db->prepare(" 
            UPDATE notifications
            SET is_read = 1,
                read_at = COALESCE(read_at, NOW())
            WHERE user_id = :user_id
            AND is_read = 0
        ");

        return $stmt->execute(['user_id' => (int) $userId]);
    }

    public function findByIdForUser($id, $userId)
    {
        $stmt = $this->db->prepare(" 
            SELECT *
            FROM notifications
            WHERE id = :id
            AND user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => (int) $id,
            'user_id' => (int) $userId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
