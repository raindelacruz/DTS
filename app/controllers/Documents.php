<?php


require_once '../app/models/Document.php';
require_once '../app/models/Department.php';


class Documents extends Controller
{
    private $documentModel;
    private $departmentModel;


    public function __construct()
    {
        if(!isset($_SESSION['user_id'])) {
            header('Location: ' . URLROOT);
            exit;
        }

        $this->documentModel = new Document();
        $this->departmentModel = new Department();

    }


    /* ============================
       LIST DOCUMENTS
    ============================ */
    public function index()
    {
        $documentModel = $this->model('Document');

        $department_id = $_SESSION['department_id'];

        $keyword = $_GET['keyword'] ?? '';
        $status  = $_GET['status'] ?? '';

        if (!empty($keyword) || !empty($status)) {
            $documents = $documentModel->search($department_id, $keyword, $status);
        } else {
            $documents = $documentModel->getAllByDepartment($department_id);
        }

        $data = [
            'documents' => $documents,
            'keyword'   => $keyword,
            'status'    => $status
        ];

        $this->view('documents/index', $data);
    }

    /* ============================
       CREATE DOCUMENT FORM
    ============================ */
    public function create()
    {
        $departments = $this->departmentModel->getAll();
        require_once '../app/views/documents/create.php';
    }



    /* ============================
       STORE DOCUMENT
    ============================ */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            // -----------------------------
            // Handle File Upload
            // -----------------------------
            $filename = null;

            if (!empty($_FILES['attachment']['name'])) {

                $filename = time() . '_' . basename($_FILES['attachment']['name']);

                // Absolute path to uploads folder
                $uploadPath = dirname(__DIR__, 2) . '/public/uploads/' . $filename;

                if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
                    die('File upload failed.');
                }
            }

            // -----------------------------
            // Prepare Data
            // -----------------------------
            $data = [
                'title' => $_POST['title'],
                'type' => $_POST['type'],
                'origin_department_id' => $_SESSION['department_id'],
                'destination_department_id' => $_POST['destination_department_id'],
                'created_by' => $_SESSION['user_id'],
                'attachment' => $filename   // <-- Added
            ];

            // Create document
            $prefix = $this->documentModel->createDocument($data);

            header('Location: ' . URLROOT . '/documents');
            exit;
        }
    }

    /* ============================
       RELEASE DOCUMENT
    ============================ */
    public function release($id)
    {
        $document = $this->documentModel->findById($id);

        if(!$document) {
            die("Document not found.");
        }

        // Only origin department can release
        if($document['origin_department_id'] != $_SESSION['department_id']) {
            die("Unauthorized action.");
        }

        if($document['status'] != 'Draft') {
            die("Only Draft documents can be released.");
        }

        $this->documentModel->releaseDocument(
            $id,
            $_SESSION['user_id'],
            $_SESSION['department_id']
        );

        header('Location: ' . URLROOT . '/documents');
        exit;
    }


    /* ============================
       RECEIVE DOCUMENT
    ============================ */
    public function receive($id)
    {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            die("Document not found.");
        }

        // Only destination department can receive
        if ($document['destination_department_id'] != $_SESSION['department_id']) {
            die("Unauthorized action.");
        }

        if ($document['status'] !== 'Released') {
            die("Only Released documents can be received.");
        }

        $this->documentModel->receiveDocument(
            $id,
            $_SESSION['user_id'],
            $_SESSION['department_id']
        );

        header('Location: ' . URLROOT . '/documents/show/' . $id);
        exit;
    }

    public function show($id)
    {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            die("Document not found.");
        }

        $dept_id = $_SESSION['department_id'];

        // Check origin or current destination
        $allowed = (
            $document['origin_department_id'] == $dept_id
            ||
            $document['destination_department_id'] == $dept_id
        );

        // If not origin or destination, check logs (lifecycle visibility)
        if (!$allowed) {
            $allowed = $this->documentModel
                            ->departmentHandledDocument($id, $dept_id);
        }

        if (!$allowed) {
            die("Unauthorized access.");
        }

        $logs = $this->documentModel->getDocumentLogs($id);

        require_once '../app/views/documents/view.php';
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

    public function forward($id)
    {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            die("Document not found.");
        }

        // Only department that received it can forward
        if ($document['destination_department_id'] != $_SESSION['department_id']) {
            die("Unauthorized action.");
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $new_department_id = $_POST['department_id'];

            $this->documentModel->forwardDocument(
                $id,
                $new_department_id,
                $_SESSION['user_id'],
                $_SESSION['department_id']
            );

            header('Location: ' . URLROOT . '/documents/show/' . $id);
            exit;
        }

        $departments = $this->documentModel->getAllDepartments();

        require_once '../app/views/documents/forward.php';
    }

}
