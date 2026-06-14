<?php require_once '../app/views/layout/header.php'; ?>

<?php
$filters = $data['filters'] ?? [];
?>

<div class="page-hero">
    <div><h1 class="section-title">User Management</h1></div>
</div>

<?php if (!empty($data['success'])): ?><div class="alert alert-success app-card border-0 mb-4"><?php echo htmlspecialchars($data['success']); ?></div><?php endif; ?>
<?php if (!empty($data['error'])): ?><div class="alert alert-danger app-card border-0 mb-4"><?php echo htmlspecialchars($data['error']); ?></div><?php endif; ?>

<div class="app-card list-filter-card p-4 mb-4">
    <form method="GET" action="<?php echo URLROOT; ?>/users" class="row g-3 align-items-end">
        <div class="col-lg-4">
            <label for="user_search" class="form-label fw-semibold">Search Name or ID Number</label>
            <input
                type="search"
                id="user_search"
                name="q"
                class="form-control"
                value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>"
                placeholder="Enter name or ID number"
            >
        </div>
        <div class="col-lg-3">
            <label for="department_filter" class="form-label fw-semibold">Department</label>
            <select id="department_filter" name="department_id" class="form-select">
                <option value="0">All Departments</option>
                <?php foreach (($data['departments'] ?? []) as $department): ?>
                    <option value="<?php echo (int) $department['id']; ?>" <?php echo ((int) ($filters['department_id'] ?? 0) === (int) $department['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($department['division_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label for="role_filter" class="form-label fw-semibold">Role</label>
            <select id="role_filter" name="role" class="form-select">
                <option value="">All Roles</option>
                <?php foreach (($data['roles'] ?? []) as $roleKey => $roleLabel): ?>
                    <option value="<?php echo htmlspecialchars($roleKey); ?>" <?php echo (($filters['role'] ?? '') === $roleKey) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($roleLabel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="<?php echo URLROOT; ?>/users" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="app-card p-4">
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead><tr><th>ID Number</th><th>Name</th><th>Department</th><th>Role</th><th class="text-end">View</th></tr></thead>
            <tbody>
                <?php if (!empty($data['users'])): ?>
                    <?php foreach ($data['users'] as $user): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars($user->id_number); ?></td>
                            <td><div class="fw-semibold"><?php echo htmlspecialchars(trim($user->firstname . ' ' . $user->lastname)); ?></div></td>
                            <td><?php echo htmlspecialchars($user->department_name ?? 'Unassigned'); ?></td>
                            <td><?php echo htmlspecialchars(($data['roles'][$user->role] ?? ucfirst($user->role ?? 'User'))); ?></td>
                            <td class="text-end">
                                <a href="<?php echo URLROOT; ?>/users/show/<?php echo (int) $user->id; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
