<?php require_once '../app/views/layout/header.php'; ?>

<div class="page-hero">
    <div><h1 class="section-title">Create Document</h1></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Enter the document title and type, add <strong>Particulars</strong> when you need more context, and use <strong>Document Reference</strong> only when this upload is related to an earlier document. Then choose the routing path, select at least one <strong>TO</strong> department, add <strong>THRU</strong> only when clearance is needed, and attach a file if users need the source document.</p>
</div>

<div class="app-card p-4 p-lg-5">
    <?php
    $formAction = $formAction ?? (URLROOT . '/documents/store');
    $documentData = $documentData ?? ['title' => '', 'type' => ''];
    $selectedThruDepartmentId = $selectedThruDepartmentId ?? 0;
    $selectedToDepartmentIds = $selectedToDepartmentIds ?? [];
    $selectedCcDepartmentIds = $selectedCcDepartmentIds ?? [];
    $submitLabel = $submitLabel ?? 'Create Document';
    $cancelUrl = $cancelUrl ?? (URLROOT . '/documents');
    $showAttachmentHint = $showAttachmentHint ?? false;
    require '../app/views/documents/_form.php';
    ?>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
