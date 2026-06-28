<?php require_once APPROOT . '/views/layout/header.php'; ?>
<?php
$form = $data['department_form'];
$fieldClass = function ($field) use ($form) { return isset($form['errors'][$field]) ? ' is-invalid' : ''; };
?>

<div class="page-hero">
    <div>
        <h1 class="section-title">Add Department</h1>
        <div class="text-muted mt-1">Create a department and its primary office or unit</div>
    </div>
    <a href="<?php echo URLROOT; ?>/departments" class="btn btn-outline-secondary">Back to Departments</a>
</div>

<?php if (!empty($form['message'])): ?>
    <div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($form['message']); ?></div>
<?php endif; ?>

<div class="app-card p-4">
    <form method="POST" action="<?php echo URLROOT; ?>/departments/store">
        <?php echo csrfInput(); ?>
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label fw-semibold" for="department_name">Department</label>
                <input id="department_name" name="department_name" type="text" maxlength="150" class="form-control<?php echo $fieldClass('department_name'); ?>" value="<?php echo htmlspecialchars($form['values']['department_name'] ?? ''); ?>" required autofocus>
                <?php if (isset($form['errors']['department_name'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form['errors']['department_name']); ?></div><?php endif; ?>
            </div>
            <div class="col-lg-6">
                <label class="form-label fw-semibold" for="division_name">Primary Office / Unit</label>
                <input id="division_name" name="division_name" type="text" maxlength="150" class="form-control<?php echo $fieldClass('division_name'); ?>" value="<?php echo htmlspecialchars($form['values']['division_name'] ?? ''); ?>" required>
                <?php if (isset($form['errors']['division_name'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form['errors']['division_name']); ?></div><?php endif; ?>
                <div class="form-text">This is the department's main receiving office. Divisions can be added afterward.</div>
            </div>
            <div class="col-lg-4">
                <label class="form-label fw-semibold" for="code">Code</label>
                <input id="code" name="code" type="text" maxlength="50" class="form-control text-uppercase<?php echo $fieldClass('code'); ?>" value="<?php echo htmlspecialchars($form['values']['code'] ?? ''); ?>" required>
                <?php if (isset($form['errors']['code'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form['errors']['code']); ?></div><?php endif; ?>
            </div>
            <div class="col-lg-8">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="150" class="form-control<?php echo $fieldClass('email'); ?>" value="<?php echo htmlspecialchars($form['values']['email'] ?? ''); ?>" required>
                <?php if (isset($form['errors']['email'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($form['errors']['email']); ?></div><?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary" type="submit">Add Department</button>
            <a href="<?php echo URLROOT; ?>/departments" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once APPROOT . '/views/layout/footer.php'; ?>
