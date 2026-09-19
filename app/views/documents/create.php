<?php require_once APPROOT . '/views/layout/header.php'; ?>

<div class="page-hero">
    <div><h1 class="section-title">Create Document</h1></div>
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
    $nextPrefix = $nextPrefix ?? '';
    require APPROOT . '/views/documents/_form.php';
    ?>
</div>

<?php require_once APPROOT . '/views/layout/footer.php'; ?>
