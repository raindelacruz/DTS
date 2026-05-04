<?php require_once '../app/views/layout/header.php'; ?>

<?php
$activeCount = 0;
$inactiveCount = 0;
foreach (($data['users'] ?? []) as $userSummary) {
    if (($userSummary->status ?? '') === 'active') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}
?>

<div class="page-hero">
    <div><h1 class="section-title">User Management</h1></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Review new registrations here, activate only verified accounts, and assign each user the correct <strong>role</strong>. Users manage only their own <strong>department</strong> and <strong>email</strong> from the profile page.</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="app-card p-4 h-100" style="background:linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);"><div class="text-uppercase small text-muted fw-semibold">Total Users</div><div class="display-6 fw-bold mt-2"><?php echo count($data['users'] ?? []); ?></div></div></div>
    <div class="col-md-4"><div class="app-card p-4 h-100" style="background:linear-gradient(135deg, #dcfce7 0%, #ecfdf5 100%);"><div class="text-uppercase small text-muted fw-semibold">Active</div><div class="display-6 fw-bold mt-2"><?php echo $activeCount; ?></div></div></div>
    <div class="col-md-4"><div class="app-card p-4 h-100" style="background:linear-gradient(135deg, #fee2e2 0%, #fff1f2 100%);"><div class="text-uppercase small text-muted fw-semibold">Inactive</div><div class="display-6 fw-bold mt-2"><?php echo $inactiveCount; ?></div></div></div>
</div>

<?php if (!empty($data['success'])): ?><div class="alert alert-success app-card border-0 mb-4"><?php echo htmlspecialchars($data['success']); ?></div><?php endif; ?>
<?php if (!empty($data['error'])): ?><div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($data['error']); ?></div><?php endif; ?>

<div class="app-card p-4">
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead><tr><th>ID Number</th><th>Name</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th>Registered</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                <?php if (!empty($data['users'])): ?>
                    <?php foreach ($data['users'] as $user): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars($user->id_number); ?></td>
                            <td><div class="fw-semibold"><?php echo htmlspecialchars(trim($user->firstname . ' ' . $user->lastname)); ?></div></td>
                            <td><?php echo htmlspecialchars($user->email ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user->department_name ?? 'Unassigned'); ?></td>
                            <td style="min-width: 220px;">
                                <form action="<?php echo URLROOT; ?>/users/updateRole/<?php echo $user->id; ?>" method="POST" class="d-flex gap-2 align-items-center justify-content-start">
                                    <?php echo csrfInput(); ?>
                                    <select name="role" class="form-select form-select-sm" aria-label="Role for <?php echo htmlspecialchars(trim($user->firstname . ' ' . $user->lastname)); ?>">
                                        <?php foreach (($data['roles'] ?? []) as $roleValue => $roleLabel): ?>
                                            <option value="<?php echo htmlspecialchars($roleValue); ?>" <?php echo (string) $user->role === (string) $roleValue ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($roleLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                            <td><span class="badge-soft" style="<?php echo $user->status === 'active' ? 'background:#dcfce7; color:#166534;' : 'background:#e2e8f0; color:#334155;'; ?>"><?php echo htmlspecialchars(ucfirst($user->status)); ?></span></td>
                            <td><?php echo !empty($user->created_at) ? htmlspecialchars(date('M d, Y h:i A', strtotime($user->created_at))) : ''; ?></td>
                            <td class="text-end">
                                <?php if ($user->status === 'inactive'): ?><form action="<?php echo URLROOT; ?>/users/activate/<?php echo $user->id; ?>" method="POST" class="d-inline"><?php echo csrfInput(); ?><button type="submit" class="btn btn-sm btn-success">Activate</button></form><?php else: ?><form action="<?php echo URLROOT; ?>/users/deactivate/<?php echo $user->id; ?>" method="POST" class="d-inline"><?php echo csrfInput(); ?><button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button></form><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
