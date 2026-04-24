<?php

require_once '../app/models/Notification.php';

class Notifications extends Controller
{
    private $notificationModel;

    public function __construct()
    {
        requireLogin();

        $this->notificationModel = new Notification();
    }

    public function read($id)
    {
        $notification = $this->notificationModel->findByIdForUser((int) $id, (int) $_SESSION['user_id']);

        if ($notification) {
            $this->notificationModel->markAsRead((int) $id, (int) $_SESSION['user_id']);
        }

        $redirect = trim($_GET['redirect'] ?? '');
        if ($redirect !== '' && $this->isSafeRedirect($redirect)) {
            header('Location: ' . $redirect);
            exit;
        }

        if (!empty($notification['link'])) {
            header('Location: ' . URLROOT . $notification['link']);
            exit;
        }

        header('Location: ' . URLROOT . '/dashboard');
        exit;
    }

    public function readAll()
    {
        $redirect = $_SERVER['HTTP_REFERER'] ?? (URLROOT . '/dashboard');
        if (!$this->isSafeRedirect($redirect)) {
            $redirect = URLROOT . '/dashboard';
        }

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                throw new ValidationException('Invalid request method.');
            }

            validateCsrfOrFail();
            $this->notificationModel->markAllAsRead((int) $_SESSION['user_id']);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
        } catch (Throwable $e) {
            reportException($e, ['action' => 'notifications.readAll', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('error', 'We could not update notifications right now.', 'error');
        }

        safeRedirect($redirect, '/dashboard', 303);
    }

    private function isSafeRedirect($redirect)
    {
        if ($redirect === '') {
            return false;
        }

        if (strpos($redirect, URLROOT . '/') === 0 || $redirect === URLROOT) {
            return true;
        }

        return strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0;
    }
}
