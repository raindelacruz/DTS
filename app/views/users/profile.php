<?php require_once '../app/views/layout/header.php'; ?>

<div class="page-hero">
    <div><h1 class="section-title">My Profile</h1></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Use this page to keep your <strong>department</strong> and <strong>email address</strong> current. Other account details are read-only so records stay consistent across the tracking system.</p>
</div>

<?php if (!empty($data['success'])): ?><div class="alert alert-success app-card border-0 mb-4"><?php echo htmlspecialchars($data['success']); ?></div><?php endif; ?>
<?php if (!empty($data['error'])): ?><div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($data['error']); ?></div><?php endif; ?>
<?php if (!empty($data['message'])): ?><div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($data['message']); ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="app-card p-4 h-100">
            <div class="text-muted small text-uppercase fw-semibold">Account Summary</div>
            <div class="mt-3">
                <div class="fw-bold fs-5"><?php echo htmlspecialchars(trim(($data['user']->firstname ?? '') . ' ' . ($data['user']->lastname ?? ''))); ?></div>
                <div class="text-muted mt-1">ID Number: <?php echo htmlspecialchars($data['user']->id_number ?? ''); ?></div>
                <div class="text-muted">Role: <?php echo htmlspecialchars(ucfirst($data['user']->role ?? 'user')); ?></div>
                <div class="text-muted">Status: <?php echo htmlspecialchars(ucfirst($data['user']->status ?? 'inactive')); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="app-card p-4 p-lg-5">
            <form action="<?php echo URLROOT; ?>/users/updateProfile" method="POST">
                <?php echo csrfInput(); ?>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Department</label>
                        <select name="department_id" class="form-select <?php echo !empty($data['errors']['department_id']) ? 'is-invalid' : ''; ?>" required>
                            <option value="">Select department</option>
                            <?php foreach (($data['departments'] ?? []) as $department): ?>
                                <option value="<?php echo $department['id']; ?>" <?php echo ((int) ($data['values']['department_id'] ?? 0) === (int) $department['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['division_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($data['errors']['department_id'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($data['errors']['department_id']); ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control <?php echo !empty($data['errors']['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($data['values']['email'] ?? ''); ?>" required>
                        <?php if (!empty($data['errors']['email'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($data['errors']['email']); ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">First Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['user']->firstname ?? ''); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['user']->lastname ?? ''); ?>" disabled>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-4 pt-2">
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                    <a href="<?php echo URLROOT; ?>/dashboard" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
