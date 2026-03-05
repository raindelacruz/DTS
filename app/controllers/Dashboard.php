<?php

class Dashboard extends Controller
{
    public function index()
    {
        $documentModel = $this->model('Document');

        $data = [
            'total' => $documentModel->countAll(),
            'pending' => $documentModel->countByStatus('Pending'),
            'completed' => $documentModel->countByStatus('Completed'),
            'department_docs' => $documentModel->countByDepartment($_SESSION['department_id'])
        ];

        $this->view('dashboard/index', $data);
    }
}