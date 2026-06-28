<?php require_once APPROOT . '/views/layout/header.php'; ?>

<?php
$filters = $data['filters'] ?? [];
$statusClasses = [
    'Draft' => 'bg-info-subtle text-info border border-info-subtle',
    'Released' => 'bg-warning-subtle text-warning border border-warning-subtle',
    'Received' => 'bg-primary-subtle text-primary border border-primary-subtle',
    'Delegated' => 'bg-primary-subtle text-primary border border-primary-subtle',
    'For Action' => 'bg-warning-subtle text-warning border border-warning-subtle',
    'Completed' => 'bg-success-subtle text-success border border-success-subtle',
    'Returned' => 'bg-danger-subtle text-danger border border-danger-subtle',
    'Cancelled' => 'bg-danger-subtle text-danger border border-danger-subtle'
];
?>

<style>
    .das-stat { border-radius: 16px; padding: 0.85rem 1rem; min-height: 100%; }
    .das-stat strong { display: block; font-size: 1.45rem; line-height: 1; margin-top: 0.25rem; }
    .das-table-card { border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06); }
    .das-title { font-weight: 700; color: #0f172a; }
    .das-meta { color: #64748b; font-size: 0.84rem; }
    .status-pill { border-radius: 999px; font-size: 0.74rem; font-weight: 800; padding: 0.34rem 0.62rem; display: inline-block; white-space: nowrap; }
    .das-filter-card { padding: 1rem !important; overflow: hidden; }
    .das-filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr));
        gap: 0.75rem;
        align-items: end;
        width: 100%;
    }
    .das-filter-form > * { min-width: 0; }
    .das-filter-form .form-label { white-space: nowrap; }
    .das-filter-actions { display: grid; }
    .das-filter-form .btn { min-height: 46px; }

    @media (max-width: 575.98px) {
        .das-filter-card { padding: 0.75rem !important; }
        .das-filter-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem 0.625rem;
        }
        .das-filter-form > div:first-child,
        .das-filter-form > div:nth-child(3),
        .das-filter-form > div:nth-child(4),
        .das-filter-form > div:nth-child(5) {
            grid-column: 1 / -1;
        }
    }
</style>

<div class="page-hero compact">
    <div>
        <h1 class="section-title">Department Action Slip</h1>
        <div class="text-muted small mt-1">Direct action slips across departments, divisions, and staff.</div>
    </div>
    <?php if (!empty($data['can_create'])): ?>
        <a href="<?php echo URLROOT; ?>/actionSlips/create" class="btn btn-primary"><?php echo (($_SESSION['role'] ?? '') === 'staff') ? 'Draft Action Slip' : 'New Action Slip'; ?></a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4 das-stat-row">
    <div class="col-md-3"><div class="das-stat" style="background:#e0f2fe;"><span class="text-uppercase small text-muted">Visible Slips</span><strong><?php echo count($data['slips'] ?? []); ?></strong></div></div>
    <div class="col-md-3"><div class="das-stat" style="background:#fef3c7;"><span class="text-uppercase small text-muted">For Action</span><strong><?php echo (int) (($data['status_counts']['For Action'] ?? 0) + ($data['status_counts']['Delegated'] ?? 0)); ?></strong></div></div>
    <div class="col-md-3"><div class="das-stat" style="background:#dcfce7;"><span class="text-uppercase small text-muted">Completed</span><strong><?php echo (int) ($data['status_counts']['Completed'] ?? 0); ?></strong></div></div>
    <div class="col-md-3"><div class="das-stat" style="background:#fee2e2;"><span class="text-uppercase small text-muted">Returned</span><strong><?php echo (int) ($data['status_counts']['Returned'] ?? 0); ?></strong></div></div>
</div>

<div class="app-card list-filter-card das-filter-card mb-4">
    <form method="GET" class="das-filter-form">
        <div>
            <label class="form-label fw-semibold">Keyword</label>
            <input type="text" name="keyword" class="form-control" placeholder="Tracking no. or instruction" value="<?php echo htmlspecialchars($filters['keyword'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach (($data['statuses'] ?? []) as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (($filters['status'] ?? '') === $status) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label fw-semibold">Department</label>
            <select name="department_id" class="form-select">
                <option value="">All Departments</option>
                <?php foreach (($data['departments'] ?? []) as $department): ?>
                    <option value="<?php echo (int) $department['id']; ?>" <?php echo ((int) ($filters['department_id'] ?? 0) === (int) $department['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['division_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label fw-semibold">Division</label>
            <select name="division_id" class="form-select">
                <option value="">All Divisions</option>
                <?php foreach (($data['divisions'] ?? []) as $division): ?>
                    <option value="<?php echo (int) $division['id']; ?>" <?php echo ((int) ($filters['division_id'] ?? 0) === (int) $division['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($division['division_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label fw-semibold">Assigned Staff</label>
            <select name="assigned_staff_id" class="form-select">
                <option value="">All Staff</option>
                <?php foreach (($data['staff'] ?? []) as $staff): ?>
                    <?php $name = trim(($staff['firstname'] ?? '') . ' ' . (!empty($staff['middle_initial']) ? $staff['middle_initial'] . '. ' : '') . ($staff['lastname'] ?? '')); ?>
                    <option value="<?php echo (int) $staff['id']; ?>" <?php echo ((int) ($filters['assigned_staff_id'] ?? 0) === (int) $staff['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="das-filter-actions"><button type="submit" class="btn btn-primary">Apply</button></div>
        <div class="das-filter-actions"><a href="<?php echo URLROOT; ?>/actionSlips" class="btn btn-outline-secondary">Reset</a></div>
    </form>
</div>

<div class="das-table-card bg-white">
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead><tr><th>Action Slip</th><th>Action</th><th>Status</th><th>Current Owner</th><th>Assigned Staff</th><th>Deadline</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                <?php if (!empty($data['slips'])): ?>
                    <?php foreach ($data['slips'] as $slip): ?>
                        <?php $status = $slip['status'] ?? ''; $statusClass = $statusClasses[$status] ?? 'bg-light text-dark border'; ?>
                        <tr>
                            <td>
                                <div class="das-title"><?php echo htmlspecialchars($slip['slip_number']); ?></div>
                                <div class="das-meta"><?php echo !empty($slip['urgent']) ? 'Urgent - ' : ''; ?><?php echo htmlspecialchars(date('M d, Y', strtotime($slip['date_received']))); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($slip['required_action']); ?></td>
                            <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            <td><?php echo htmlspecialchars($slip['current_division_name'] ?: ($slip['current_department_name'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars($slip['assigned_staff_name'] ?: '-'); ?></td>
                            <td><?php echo !empty($slip['deadline']) ? htmlspecialchars(date('M d, Y', strtotime($slip['deadline']))) : '<span class="text-muted">-</span>'; ?></td>
                            <td class="text-end"><a href="<?php echo URLROOT; ?>/actionSlips/show/<?php echo (int) $slip['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No department action slips found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderServerPagination($data['pagination'] ?? null); ?>

<?php require_once APPROOT . '/views/layout/footer.php'; ?>
