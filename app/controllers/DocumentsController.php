<?php

class DocumentsController extends Controller
{
    public function create()
    {
        requireLogin();
        allowRoles(['admin','staff','custodian']);

        $departmentModel = $this->model('Department');
        $departments = $departmentModel->getAll();

        $this->view('documents/create', [
            'departments' => $departments
        ]);
    }

    public function store()
    {
        requireLogin();
        allowRoles(['admin','staff','custodian']);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if (empty($_POST['title']) || empty($_POST['type']) || empty($_POST['destination_department_id'])) {
                die("All fields are required.");
            }

            $documentModel = $this->model('Document');

            $prefix = $documentModel->createDocument([
                'title' => trim($_POST['title']),
                'type' => $_POST['type'],
                'origin_department_id' => $_SESSION['department_id'],
                'destination_department_id' => $_POST['destination_department_id'],
                'created_by' => $_SESSION['user_id']
            ]);

            header("Location: /documents/show/" . urlencode($prefix));
            exit;
        }
    }

    public function show($prefix)
    {
        requireLogin();

        $documentModel = $this->model('Document');
        $document = $documentModel->findByPrefix($prefix);

        if (!$document) {
            die("Document not found.");
        }

        $this->view('documents/show', [
            'document' => $document
        ]);
    }

    public function release($id)
    {
        requireLogin();
        requireRole('custodian');

        $documentModel = $this->model('Document');
        $document = $documentModel->findById($id);

        if (!$document) {
            die("Document not found.");
        }

        if ($document['origin_department_id'] != $_SESSION['department_id']) {
            die("You cannot release documents from another division.");
        }

        if ($document['status'] !== 'Draft') {
            die("Only Draft documents can be released.");
        }

        $documentModel->releaseDocument(
            $id,
            $_SESSION['user_id'],
            $_SESSION['department_id']
        );


        header("Location: /documents/show/" . $document['prefix']);
        exit;
    }

    public function receive($id)
    {
        requireLogin();
        requireRole('custodian');

        $documentModel = $this->model('Document');
        $document = $documentModel->findById($id);

        if (!$document) {
            die("Document not found.");
        }

        if ($document['destination_department_id'] != $_SESSION['department_id']) {
            die("You cannot receive this document.");
        }

        if ($document['status'] !== 'Released') {
            die("Only Released documents can be received.");
        }

        $documentModel->receiveDocument(
            $id,
            $_SESSION['user_id'],
            $_SESSION['department_id']
        );


        header("Location: /documents/show/" . $document['prefix']);
        exit;
    }
}


public function index()
{
    requireLogin();

    $documentModel = $this->model('Document');

    $created = $documentModel->getByCreator($_SESSION['user_id']);
    $toRelease = $documentModel->getToRelease($_SESSION['department_id']);
    $toReceive = $documentModel->getToReceive($_SESSION['department_id']);
    $recent = $documentModel->getRecent();

    $this->view('documents/index', [
        'created' => $created,
        'toRelease' => $toRelease,
        'toReceive' => $toReceive,
        'recent' => $recent
    ]);
}
