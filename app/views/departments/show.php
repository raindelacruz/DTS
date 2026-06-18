<?php require_once '../app/views/layout/header.php'; ?>
<?php
$department = $data['department'];
$edit = $data['department_form'];
$add = $data['division_form'];
$fieldClass = function ($errors, $field) { return isset($errors[$field]) ? ' is-invalid' : ''; };
?>

<div class="page-hero">
    <div>
        <h1 class="section-title"><?php echo htmlspecialchars($department['department_name']); ?></h1>
        <div class="text-muted mt-1">Department details and divisions</div>
    </div>
    <a href="<?php echo URLROOT; ?>/departments" class="btn btn-outline-secondary">Back to Departments</a>
</div>

<?php if (!empty($edit['message'])): ?><div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($edit['message']); ?></div><?php endif; ?>
<?php if (!empty($add['message'])): ?><div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($add['message']); ?></div><?php endif; ?>

<div class="app-card p-4 mb-4">
    <div class="text-muted small text-uppercase fw-semibold mb-3">Edit Department</div>
    <form method="POST" action="<?php echo URLROOT; ?>/departments/update/<?php echo (int) $department['id']; ?>">
        <?php echo csrfInput(); ?>
        <div class="row g-3">
            <?php foreach ([
                'department_name' => 'Department', 'division_name' => 'Primary Office / Unit',
                'code' => 'Code', 'email' => 'Email'
            ] as $field => $label): ?>
                <div class="<?php echo in_array($field, ['department_name', 'division_name'], true) ? 'col-lg-6' : 'col-lg-3'; ?>">
                    <label class="form-label fw-semibold" for="department_<?php echo $field; ?>"><?php echo $label; ?></label>
                    <input id="department_<?php echo $field; ?>" name="<?php echo $field; ?>" type="<?php echo $field === 'email' ? 'email' : 'text'; ?>" class="form-control<?php echo $fieldClass($edit['errors'], $field); ?>" value="<?php echo htmlspecialchars($edit['values'][$field] ?? ''); ?>" required>
                    <?php if (isset($edit['errors'][$field])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($edit['errors'][$field]); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Save Department</button>
    </form>
</div>

<div class="app-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small text-uppercase fw-semibold">Divisions</div>
        <span class="badge-soft"><?php echo count($data['divisions']); ?> division(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead><tr><th>Division</th><th>Code</th><th>Email</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php if ($data['divisions']): foreach ($data['divisions'] as $division): ?>
                <?php $divisionFormId = 'division_form_' . (int) $division['id']; ?>
                <tr>
                    <td><input class="form-control" form="<?php echo $divisionFormId; ?>" name="division_name" value="<?php echo htmlspecialchars($division['division_name']); ?>" required></td>
                    <td><input class="form-control" form="<?php echo $divisionFormId; ?>" name="code" value="<?php echo htmlspecialchars($division['code']); ?>" required></td>
                    <td><input class="form-control" form="<?php echo $divisionFormId; ?>" type="email" name="email" value="<?php echo htmlspecialchars($division['email']); ?>" required></td>
                    <td class="text-end">
                        <form id="<?php echo $divisionFormId; ?>" method="POST" action="<?php echo URLROOT; ?>/departments/updateDivision/<?php echo (int) $division['id']; ?>">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="parent_id" value="<?php echo (int) $department['id']; ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No divisions have been added.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="app-card p-4">
    <div class="text-muted small text-uppercase fw-semibold mb-3">Add Division</div>
    <form method="POST" action="<?php echo URLROOT; ?>/departments/addDivision/<?php echo (int) $department['id']; ?>">
        <?php echo csrfInput(); ?>
        <div class="row g-3 align-items-end">
            <?php foreach (['division_name' => 'Division', 'code' => 'Code', 'email' => 'Email'] as $field => $label): ?>
                <div class="<?php echo $field === 'division_name' ? 'col-lg-5' : ($field === 'code' ? 'col-lg-2' : 'col-lg-3'); ?>">
                    <label class="form-label fw-semibold" for="new_<?php echo $field; ?>"><?php echo $label; ?></label>
                    <input id="new_<?php echo $field; ?>" name="<?php echo $field; ?>" type="<?php echo $field === 'email' ? 'email' : 'text'; ?>" class="form-control<?php echo $fieldClass($add['errors'], $field); ?>" value="<?php echo htmlspecialchars($add['values'][$field] ?? ''); ?>" required>
                    <?php if (isset($add['errors'][$field])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($add['errors'][$field]); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="col-lg-2"><button class="btn btn-primary w-100" type="submit">Add Division</button></div>
        </div>
    </form>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
