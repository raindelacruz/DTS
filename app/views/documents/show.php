<?php require_once '../app/views/layout/header.php'; ?>

<?php

<?php if ($_SESSION['role'] == 'custodian' 
    && $data['document']['status'] == 'Draft'
    && $data['document']['origin_department_id'] == $_SESSION['department_id']): ?>

    <a href="/documents/release/<?= $data['document']['id']; ?>" 
       class="btn btn-success">
        Release Document
    </a>

<?php endif; ?>

<?php if ($_SESSION['role'] == 'custodian' 
    && $data['document']['status'] == 'Released'
    && $data['document']['destination_department_id'] == $_SESSION['department_id']): ?>

    <a href="/documents/receive/<?= $data['document']['id']; ?>" 
       class="btn btn-primary">
        Receive Document
    </a>

<?php endif; ?>

<?php if(!empty($data['document']['attachment'])): ?>
    <p>
        <strong>Attachment:</strong>
        <a href="<?php echo URLROOT . '/uploads/' . $data['document']['attachment']; ?>" 
           target="_blank" 
           class="btn btn-sm btn-primary">
            Download File
        </a>
    </p>
<?php endif; ?>

<?php require_once '../app/views/layout/footer.php'; ?>