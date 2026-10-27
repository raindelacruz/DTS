<?php require_once APPROOT . '/views/layout/header.php'; ?>

<?php
$user = $data['user'];
$fullName = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
$roleLabel = $data['roles'][$user->role] ?? ucfirst($user->role ?? 'User');
$statusStyle = ($user->status ?? '') === 'active'
    ? 'background:#dcfce7; color:#166534;'
    : 'background:#e2e8f0; color:#334155;';
?>

<div class="page-hero">
    <div>
        <h1 class="section-title">User Details</h1>
        <div class="text-muted mt-1">System Admin account controls</div>
    </div>
    <a href="<?php echo URLROOT; ?>/users" class="btn btn-outline-secondary">Back to Users</a>
</div>

<?php if (!empty($user->mfa_enabled)): ?>
<div class="app-card p-4 mt-4">
    <h3 class="h5">MFA recovery</h3>
    <p class="text-muted">Reset only after verifying the user's identity through an approved offline process.</p>
    <form method="POST" action="<?php echo URLROOT; ?>/users/resetMfa/<?php echo (int) $user->id; ?>" onsubmit="return confirm('Reset MFA for this user?');">
        <?php echo csrfInput(); ?>
        <?php echo adminActionInputs('mfa_reset',(int)$user->id,true); ?>
        <button class="btn btn-outline-danger" type="submit">Reset MFA enrollment</button>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($data['success'])): ?><div class="alert alert-success app-card border-0 mb-4"><?php echo htmlspecialchars($data['success']); ?></div><?php endif; ?>
<?php if (!empty($data['error'])): ?><div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($data['error']); ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="app-card p-4 h-100">
            <div class="text-muted small text-uppercase fw-semibold">Account</div>
            <div class="mt-3">
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($fullName); ?></div>
                <div class="text-muted mt-1"><?php echo htmlspecialchars($user->email ?? '-'); ?></div>
            </div>
            <div class="border-top mt-4 pt-4">
                <div class="text-muted small">ID Number</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($user->id_number ?? ''); ?></div>
            </div>
            <div class="mt-3">
                <div class="text-muted small">Department</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($user->department_name ?? 'Unassigned'); ?></div>
            </div>
            <div class="mt-3">
                <div class="text-muted small">Role</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($roleLabel); ?></div>
            </div>
            <div class="mt-3">
                <div class="text-muted small">Status</div>
                <span class="badge-soft" style="<?php echo $statusStyle; ?>"><?php echo htmlspecialchars(ucfirst($user->status ?? 'inactive')); ?></span>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="app-card p-4 p-lg-5">
            <div class="text-muted small text-uppercase fw-semibold mb-3">Change Password</div>
            <form action="<?php echo URLROOT; ?>/users/updateUserPassword/<?php echo (int) $user->id; ?>" method="POST">
                <?php echo adminActionInputs('password',(int)$user->id,true); ?>
                <?php echo csrfInput(); ?>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-4">Update Password</button>
            </form>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-6">
                <div class="app-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-3">Change Role</div>
                    <form action="<?php echo URLROOT; ?>/users/updateRole/<?php echo (int) $user->id; ?>" method="POST" onsubmit="return confirm('Change role for <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>? Personal history remains attached to the user; manager access follows the selected role and office.');">
                        <?php echo csrfInput(); ?>
                        <label class="form-label fw-semibold">Role</label>
                        <?php echo adminActionInputs('role',(int)$user->id); ?>
                        <select name="role" class="form-select" required>
                            <?php foreach (($data['roles'] ?? []) as $roleValue => $roleName): ?>
                                <option value="<?php echo htmlspecialchars($roleValue); ?>" <?php echo (string) $user->role === (string) $roleValue ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($roleName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label fw-semibold mt-3">Reason</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Optional audit note"></textarea>
                        <button type="submit" class="btn btn-outline-primary mt-3">Save Role</button>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <div class="app-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-3">Change Status</div>
                    <form action="<?php echo URLROOT; ?>/users/updateStatus/<?php echo (int) $user->id; ?>" method="POST">
                        <?php if (!empty($data['registration_review'])): ?>
                            <p>Registration review: <?php echo htmlspecialchars(ucfirst($data['registration_review']['decision'])); ?>. Verify employee eligibility independently before activation. Selecting inactive rejects or suspends the account.</p>
                            <?php if (!empty($data['registration_review']['reason'])): ?><p>Last review note: <?php echo htmlspecialchars($data['registration_review']['reason']); ?></p><?php endif; ?>
                            <label for="registration_review_reason">Verification or rejection note</label>
                            <textarea id="registration_review_reason" name="reason" class="form-control" rows="2" minlength="10" maxlength="500" required placeholder="Record the verification reference or reason; omit passwords and invitation codes."></textarea>
                        <?php endif; ?>
                        <?php echo csrfInput(); ?>
                        <label class="form-label fw-semibold">Status</label>
                        <?php echo adminActionInputs('status',(int)$user->id); ?>
                        <select name="status" class="form-select" required>
                            <option value="active" <?php echo ($user->status ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($user->status ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary mt-3">Save Status</button>
                    </form>
                </div>
            </div>

            <div class="col-12">
                <div class="app-card p-4">
                    <div class="text-muted small text-uppercase fw-semibold mb-3">Change Department</div>
                    <form action="<?php echo URLROOT; ?>/users/updateDepartment/<?php echo (int) $user->id; ?>" method="POST" onsubmit="return confirm('Change office for <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>? Personal history remains attached to the user; manager access follows the selected role and office.');">
                        <?php echo csrfInput(); ?>
                        <label class="form-label fw-semibold">Department</label>
                        <?php echo adminActionInputs('department',(int)$user->id); ?>
                        <select name="department_id" class="form-select" required>
                            <option value="">Select department</option>
                            <?php foreach (($data['departments'] ?? []) as $department): ?>
                                <?php $officeLevel = ucfirst((string) ($department['office_level'] ?? 'department')); ?>
                                <option value="<?php echo (int) $department['id']; ?>" <?php echo (int) ($user->department_id ?? 0) === (int) $department['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($department['division_name'] . ' (' . $officeLevel . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label fw-semibold mt-3">Reason</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Optional audit note"></textarea>
                        <button type="submit" class="btn btn-outline-primary mt-3">Save Department</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="app-card p-4 mt-4">
    <div class="text-muted small text-uppercase fw-semibold mb-3">Role and Office Change History</div>
    <?php if (empty($data['access_history'])): ?>
        <div class="text-muted">No role or office changes recorded yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Role</th>
                        <th>Office</th>
                        <th>Changed By</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['access_history'] as $history): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($history->created_at ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(($data['roles'][$history->old_role] ?? ucfirst((string) $history->old_role)) . ' to ' . ($data['roles'][$history->new_role] ?? ucfirst((string) $history->new_role))); ?></td>
                            <td><?php echo htmlspecialchars(($history->old_department_name ?? 'Unassigned') . ' to ' . ($history->new_department_name ?? 'Unassigned')); ?></td>
                            <td><?php echo htmlspecialchars($history->changed_by_name ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars($history->reason ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once APPROOT . '/views/layout/footer.php'; ?>
