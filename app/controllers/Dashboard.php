<?php

class Dashboard extends Controller
{
    public function __construct()
    {
        requireLogin();
    }

    public function index()
    {
        try {
            $documentModel = $this->model('Document');

            $data = [
                'total' => $documentModel->countAll(),
                'pending' => $documentModel->countByStatus('Pending'),
                'completed' => $documentModel->countByStatus('Completed'),
                'department_docs' => $documentModel->countByDepartment($_SESSION['department_id'])
            ];

            $this->view('dashboard/index', $data);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'dashboard.index', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('error', 'We could not load the dashboard right now.', 'error');
            redirect('/documents', 303);
        }
    }
}
