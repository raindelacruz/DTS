<?php require_once '../app/views/layout/header.php'; ?>

<?php
$statusClasses = [
    'Draft' => 'background:#e2e8f0; color:#334155;',
    'Released' => 'background:#fef3c7; color:#92400e;',
    'Received' => 'background:#dcfce7; color:#166534;'
];
$filters = $data['filters'] ?? [];
?>

<div class="page-hero">
    <div><h1 class="section-title"><?php echo htmlspecialchars($data['title']); ?></h1></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>This screen shows documents routed into your department. Filter the list when needed, then open a record to receive it or complete the next manager action shown on the details page.</p>
</div>

<div class="app-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label fw-semibold">Keyword</label>
            <input type="text" name="keyword" class="form-control" value="<?php echo htmlspecialchars($filters['keyword'] ?? ''); ?>">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach (['Draft', 'Released', 'Received'] as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo (($filters['status'] ?? '') === $status) ? 'selected' : ''; ?>><?php echo $status; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label fw-semibold">Type</label>
            <select name="type" class="form-select">
                <option value="">All Types</option>
                <?php foreach (($data['types'] ?? []) as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (($filters['type'] ?? '') === $type) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label fw-semibold">Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label fw-semibold">Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
        </div>
        <div class="col-lg-1 col-md-6 d-grid">
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
        <div class="col-12">
            <a href="<?php echo URLROOT; ?>/documents/incoming" class="btn btn-outline-secondary btn-sm">Reset Filters</a>
        </div>
    </form>
</div>

<div class="app-card p-4">
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Released</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data['documents'])): ?>
                    <?php foreach ($data['documents'] as $doc): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($doc['title']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($doc['prefix']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($doc['type'] ?: '-'); ?></td>
                            <td>
                                <span class="badge-soft" style="<?php echo $statusClasses[$doc['status']] ?? 'background:#e5e7eb; color:#111827;'; ?>">
                                    <?php echo htmlspecialchars($doc['status']); ?>
                                </span>
                            </td>
                            <td><?php echo !empty($doc['created_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime($doc['created_at']))) : '-'; ?></td>
                            <td><?php echo !empty($doc['released_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime($doc['released_at']))) : '-'; ?></td>
                            <td class="text-end">
                                <a href="<?php echo URLROOT; ?>/documents/show/<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5"><?php echo htmlspecialchars($data['empty_message']); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
