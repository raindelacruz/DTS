<?php

require_once '../app/models/Document.php';
require_once '../app/models/Department.php';

class Verification extends Controller
{
    private $documentModel;
    private $departmentModel;

    public function __construct()
    {
        $this->documentModel = new Document();
        $this->departmentModel = new Department();
    }

    private function isAuthorizedViewer($document)
    {
        if (empty($_SESSION['user_id']) || empty($_SESSION['department_id'])) {
            return false;
        }

        $departmentId = (int) $_SESSION['department_id'];
        $allowed = $this->documentModel->canDepartmentViewDocument((int) $document['id'], $departmentId);

        if (!$allowed) {
            return false;
        }

        $isManager = (($_SESSION['role'] ?? '') === 'manager');
        if (!$isManager) {
            return true;
        }

        if ((int) $document['origin_department_id'] === $departmentId) {
            return true;
        }

        return $this->documentModel->departmentHandledDocumentByOtherUser(
            (int) $document['id'],
            $departmentId,
            (int) $_SESSION['user_id']
        );
    }

    public function verify($qrToken = '')
    {
        $qrToken = trim((string) $qrToken);
        $isTokenFormatValid = (bool) preg_match('/^[a-f0-9]{32,64}$/', $qrToken);
        $document = $isTokenFormatValid ? $this->documentModel->findByQrToken($qrToken) : null;
        $showFullDetails = $document ? $this->isAuthorizedViewer($document) : false;

        require_once '../app/views/verification/verify.php';
    }
}
