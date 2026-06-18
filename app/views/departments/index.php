<?php require_once '../app/views/layout/header.php'; ?>

<div class="page-hero">
    <div>
        <h1 class="section-title">Department Management</h1>
        <div class="text-muted mt-1">Manage departments and their divisions</div>
    </div>
</div>

<div class="app-card p-4">
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead><tr><th>Department</th><th>Email</th><th>Divisions</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php if (!empty($data['departments'])): ?>
                <?php foreach ($data['departments'] as $department): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($department['department_name']); ?></td>
                        <td><a href="mailto:<?php echo htmlspecialchars($department['email']); ?>"><?php echo htmlspecialchars($department['email'] ?: '-'); ?></a></td>
                        <td><?php echo (int) $department['division_count']; ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo URLROOT; ?>/departments/show/<?php echo (int) $department['id']; ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center text-muted py-5">No departments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
