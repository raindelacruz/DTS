<?php

require_once '../app/models/Document.php';
require_once '../app/models/Department.php';
require_once '../app/models/Notification.php';
require_once '../app/lib/QrCodeService.php';
require_once '../app/lib/PdfOverlayService.php';

class Documents extends Controller
{
    private $documentModel;
    private $departmentModel;
    private $notificationModel;

    public function __construct()
    {
        requireLogin();

        $this->documentModel = new Document();
        $this->departmentModel = new Department();
        $this->notificationModel = new Notification();
    }

    private function isManager()
    {
        return (($_SESSION['role'] ?? '') === 'manager');
    }

    private function requireParentDepartment()
    {
        $isParent = $this->departmentModel->isParentDepartment((int) $_SESSION['department_id']);
        if (!$isParent) {
            throw new AuthorizationException('Only parent departments can perform this action.');
        }
    }

    private function getListFilters()
    {
        return [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'type' => trim($_GET['type'] ?? ''),
            'date_from' => trim($_GET['date_from'] ?? ''),
            'date_to' => trim($_GET['date_to'] ?? '')
        ];
    }

    private function hasFilters($filters)
    {
        foreach ($filters as $value) {
            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    private function extractTypes($documents)
    {
        $types = [];

        foreach ($documents as $document) {
            $type = trim((string) ($document['type'] ?? ''));
            if ($type !== '') {
                $types[$type] = $type;
            }
        }

        ksort($types);
        return array_values($types);
    }

    private function managerStaffHandled($documentId, $departmentId)
    {
        return $this->documentModel->departmentHandledDocumentByOtherUser($documentId, $departmentId, (int) $_SESSION['user_id']);
    }

    private function managerHasAcknowledged($documentId, $departmentId)
    {
        return $this->documentModel->userHandledDocument(
            $documentId,
            $departmentId,
            (int) $_SESSION['user_id'],
            ['Manager Received', 'Cleared THRU', 'Noted CC', 'Forwarded']
        );
    }

    private function managerHasSpecificAction($documentId, $departmentId, $action)
    {
        return $this->documentModel->userHandledDocument(
            $documentId,
            $departmentId,
            (int) $_SESSION['user_id'],
            [$action]
        );
    }

    private function departmentManagerHasReceived($documentId, $departmentId)
    {
        return $this->documentModel->departmentHandledDocumentWithActions(
            $documentId,
            $departmentId,
            ['Manager Received', 'Cleared THRU', 'Noted CC', 'Forwarded']
        );
    }

    private function simplifyTimelineRemarks($log)
    {
        $action = strtolower(trim((string) ($log['action'] ?? '')));
        $remarks = trim((string) ($log['remarks'] ?? ''));

        if ($action === 're-released') {
            return 'Document Re-released';
        }

        if (strpos($action, 'attachment') !== false && strpos($action, 'replaced') !== false) {
            if (preg_match('/^Reason:\s*(.+)$/mi', $remarks, $matches)) {
                return trim($matches[1]);
            }

            return $remarks;
        }

        return $remarks;
    }

    private function parseActionInstructions($instructions)
    {
        $details = [
            'urgent' => 'No',
            'action' => '',
            'deadline' => '',
            'instruction' => ''
        ];

        foreach (preg_split('/\\r\\n|\\r|\\n/', (string) $instructions) as $line) {
            if (strpos($line, 'Urgent: ') === 0) {
                $details['urgent'] = trim(substr($line, 8));
            } elseif (strpos($line, 'Action: ') === 0) {
                $details['action'] = trim(substr($line, 8));
            } elseif (strpos($line, 'Deadline: ') === 0) {
                $details['deadline'] = trim(substr($line, 10));
            } elseif (strpos($line, 'Instruction: ') === 0) {
                $details['instruction'] = trim(substr($line, 13));
            }
        }

        return $details;
    }

    private function requireValidCsrfPost()
    {
        requirePost();
        validateCsrfOrFail();
    }

    private function documentFormDefaults()
    {
        return [
            'title' => '',
            'particulars' => '',
            'type' => '',
            'reference_document_id' => '',
            'thru_department_id' => '',
            'to_department_ids' => [],
            'cc_department_ids' => [],
            'delegate_department_ids' => []
        ];
    }

    private function forwardFormDefaults()
    {
        return [
            'department_ids' => [],
            'urgent' => 0,
            'action_type' => '',
            'deadline_date' => '',
            'instruction' => ''
        ];
    }

    private function releasableStatuses()
    {
        return ['Released', 'Re-released'];
    }

    private function normalizeDocumentFormValues($source)
    {
        return [
            'title' => trim($source['title'] ?? ''),
            'particulars' => trim($source['particulars'] ?? ''),
            'type' => trim($source['type'] ?? ''),
            'reference_document_id' => !empty($source['reference_document_id']) ? (string) ((int) $source['reference_document_id']) : '',
            'thru_department_id' => !empty($source['thru_department_id']) ? (string) ((int) $source['thru_department_id']) : '',
            'to_department_ids' => array_values(array_unique(array_filter(array_map('intval', $source['to_department_ids'] ?? [])))),
            'cc_department_ids' => array_values(array_unique(array_filter(array_map('intval', $source['cc_department_ids'] ?? [])))),
            'delegate_department_ids' => array_values(array_unique(array_filter(array_map('intval', $source['delegate_department_ids'] ?? []))))
        ];
    }

    private function validateDocumentFormValues($values, $currentDocumentId = null)
    {
        $errors = [];

        if ($values['title'] === '') {
            $errors['title'] = 'Title is required.';
        }

        if ($values['type'] === '') {
            $errors['type'] = 'Type is required.';
        }

        $referenceDocumentId = $values['reference_document_id'] !== '' ? (int) $values['reference_document_id'] : null;
        if ($referenceDocumentId !== null) {
            $referenceDocument = $this->documentModel->findById($referenceDocumentId);

            if (!$referenceDocument) {
                $errors['reference_document_id'] = 'Select a valid referenced document.';
            } elseif (!$this->documentModel->canDepartmentViewDocument($referenceDocumentId, (int) $_SESSION['department_id'])) {
                $errors['reference_document_id'] = 'You can only reference documents visible to your department.';
            } elseif ($currentDocumentId !== null && $referenceDocumentId === (int) $currentDocumentId) {
                $errors['reference_document_id'] = 'A document cannot reference itself.';
            }
        }

        $thruDepartmentId = $values['thru_department_id'] !== '' ? (int) $values['thru_department_id'] : null;
        $toDepartmentIds = $values['to_department_ids'];
        $ccDepartmentIds = $values['cc_department_ids'];
        $delegateDepartmentIds = $values['delegate_department_ids'];
        $originDepartmentId = (int) $_SESSION['department_id'];

        if (empty($toDepartmentIds) && empty($delegateDepartmentIds)) {
            $errors['to_department_ids'] = 'Select at least one TO department or internal division.';
        }

        if ($thruDepartmentId !== null) {
            $toDepartmentIds = array_values(array_filter($toDepartmentIds, function ($id) use ($thruDepartmentId) {
                return (int) $id !== $thruDepartmentId;
            }));

            $ccDepartmentIds = array_values(array_filter($ccDepartmentIds, function ($id) use ($thruDepartmentId) {
                return (int) $id !== $thruDepartmentId;
            }));
        }

        $ccDepartmentIds = array_values(array_filter($ccDepartmentIds, function ($id) use ($toDepartmentIds) {
            return !in_array((int) $id, $toDepartmentIds, true);
        }));

        $delegateDepartmentIds = array_values(array_filter($delegateDepartmentIds, function ($id) use ($thruDepartmentId, $toDepartmentIds, $ccDepartmentIds) {
            $departmentId = (int) $id;
            return ($thruDepartmentId === null || $departmentId !== $thruDepartmentId)
                && !in_array($departmentId, $toDepartmentIds, true)
                && !in_array($departmentId, $ccDepartmentIds, true);
        }));

        if (!empty($values['to_department_ids']) && empty($toDepartmentIds) && empty($delegateDepartmentIds)) {
            $errors['to_department_ids'] = 'TO must still contain at least one department after THRU validation.';
        }

        $allSelectedDepartments = $toDepartmentIds;
        if ($thruDepartmentId !== null) {
            $allSelectedDepartments[] = $thruDepartmentId;
        }
        $allSelectedDepartments = array_merge($allSelectedDepartments, $ccDepartmentIds);

        if (!empty($allSelectedDepartments) && !$this->departmentModel->areParentDepartments($allSelectedDepartments)) {
            $errors['to_department_ids'] = 'Routing is limited to parent departments only.';
        }

        if (!empty($delegateDepartmentIds) && !$this->departmentModel->areChildDepartmentsOfParent($delegateDepartmentIds, $originDepartmentId)) {
            $errors['delegate_department_ids'] = 'Internal routing is limited to your own child division only.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Please correct the highlighted fields.', $errors);
        }

        return [
            'title' => $values['title'],
            'particulars' => $values['particulars'],
            'type' => $values['type'],
            'reference_document_id' => $referenceDocumentId,
            'thru_department_id' => $thruDepartmentId,
            'to_department_ids' => $toDepartmentIds,
            'cc_department_ids' => $ccDepartmentIds,
            'delegate_department_ids' => $delegateDepartmentIds
        ];
    }

    private function getReferenceableDocuments($excludeDocumentId = null)
    {
        $documents = $this->documentModel->getByDepartment((int) $_SESSION['department_id']);

        if ($excludeDocumentId !== null) {
            $documents = array_values(array_filter($documents, function ($document) use ($excludeDocumentId) {
                return (int) ($document['id'] ?? 0) !== (int) $excludeDocumentId;
            }));
        }

        return $documents;
    }

    private function authorizeDocumentViewOrFail($id)
    {
        $document = $this->documentModel->findById($id);
        if (!$document) {
            throw new NotFoundException('Document not found.');
        }

        $deptId = (int) $_SESSION['department_id'];
        $allowed = $this->documentModel->canDepartmentViewDocument($id, $deptId);
        $managerStaffHandled = $this->managerStaffHandled((int) $id, $deptId);

        if ($this->isManager()) {
            $allowed = $allowed && (
                (int) $document['origin_department_id'] === $deptId
                || $managerStaffHandled
            );
        }

        if (!$allowed) {
            throw new AuthorizationException('Unauthorized access.');
        }

        return [$document, $deptId, $managerStaffHandled];
    }

    private function ensureUploadDirectory()
    {
        if (!is_dir(UPLOAD_ROOT) && !@mkdir(UPLOAD_ROOT, 0775, true) && !is_dir(UPLOAD_ROOT)) {
            throw new RuntimeException('Unable to create upload directory.');
        }
    }

    private function generateAttachmentFilename($extension)
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function handleAttachmentUpload($fieldName = 'attachment')
    {
        if (empty($_FILES[$fieldName]['name'])) {
            return null;
        }

        if (
            isset($_FILES[$fieldName]['error'])
            && in_array($_FILES[$fieldName]['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ) {
            throw new ValidationException('Please correct the highlighted fields.', ['attachment' => 'Attachment exceeds the ' . MAX_ATTACHMENT_SIZE_MB . ' MB limit.']);
        }

        if (!isset($_FILES[$fieldName]['error']) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException('Please correct the highlighted fields.', ['attachment' => 'File upload failed. Please try again.']);
        }

        if (($_FILES[$fieldName]['size'] ?? 0) > MAX_ATTACHMENT_SIZE_BYTES) {
            throw new ValidationException('Please correct the highlighted fields.', ['attachment' => 'Attachment exceeds the ' . MAX_ATTACHMENT_SIZE_MB . ' MB limit.']);
        }

        $tmpPath = $_FILES[$fieldName]['tmp_name'] ?? '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new ValidationException('Please correct the highlighted fields.', ['attachment' => 'Invalid uploaded file.']);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);
        $allowedMimeTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimeTypes[$mimeType])) {
            throw new ValidationException('Please correct the highlighted fields.', ['attachment' => 'Only PDF, JPG, PNG, GIF, and WEBP attachments are allowed.']);
        }

        $extension = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
        $validExtensions = [$allowedMimeTypes[$mimeType]];
        if ($mimeType === 'image/jpeg') {
            $validExtensions[] = 'jpeg';
        }

        if (!in_array($extension, $validExtensions, true)) {
            throw new ValidationException('Please correct the highlighted fields.', ['attachment' => 'Attachment file extension does not match the uploaded content.']);
        }

        $this->ensureUploadDirectory();
        $filename = $this->generateAttachmentFilename($allowedMimeTypes[$mimeType]);
        $uploadPath = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $uploadPath)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        return $filename;
    }

    private function resolveAttachmentPath($filename)
    {
        $safeFilename = basename((string) $filename);
        $candidates = [
            rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . $safeFilename,
            rtrim(LEGACY_UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . $safeFilename
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function detectAttachmentMimeType($attachmentPath)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($attachmentPath);

        if (!is_string($mimeType) || $mimeType === '') {
            return 'application/octet-stream';
        }

        return $mimeType;
    }

    private function getAttachmentViewerType($attachmentPath)
    {
        $mimeType = $this->detectAttachmentMimeType($attachmentPath);

        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }

        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        }

        return 'unsupported';
    }

    private function buildVerificationUrl($qrToken)
    {
        return buildUrl('/document/verify/' . rawurlencode((string) $qrToken));
    }

    private function isQrPrintEnabled()
    {
        return defined('ENABLE_QR_PRINT') && ENABLE_QR_PRINT === true;
    }


    private function replaceForwardedDepartmentIdsWithCodes($remarks, $departmentCodeMap)
    {
        $prefix = 'Document forwarded to department IDs: ';
        if (strpos($remarks, $prefix) !== 0) {
            return $remarks;
        }

        $parts = preg_split('/\\r\\n|\\r|\\n/', (string) $remarks);
        $ids = array_filter(array_map('trim', explode(',', substr($parts[0], strlen($prefix)))));
        $codes = [];

        foreach ($ids as $id) {
            $codes[] = $departmentCodeMap[(int) $id] ?? $id;
        }

        $parts[0] = 'Document forwarded to departments: ' . implode(', ', $codes);
        return implode("\n", $parts);
    }
    private function filterManagerVisibleDocuments($documents, $departmentId)
    {
        if (!$this->isManager()) {
            return $documents;
        }

        return array_values(array_filter($documents, function ($document) use ($departmentId) {
            return (int) $document['origin_department_id'] === (int) $departmentId
                || $this->documentModel->departmentHandledDocumentByOtherUser((int) $document['id'], (int) $departmentId, (int) $_SESSION['user_id']);
        }));
    }

    private function notifyDocumentRouteDepartments($documentId, $title, $message, $departmentIds = null)
    {
        if ($departmentIds === null) {
            $routing = $this->documentModel->getRoutingByDocument($documentId);
            $departmentIds = [];

            foreach (['THRU', 'TO', 'CC', 'DELEGATE'] as $routeType) {
                foreach ($routing[$routeType] ?? [] as $route) {
                    $departmentIds[] = (int) $route['department_id'];
                }
            }
        }

        $this->notificationModel->notifyDepartmentStaffUsers(
            $departmentIds,
            $title,
            $message,
            '/documents/show/' . $documentId,
            (int) $_SESSION['user_id']
        );
    }

    private function getRouteDepartmentIdsByTypes($documentId, $types, $excludeDepartmentIds = [])
    {
        $routing = $this->documentModel->getRoutingByDocument($documentId);
        $departmentIds = [];
        $types = array_values(array_unique($types));
        $excludeDepartmentIds = array_map('intval', $excludeDepartmentIds);

        foreach ($types as $routeType) {
            foreach ($routing[$routeType] ?? [] as $route) {
                $departmentId = (int) $route['department_id'];
                if (in_array($departmentId, $excludeDepartmentIds, true)) {
                    continue;
                }
                $departmentIds[$departmentId] = $departmentId;
            }
        }

        return array_values($departmentIds);
    }

    private function findOwnedDraftOrFail($id)
    {
        $document = $this->documentModel->findById((int) $id);
        if (!$document) {
            throw new NotFoundException('Document not found.');
        }

        if ((int) $document['origin_department_id'] !== (int) $_SESSION['department_id']) {
            throw new AuthorizationException('Unauthorized action.');
        }

        if (($document['status'] ?? '') !== 'Draft') {
            throw new ValidationException('Only draft documents can be edited.');
        }

        return $document;
    }

    private function renderCreateForm()
    {
        $state = pullFormState('document_create', $this->documentFormDefaults());
        $departments = $this->departmentModel->getParentDepartments();
        $childDepartments = $this->departmentModel->getChildDepartmentsForParent((int) $_SESSION['department_id']);
        $documentData = [
            'title' => $state['values']['title'] ?? '',
            'particulars' => $state['values']['particulars'] ?? '',
            'type' => $state['values']['type'] ?? '',
            'reference_document_id' => $state['values']['reference_document_id'] ?? ''
        ];
        $referenceDocuments = $this->getReferenceableDocuments();
        $selectedThruDepartmentId = (int) ($state['values']['thru_department_id'] ?? 0);
        $selectedToDepartmentIds = array_map('intval', $state['values']['to_department_ids'] ?? []);
        $selectedCcDepartmentIds = array_map('intval', $state['values']['cc_department_ids'] ?? []);
        $selectedDelegateDepartmentIds = array_map('intval', $state['values']['delegate_department_ids'] ?? []);
        $submitLabel = 'Create Document';
        $formAction = URLROOT . '/documents/store';
        $cancelUrl = URLROOT . '/documents';
        $showAttachmentHint = false;
        $nextPrefix = $this->documentModel->getNextPrefix((int) $_SESSION['department_id']);
        $errors = $state['errors'];
        $formMessage = $state['message'];
        require_once '../app/views/documents/create.php';
    }

    private function renderEditForm($document)
    {
        $routing = $this->documentModel->getRoutingByDocument((int) $document['id']);
        $defaults = [
            'title' => $document['title'] ?? '',
            'particulars' => $document['particulars'] ?? '',
            'type' => $document['type'] ?? '',
            'reference_document_id' => !empty($document['reference_document_id']) ? (string) ((int) $document['reference_document_id']) : '',
            'thru_department_id' => !empty($routing['THRU'][0]['department_id']) ? (string) ((int) $routing['THRU'][0]['department_id']) : '',
            'to_department_ids' => array_map(function ($route) {
                return (int) $route['department_id'];
            }, $routing['TO'] ?? []),
            'cc_department_ids' => array_map(function ($route) {
                return (int) $route['department_id'];
            }, $routing['CC'] ?? []),
            'delegate_department_ids' => array_map(function ($route) {
                return (int) $route['department_id'];
            }, $routing['DELEGATE'] ?? [])
        ];
        $state = pullFormState('document_edit_' . (int) $document['id'], $defaults);
        $departments = $this->departmentModel->getParentDepartments();
        $childDepartments = $this->departmentModel->getChildDepartmentsForParent((int) $_SESSION['department_id']);
        $documentData = [
            'title' => $state['values']['title'] ?? '',
            'particulars' => $state['values']['particulars'] ?? '',
            'type' => $state['values']['type'] ?? '',
            'reference_document_id' => $state['values']['reference_document_id'] ?? ''
        ];
        $referenceDocuments = $this->getReferenceableDocuments((int) $document['id']);
        $selectedThruDepartmentId = (int) ($state['values']['thru_department_id'] ?? 0);
        $selectedToDepartmentIds = array_map('intval', $state['values']['to_department_ids'] ?? []);
        $selectedCcDepartmentIds = array_map('intval', $state['values']['cc_department_ids'] ?? []);
        $selectedDelegateDepartmentIds = array_map('intval', $state['values']['delegate_department_ids'] ?? []);
        $formAction = URLROOT . '/documents/update/' . (int) $document['id'];
        $submitLabel = 'Save Changes';
        $cancelUrl = URLROOT . '/documents/show/' . (int) $document['id'];
        $showAttachmentHint = true;
        $errors = $state['errors'];
        $formMessage = $state['message'];
        require_once '../app/views/documents/edit.php';
    }

    private function ensureForwardableDocument($document)
    {
        $currentDepartmentId = (int) $_SESSION['department_id'];

        if (!$this->isManager()) {
            throw new AuthorizationException('Only managers can forward documents.');
        }

        if (!$document) {
            throw new NotFoundException('Document not found.');
        }

        if (!$this->managerStaffHandled((int) $document['id'], $currentDepartmentId) || !$this->managerHasAcknowledged((int) $document['id'], $currentDepartmentId)) {
            throw new ValidationException('The manager must receive the document after staff receipt before forwarding.');
        }

        $routeRole = $this->documentModel->getDepartmentRouteRole((int) $document['id'], $currentDepartmentId);
        $hasDelegatedChild = $this->documentModel->hasDelegatedToChild((int) $document['id'], $currentDepartmentId);

        $canForwardViaRouting = !$hasDelegatedChild
            && $routeRole
            && in_array($routeRole['route_type'], ['TO', 'DELEGATE'], true)
            && ((int) $routeRole['is_cleared'] === 1);

        $canForwardLegacy = !$hasDelegatedChild
            && !$routeRole
            && (int) $document['destination_department_id'] === $currentDepartmentId
            && $document['status'] === 'Received';

        if (!$canForwardViaRouting && !$canForwardLegacy) {
            throw new AuthorizationException('Unauthorized action.');
        }
    }

    private function canCurrentStaffReturnDocument($document, $routeRole, $deptId, $departmentManagerReceived = false)
    {
        if ($this->isManager() || !$document || $departmentManagerReceived || ($document['status'] ?? '') === 'Returned') {
            return false;
        }

        if (!in_array($document['status'] ?? '', $this->releasableStatuses(), true)) {
            return false;
        }

        if ($routeRole) {
            return ((int) ($routeRole['is_cleared'] ?? 0) !== 1)
                && (($routeRole['route_type'] ?? '') === 'THRU' || $this->documentModel->isThruCleared((int) $document['id']));
        }

        return (int) ($document['destination_department_id'] ?? 0) === (int) $deptId
            && in_array($document['status'] ?? '', $this->releasableStatuses(), true);
    }

    private function canCurrentStaffResolveReturn($openReturn, $deptId)
    {
        return !$this->isManager()
            && $openReturn
            && (int) ($openReturn['releasing_department_id'] ?? 0) === (int) $deptId;
    }

    private function renderForwardForm($document)
    {
        $state = pullFormState('document_forward_' . (int) $document['id'], $this->forwardFormDefaults());
        $departments = $this->departmentModel->getForwardTargetsForParent((int) $_SESSION['department_id']);
        $formValues = $state['values'];
        $errors = $state['errors'];
        $formMessage = $state['message'];
        require_once '../app/views/documents/forward.php';
    }

    public function index()
    {
        try {
            $department_id = (int) $_SESSION['department_id'];
            $filters = $this->getListFilters();

            if ($this->hasFilters($filters)) {
                $documents = $this->documentModel->search($department_id, $filters);
            } else {
                $documents = $this->documentModel->getAllByDepartment($department_id);
            }

            $documents = $this->filterManagerVisibleDocuments($documents, $department_id);

            $statusCounts = [
                'Draft' => 0,
                'Released' => 0,
                'Received' => 0,
                'Returned' => 0,
                'Re-released' => 0
            ];

            foreach ($documents as $document) {
                if (isset($statusCounts[$document['status']])) {
                    $statusCounts[$document['status']]++;
                }
            }

            $data = [
                'documents' => $documents,
                'filters' => $filters,
                'types' => $this->extractTypes($documents),
                'status_counts' => $statusCounts,
                'total_documents' => count($documents)
            ];

            $this->view('documents/index', $data);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.index', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('error', 'We could not load documents right now.', 'error');
            redirect('/dashboard', 303);
        }
    }

    public function outgoing()
    {
        try {
            $departmentId = (int) $_SESSION['department_id'];
            $filters = $this->getListFilters();
            $documents = $this->documentModel->getOutgoingByDepartment($departmentId, $filters);

            $data = [
                'title' => 'Outgoing Documents',
                'documents' => $documents,
                'filters' => $filters,
                'types' => $this->extractTypes($this->documentModel->getOutgoingByDepartment($departmentId)),
                'empty_message' => 'No outgoing documents found.'
            ];

            $this->view('documents/outgoing', $data);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.outgoing', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('error', 'We could not load outgoing documents right now.', 'error');
            redirect('/documents', 303);
        }
    }

    public function incoming()
    {
        try {
            $departmentId = (int) $_SESSION['department_id'];
            $filters = $this->getListFilters();
            $documents = $this->documentModel->getIncomingByDepartment($departmentId, $filters);
            $documents = $this->filterManagerVisibleDocuments($documents, $departmentId);

            $data = [
                'title' => 'Incoming Documents',
                'documents' => $documents,
                'filters' => $filters,
                'types' => $this->extractTypes($this->filterManagerVisibleDocuments($this->documentModel->getIncomingByDepartment($departmentId), $departmentId)),
                'empty_message' => 'No incoming documents found.'
            ];

            $this->view('documents/incoming', $data);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.incoming', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('error', 'We could not load incoming documents right now.', 'error');
            redirect('/documents', 303);
        }
    }

    public function create()
    {
        try {
            $this->renderCreateForm();
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.create']);
            flash('error', 'We could not open the document form right now.', 'error');
            redirect('/documents', 303);
        }
    }

    public function edit($id)
    {
        try {
            $this->renderEditForm($this->findOwnedDraftOrFail((int) $id));
        } catch (NotFoundException $e) {
            flash('error', 'Document not found.', 'error');
            redirect('/documents', 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to edit that document.', 'error');
            redirect('/documents', 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . (int) $id, 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.edit', 'document_id' => (int) $id]);
            flash('error', 'We could not open that draft right now.', 'error');
            redirect('/documents', 303);
        }
    }

    public function store()
    {
        $values = $this->normalizeDocumentFormValues($_POST);

        try {
            $this->requireValidCsrfPost();
            $routingInput = $this->validateDocumentFormValues($values);
            $filename = $this->handleAttachmentUpload();

            $this->documentModel->createDocument([
                'title' => $routingInput['title'],
                'particulars' => $routingInput['particulars'],
                'type' => $routingInput['type'],
                'origin_department_id' => (int) $_SESSION['department_id'],
                'destination_department_id' => $routingInput['to_department_ids'][0] ?? $routingInput['delegate_department_ids'][0],
                'reference_document_id' => $routingInput['reference_document_id'],
                'thru_department_id' => $routingInput['thru_department_id'],
                'to_department_ids' => $routingInput['to_department_ids'],
                'cc_department_ids' => $routingInput['cc_department_ids'],
                'delegate_department_ids' => $routingInput['delegate_department_ids'],
                'created_by' => (int) $_SESSION['user_id'],
                'attachment' => $filename
            ]);

            flash('success', 'Document created successfully.', 'success');
            redirect('/documents', 303);
        } catch (ValidationException $e) {
            storeFormState('document_create', $values, $e->getErrors(), $e->getMessage());
            redirect('/documents/create', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.store', 'user_id' => $_SESSION['user_id'] ?? null]);
            storeFormState('document_create', $values, [], 'We could not save the document right now. Please try again.');
            redirect('/documents/create', 303);
        }
    }

    public function update($id)
    {
        $documentId = (int) $id;
        $values = $this->normalizeDocumentFormValues($_POST);

        try {
            $this->requireValidCsrfPost();
            $this->findOwnedDraftOrFail($documentId);
            $routingInput = $this->validateDocumentFormValues($values, $documentId);
            $filename = $this->handleAttachmentUpload();

            $data = [
                'title' => $routingInput['title'],
                'particulars' => $routingInput['particulars'],
                'type' => $routingInput['type'],
                'reference_document_id' => $routingInput['reference_document_id'],
                'thru_department_id' => $routingInput['thru_department_id'],
                'to_department_ids' => $routingInput['to_department_ids'],
                'cc_department_ids' => $routingInput['cc_department_ids'],
                'delegate_department_ids' => $routingInput['delegate_department_ids'],
                'updated_by' => (int) $_SESSION['user_id']
            ];

            if ($filename !== null) {
                $data['attachment'] = $filename;
            }

            $this->documentModel->updateDraftDocument($documentId, $data);

            flash('success', 'Draft updated successfully.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (ValidationException $e) {
            storeFormState('document_edit_' . $documentId, $values, $e->getErrors(), $e->getMessage());
            redirect('/documents/edit/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to edit that document.', 'error');
            redirect('/documents', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Document not found.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.update', 'document_id' => $documentId]);
            storeFormState('document_edit_' . $documentId, $values, [], 'We could not save your changes right now. Please try again.');
            redirect('/documents/edit/' . $documentId, 303);
        }
    }

    public function release($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();
            $document = $this->findOwnedDraftOrFail($documentId);

            $this->documentModel->releaseDocument($documentId, $_SESSION['user_id'], $_SESSION['department_id']);
            $initialDepartmentIds = $this->getRouteDepartmentIdsByTypes($documentId, ['THRU']);
            if (empty($initialDepartmentIds)) {
                $initialDepartmentIds = $this->getRouteDepartmentIdsByTypes($documentId, ['TO', 'CC', 'DELEGATE']);
            }

            $this->notifyDocumentRouteDepartments(
                $documentId,
                'Document released',
                $document['prefix'] . ' has been released.',
                $initialDepartmentIds
            );

            flash('success', 'Document released successfully.', 'success');
            redirect('/documents', 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to release that document.', 'error');
            redirect('/documents', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Document not found.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.release', 'document_id' => $documentId]);
            flash('error', 'We could not release that document right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function receive($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();

            if ($this->isManager()) {
                throw new ValidationException('Managers must wait for staff receipt before acting on a document.');
            }

            $document = $this->documentModel->findById($documentId);
            if (!$document) {
                throw new NotFoundException('Document not found.');
            }

            $deptId = (int) $_SESSION['department_id'];
            $route = $this->documentModel->getDepartmentRouteRole($documentId, $deptId);

            if ($route) {
                if ($document['status'] === 'Draft') {
                    throw new ValidationException('Draft documents cannot be received.');
                }

                if ($document['status'] === 'Returned') {
                    throw new ValidationException('Returned documents must be corrected and re-released before receipt.');
                }

                if ($route['route_type'] !== 'THRU' && !$this->documentModel->isThruCleared($documentId)) {
                    throw new ValidationException('Document is waiting for THRU clearance.');
                }
            } else {
                if (!in_array($document['status'], $this->releasableStatuses(), true)) {
                    throw new ValidationException('Only released documents can be received.');
                }

                if ((int) $document['destination_department_id'] !== $deptId) {
                    throw new AuthorizationException('Unauthorized action.');
                }

                if (!$this->departmentModel->isParentDepartment($deptId)) {
                    throw new AuthorizationException('Only parent departments can perform this action.');
                }
            }

            $this->documentModel->receiveDocument($documentId, $_SESSION['user_id'], $deptId);

            $this->notificationModel->notifyDepartmentManagers(
                [$deptId],
                'Manager action required',
                $document['prefix'] . ' was received by staff and is ready for manager action.',
                '/documents/show/' . $documentId,
                (int) $_SESSION['user_id']
            );

            if (!empty($document['created_by']) && (int) $document['created_by'] !== (int) $_SESSION['user_id']) {
                $this->notificationModel->create((int) $document['created_by'], 'Document received', $document['prefix'] . ' has been received.', '/documents/show/' . $documentId);
            }

            flash('success', 'Document received successfully.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to receive that document.', 'error');
            redirect('/documents', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Document not found.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.receive', 'document_id' => $documentId]);
            flash('error', 'We could not receive that document right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function returnDocument($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();

            if ($this->isManager()) {
                throw new AuthorizationException('Only receiving department staff can return documents.');
            }

            $document = $this->documentModel->findById($documentId);
            if (!$document) {
                throw new NotFoundException('Document not found.');
            }

            $deptId = (int) $_SESSION['department_id'];
            $routeRole = $this->documentModel->getDepartmentRouteRole($documentId, $deptId);
            if (!$this->canCurrentStaffReturnDocument($document, $routeRole, $deptId, $this->departmentManagerHasReceived($documentId, $deptId))) {
                throw new AuthorizationException('Unauthorized action.');
            }

            $reason = trim($_POST['return_reason'] ?? '');
            $remarks = trim($_POST['return_remarks'] ?? '');
            $attachmentIssue = trim($_POST['attachment_issue'] ?? '');
            $allowedIssues = ['Incorrect attachment', 'Missing page', 'Wrong file', 'Unreadable file'];
            $errors = [];

            if ($reason === '') {
                $errors['return_reason'] = 'Reason for return is required.';
            }

            if ($remarks === '') {
                $errors['return_remarks'] = 'Remarks/details are required.';
            }

            if ($attachmentIssue !== '' && !in_array($attachmentIssue, $allowedIssues, true)) {
                $errors['attachment_issue'] = 'Select a valid attachment issue.';
            }

            if (!empty($errors)) {
                throw new ValidationException('Please provide the return reason and details.', $errors);
            }

            $this->documentModel->returnDocument(
                $documentId,
                (int) $_SESSION['user_id'],
                $deptId,
                $reason,
                $attachmentIssue,
                $remarks
            );

            $openReturn = $this->documentModel->getOpenReturn($documentId);
            $releasingDepartmentId = $openReturn ? (int) $openReturn['releasing_department_id'] : (int) $document['origin_department_id'];

            $this->notificationModel->notifyDepartmentStaffUsers(
                [$releasingDepartmentId],
                'Document returned',
                $document['prefix'] . ' was returned due to attachment issue.',
                '/documents/show/' . $documentId,
                (int) $_SESSION['user_id']
            );

            flash('success', 'Document returned to the releasing department.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to return that document.', 'error');
            redirect('/documents', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Document not found.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.returnDocument', 'document_id' => $documentId]);
            flash('error', 'We could not return that document right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function uploadCorrectedAttachment($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();

            $document = $this->documentModel->findById($documentId);
            $openReturn = $this->documentModel->getOpenReturn($documentId);
            $deptId = (int) $_SESSION['department_id'];

            if (!$document || !$openReturn) {
                throw new NotFoundException('Returned document not found.');
            }

            if (!$this->canCurrentStaffResolveReturn($openReturn, $deptId)) {
                throw new AuthorizationException('Unauthorized action.');
            }

            if (($document['status'] ?? '') !== 'Returned') {
                throw new ValidationException('Only returned documents can receive a corrected attachment.');
            }

            $replacementReason = trim($_POST['replacement_reason'] ?? '');
            if ($replacementReason === '') {
                throw new ValidationException('Reason for replacement is required.', ['replacement_reason' => 'Reason for replacement is required.']);
            }

            $filename = $this->handleAttachmentUpload('corrected_attachment');
            if ($filename === null) {
                throw new ValidationException('Please attach the corrected file.', ['attachment' => 'Corrected attachment is required.']);
            }

            $this->documentModel->replaceReturnedAttachment(
                $documentId,
                $filename,
                (int) $_SESSION['user_id'],
                $deptId,
                $replacementReason
            );

            flash('success', 'Corrected attachment uploaded. You can now re-release the document.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to replace that attachment.', 'error');
            redirect('/documents', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Returned document not found.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.uploadCorrectedAttachment', 'document_id' => $documentId]);
            flash('error', 'We could not upload the corrected attachment right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function reRelease($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();

            $document = $this->documentModel->findById($documentId);
            $openReturn = $this->documentModel->getOpenReturn($documentId);
            $deptId = (int) $_SESSION['department_id'];

            if (!$document || !$openReturn) {
                throw new NotFoundException('Returned document not found.');
            }

            if (!$this->canCurrentStaffResolveReturn($openReturn, $deptId)) {
                throw new AuthorizationException('Unauthorized action.');
            }

            if (!$this->documentModel->hasReplacementForReturn((int) $openReturn['id'])) {
                throw new ValidationException('Upload a corrected attachment before re-releasing this document.');
            }

            $this->documentModel->reReleaseReturnedDocument(
                $documentId,
                (int) $_SESSION['user_id'],
                $deptId
            );

            $this->notificationModel->notifyDepartmentStaffUsers(
                [(int) $openReturn['returned_department_id']],
                'Document re-released',
                $document['prefix'] . ' was re-released with a corrected attachment.',
                '/documents/show/' . $documentId,
                (int) $_SESSION['user_id']
            );

            flash('success', 'Document re-released successfully.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to re-release that document.', 'error');
            redirect('/documents', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Returned document not found.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.reRelease', 'document_id' => $documentId]);
            flash('error', 'We could not re-release that document right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function managerReceive($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();

            if (!$this->isManager()) {
                throw new AuthorizationException('Only managers can perform this action.');
            }

            $document = $this->documentModel->findById($documentId);
            $deptId = (int) $_SESSION['department_id'];

            if (!$document || !$this->managerStaffHandled($documentId, $deptId)) {
                throw new ValidationException('Document is not ready for manager action.');
            }

            if (!$this->managerHasAcknowledged($documentId, $deptId)) {
                $this->documentModel->addDocumentLog($documentId, 'Manager Received', (int) $_SESSION['user_id'], $deptId, 'Document received by manager');
                $this->notificationModel->create((int) $document['created_by'], 'Manager received document', $document['prefix'] . ' was received by the manager.', '/documents/show/' . $documentId);
            }

            flash('success', 'Manager receipt recorded.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to perform that action.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.managerReceive', 'document_id' => $documentId]);
            flash('error', 'We could not record manager receipt right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function clearThru($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();

            if (!$this->isManager()) {
                throw new AuthorizationException('Only managers can perform this action.');
            }

            $document = $this->documentModel->findById($documentId);
            $deptId = (int) $_SESSION['department_id'];
            $route = $this->documentModel->getDepartmentRouteRole($documentId, $deptId);

            if (!$document || !$route || $route['route_type'] !== 'THRU' || !$this->managerHasAcknowledged($documentId, $deptId)) {
                throw new AuthorizationException('Unauthorized action.');
            }

            if (!$this->managerHasSpecificAction($documentId, $deptId, 'Cleared THRU')) {
                $this->documentModel->addDocumentLog($documentId, 'Cleared THRU', (int) $_SESSION['user_id'], $deptId, 'Document cleared by manager');
                if (!empty($document['created_by'])) {
                    $this->notificationModel->create((int) $document['created_by'], 'THRU cleared', $document['prefix'] . ' was cleared by the manager.', '/documents/show/' . $documentId);
                }

                $nextDepartmentIds = $this->getRouteDepartmentIdsByTypes($documentId, ['TO', 'CC', 'DELEGATE'], [$deptId]);
                if (!empty($nextDepartmentIds)) {
                    $this->notifyDocumentRouteDepartments($documentId, 'Document ready for receipt', $document['prefix'] . ' is now available for your department after THRU clearance.', $nextDepartmentIds);
                }
            }

            flash('success', 'THRU clearance recorded.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to clear this THRU route.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.clearThru', 'document_id' => $documentId]);
            flash('error', 'We could not clear that THRU route right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function noteCc($id)
    {
        $documentId = (int) $id;

        try {
            $this->requireValidCsrfPost();

            if (!$this->isManager()) {
                throw new AuthorizationException('Only managers can perform this action.');
            }

            $document = $this->documentModel->findById($documentId);
            $deptId = (int) $_SESSION['department_id'];
            $route = $this->documentModel->getDepartmentRouteRole($documentId, $deptId);

            if (!$document || !$route || $route['route_type'] !== 'CC' || !$this->managerHasAcknowledged($documentId, $deptId)) {
                throw new AuthorizationException('Unauthorized action.');
            }

            if (!$this->managerHasSpecificAction($documentId, $deptId, 'Noted CC')) {
                $this->documentModel->addDocumentLog($documentId, 'Noted CC', (int) $_SESSION['user_id'], $deptId, 'Document noted by manager');
                if (!empty($document['created_by'])) {
                    $this->notificationModel->create((int) $document['created_by'], 'CC noted', $document['prefix'] . ' was noted by the manager.', '/documents/show/' . $documentId);
                }
            }

            flash('success', 'CC notation recorded.', 'success');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to note this CC route.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.noteCc', 'document_id' => $documentId]);
            flash('error', 'We could not record that CC action right now. Please try again.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function show($id)
    {
        $documentId = (int) $id;

        try {
            [$document, $deptId, $managerStaffHandled] = $this->authorizeDocumentViewOrFail($documentId);

            $logs = $this->documentModel->getDocumentLogs($documentId);
            $departmentCodeMap = [];
            foreach ($this->departmentModel->getAll() as $department) {
                $departmentCodeMap[(int) $department['id']] = $department['code'];
            }
            foreach ($logs as &$log) {
                $log['remarks'] = $this->replaceForwardedDepartmentIdsWithCodes((string) ($log['remarks'] ?? ''), $departmentCodeMap);
                $log['remarks'] = $this->simplifyTimelineRemarks($log);
            }
            unset($log);
            $routing = $this->documentModel->getRoutingByDocument($documentId);
            $routeRole = $this->documentModel->getDepartmentRouteRole($documentId, $deptId);
            $routeType = $routeRole['route_type'] ?? null;
            $routeCleared = isset($routeRole['is_cleared']) ? ((int) $routeRole['is_cleared'] === 1) : false;
            $thruCleared = $this->documentModel->isThruCleared($documentId);
            $isParentDepartment = $this->departmentModel->isParentDepartment($deptId);
            $hasDelegatedChild = $this->documentModel->hasDelegatedToChild($documentId, $deptId);
            $isManager = $this->isManager();
            $managerAcknowledged = $this->managerHasAcknowledged($documentId, $deptId);
            $managerThruCleared = $this->managerHasSpecificAction($documentId, $deptId, 'Cleared THRU');
            $managerCcNoted = $this->managerHasSpecificAction($documentId, $deptId, 'Noted CC');
            $recipientActionDetails = null;
            $referencedDocument = null;
            $latestRecipientRoute = $this->documentModel->getLatestRouteForDepartment($documentId, $deptId);
            $openReturn = $this->documentModel->getOpenReturn($documentId);
            $attachmentHistory = $this->documentModel->getAttachmentHistory($documentId);
            $hasReplacementForOpenReturn = $openReturn
                ? $this->documentModel->hasReplacementForReturn((int) $openReturn['id'])
                : false;
            $canReturnDocument = $this->canCurrentStaffReturnDocument($document, $routeRole, $deptId, $this->departmentManagerHasReceived($documentId, $deptId));
            $canResolveReturn = $this->canCurrentStaffResolveReturn($openReturn, $deptId);
            $canReplaceReturnedAttachment = $canResolveReturn && ($document['status'] ?? '') === 'Returned';
            $canReReleaseReturnedDocument = $canReplaceReturnedAttachment && $hasReplacementForOpenReturn;
            $returnIssueOptions = ['Incorrect attachment', 'Missing page', 'Wrong file', 'Unreadable file'];

            if (!empty($document['reference_document_id']) && $this->documentModel->canDepartmentViewDocument((int) $document['reference_document_id'], $deptId)) {
                $referencedDocument = $this->documentModel->findById((int) $document['reference_document_id']);
            }

            if ($latestRecipientRoute && !empty(trim((string) ($latestRecipientRoute['instructions'] ?? '')))) {
                $recipientActionDetails = $this->parseActionInstructions($latestRecipientRoute['instructions']);
                $recipientActionDetails['from_department_name'] = $latestRecipientRoute['from_department_name'] ?? '';
            }

            require_once '../app/views/documents/view.php';
        } catch (NotFoundException $e) {
            flash('error', 'Document not found.', 'error');
            redirect('/documents', 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to view that document.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.show', 'document_id' => $documentId]);
            flash('error', 'We could not load that document right now.', 'error');
            redirect('/documents', 303);
        }
    }

    public function attachment($id)
    {
        $documentId = (int) $id;

        try {
            [$document] = $this->authorizeDocumentViewOrFail($documentId);

            if (empty($document['attachment'])) {
                throw new NotFoundException('Attachment not found.');
            }

            $attachmentPath = $this->resolveAttachmentPath($document['attachment']);
            if ($attachmentPath === null) {
                throw new NotFoundException('Attachment not found.');
            }

            $qrPrintEnabled = $this->isQrPrintEnabled();
            $verificationUrl = '';
            $qrCodeDataUri = '';
            // Temporarily Disabled – QR Code Printing Feature
            if ($qrPrintEnabled) {
                $qrToken = $this->documentModel->ensureQrToken($documentId);
                $verificationUrl = $this->buildVerificationUrl($qrToken);
                $qrCodeDataUri = QrCodeService::generateSvgDataUri($verificationUrl, 4);
            }
            $attachmentType = $this->getAttachmentViewerType($attachmentPath);
            $sourceUrl = URLROOT . '/documents/source/' . $documentId;
            $previewUrl = $sourceUrl;

            require_once '../app/views/documents/attachment.php';
        } catch (NotFoundException $e) {
            flash('error', 'Attachment not found.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to view that attachment.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.attachment', 'document_id' => $documentId]);
            flash('error', 'We could not open that attachment right now.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function source($id)
    {
        $documentId = (int) $id;

        try {
            [$document] = $this->authorizeDocumentViewOrFail($documentId);

            if (empty($document['attachment'])) {
                throw new NotFoundException('Attachment not found.');
            }

            $attachmentPath = $this->resolveAttachmentPath($document['attachment']);
            if ($attachmentPath === null) {
                throw new NotFoundException('Attachment not found.');
            }

            $mimeType = $this->detectAttachmentMimeType($attachmentPath);

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string) filesize($attachmentPath));
            header('Content-Disposition: inline; filename="' . rawurlencode($document['attachment']) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($attachmentPath);
            exit;
        } catch (NotFoundException $e) {
            flash('error', 'Attachment not found.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to view that attachment.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.source', 'document_id' => $documentId]);
            flash('error', 'We could not open that attachment right now.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }

    public function download($id)
    {
        $this->source($id);
    }

    public function printable($id)
    {
        $documentId = (int) $id;

        try {
            [$document] = $this->authorizeDocumentViewOrFail($documentId);

            if (empty($document['attachment'])) {
                throw new NotFoundException('Attachment not found.');
            }

            $attachmentPath = $this->resolveAttachmentPath($document['attachment']);
            if ($attachmentPath === null) {
                throw new NotFoundException('Attachment not found.');
            }

            if ($this->getAttachmentViewerType($attachmentPath) !== 'pdf') {
                throw new ValidationException('Printable PDF view is only available for PDF attachments.');
            }

            $downloadName = basename((string) ($document['attachment'] ?? ('document-' . $documentId . '.pdf')));

            // Temporarily Disabled – QR Code Printing Feature
            if ($this->isQrPrintEnabled()) {
                $qrToken = $this->documentModel->ensureQrToken($documentId);
                $verificationUrl = $this->buildVerificationUrl($qrToken);

                PdfOverlayService::streamStampedPdf(
                    $attachmentPath,
                    $verificationUrl,
                    $downloadName
                );
                exit;
            }

            header('Content-Type: application/pdf');
            header('Content-Length: ' . (string) filesize($attachmentPath));
            header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($attachmentPath);
            exit;
        } catch (NotFoundException $e) {
            flash('error', 'Attachment not found.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to view that attachment.', 'error');
            redirect('/documents', 303);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage(), 'error');
            redirect('/documents/attachment/' . $documentId, 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.printable', 'document_id' => $documentId]);
            flash('error', 'We could not prepare that print-ready attachment right now.', 'error');
            redirect('/documents/attachment/' . $documentId, 303);
        }
    }

    public function forward($id)
    {
        $documentId = (int) $id;
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $values = [
            'department_ids' => array_values(array_unique(array_filter(array_map('intval', $_POST['department_ids'] ?? [])))),
            'urgent' => !empty($_POST['urgent']) ? 1 : 0,
            'action_type' => trim($_POST['action_type'] ?? ''),
            'deadline_date' => trim($_POST['deadline_date'] ?? ''),
            'instruction' => trim($_POST['instruction'] ?? '')
        ];

        try {
            $this->requireParentDepartment();
            $document = $this->documentModel->findById($documentId);
            $this->ensureForwardableDocument($document);

            if ($requestMethod === 'POST') {
                validateCsrfOrFail();

                $errors = [];
                $currentDepartmentId = (int) $_SESSION['department_id'];
                $allowedActionTypes = ['For initial/signature', 'For meeting attendance', 'For coordination', 'For review/comments', 'For reference/filing', 'For appropriate action'];

                if (empty($values['department_ids'])) {
                    $errors['department_ids'] = 'Select at least one forward target.';
                } else {
                    foreach ($values['department_ids'] as $targetId) {
                        if (!$this->departmentModel->isValidForwardTargetForParent($currentDepartmentId, $targetId)) {
                            $errors['department_ids'] = 'Forward targets must be another parent department or your own child department.';
                            break;
                        }
                    }
                }

                if (!in_array($values['action_type'], $allowedActionTypes, true)) {
                    $errors['action_type'] = 'Select a valid action type.';
                }

                if ($values['deadline_date'] === '') {
                    $errors['deadline_date'] = 'Deadline date is required.';
                }

                if ($values['instruction'] === '') {
                    $errors['instruction'] = 'Instruction is required.';
                }

                if (!empty($errors)) {
                    throw new ValidationException('Please correct the highlighted fields.', $errors);
                }

                $this->documentModel->forwardDocument($documentId, $values['department_ids'], $_SESSION['user_id'], $currentDepartmentId, [
                    'urgent' => $values['urgent'],
                    'action_type' => $values['action_type'],
                    'deadline_date' => $values['deadline_date'],
                    'instruction' => $values['instruction']
                ]);
                $this->notifyDocumentRouteDepartments($documentId, 'Document forwarded', $document['prefix'] . ' has been forwarded to your department.', $values['department_ids']);

                flash('success', 'Document forwarded successfully.', 'success');
                redirect('/documents/show/' . $documentId, 303);
            }

            $this->renderForwardForm($document);
        } catch (ValidationException $e) {
            if ($requestMethod === 'POST') {
                storeFormState('document_forward_' . $documentId, $values, $e->getErrors(), $e->getMessage());
                redirect('/documents/forward/' . $documentId, 303);
            }

            flash('error', $e->getMessage(), 'error');
            redirect('/documents/show/' . $documentId, 303);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to forward that document.', 'error');
            redirect('/documents', 303);
        } catch (NotFoundException $e) {
            flash('error', 'Document not found.', 'error');
            redirect('/documents', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'documents.forward', 'document_id' => $documentId, 'method' => $requestMethod]);
            if ($requestMethod === 'POST') {
                storeFormState('document_forward_' . $documentId, $values, [], 'We could not forward that document right now. Please try again.');
                redirect('/documents/forward/' . $documentId, 303);
            }

            flash('error', 'We could not open the forward form right now.', 'error');
            redirect('/documents/show/' . $documentId, 303);
        }
    }
}
