<?php require_once '../app/views/layout/header.php'; ?>

<?php
$filters = $data['filters'] ?? [];
$statusClasses = [
    'Draft' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
    'Released' => 'bg-warning-subtle text-warning border border-warning-subtle',
    'Received' => 'bg-success-subtle text-success border border-success-subtle'
];
?>

<style>
    .documents-hero {
        background: linear-gradient(135deg, #f8fafc 0%, #eef6ff 100%);
        border: 1px solid #dbe7f3;
        border-radius: 18px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .documents-stat { border-radius: 16px; padding: 1rem 1.2rem; color: #0f172a; min-height: 100%; }
    .documents-stat strong { display: block; font-size: 1.7rem; line-height: 1; margin-top: 0.35rem; }
    .documents-filters { border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06); }
    .documents-table-card { border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06); }
    .documents-table thead th { background: #f8fafc; color: #475569; font-size: 0.82rem; letter-spacing: 0.04em; text-transform: uppercase; white-space: nowrap; }
    .documents-table tbody tr:hover { background: #f8fbff; }
    .documents-title { font-weight: 600; color: #0f172a; }
    .documents-meta { color: #64748b; font-size: 0.85rem; }
    .status-pill { border-radius: 999px; font-size: 0.78rem; font-weight: 700; padding: 0.45rem 0.8rem; display: inline-block; }
    .release-empty { color: #94a3b8; font-style: italic; }
</style>

<div class="documents-hero d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div><h2 class="mb-1">Documents</h2></div>
    <div><a href="<?php echo URLROOT; ?>/documents/create" class="btn btn-primary btn-lg">Create New Document</a></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Use this list to monitor all documents visible to your department. Apply filters to narrow results, then open <strong>View Details</strong> to release, receive, clear, note, or forward a document based on your role.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="documents-stat" style="background:#e0f2fe;"><span class="text-uppercase small text-muted">Visible Documents</span><strong><?php echo (int) ($data['total_documents'] ?? 0); ?></strong></div></div>
    <div class="col-md-3"><div class="documents-stat" style="background:#fef3c7;"><span class="text-uppercase small text-muted">Released</span><strong><?php echo (int) (($data['status_counts']['Released'] ?? 0)); ?></strong></div></div>
    <div class="col-md-3"><div class="documents-stat" style="background:#e2e8f0;"><span class="text-uppercase small text-muted">Draft</span><strong><?php echo (int) (($data['status_counts']['Draft'] ?? 0)); ?></strong></div></div>
    <div class="col-md-3"><div class="documents-stat" style="background:#dcfce7;"><span class="text-uppercase small text-muted">Received</span><strong><?php echo (int) (($data['status_counts']['Received'] ?? 0)); ?></strong></div></div>
</div>

<div class="card documents-filters mb-4">
    <div class="card-body p-4">
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
            <div class="col-lg-1 col-md-6 d-grid"><button type="submit" class="btn btn-primary">Apply</button></div>
            <div class="col-lg-12 d-flex flex-wrap gap-2 pt-1"><a href="<?php echo URLROOT; ?>/documents" class="btn btn-outline-secondary btn-sm">Reset Filters</a></div>
        </form>
    </div>
</div>

<div class="documents-table-card bg-white">
    <div class="table-responsive">
        <table class="table documents-table align-middle mb-0">
            <thead>
                <tr><th>Document</th><th>Type</th><th>Status</th><th>Created</th><th>Date Released</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($data['documents'])): ?>
                    <?php foreach ($data['documents'] as $doc): ?>
                        <?php $status = $doc['status'] ?? 'Unknown'; $statusClass = $statusClasses[$status] ?? 'bg-light text-dark border'; $createdAt = !empty($doc['created_at']) ? date('M d, Y h:i A', strtotime($doc['created_at'])) : ''; $releasedAt = !empty($doc['released_at']) ? date('M d, Y h:i A', strtotime($doc['released_at'])) : ''; ?>
                        <tr>
                            <td><div class="documents-title"><?php echo htmlspecialchars($doc['title']); ?></div><div class="documents-meta"><?php echo htmlspecialchars($doc['prefix']); ?></div></td>
                            <td><span class="badge text-bg-light border"><?php echo htmlspecialchars($doc['type'] ?: 'Unspecified'); ?></span></td>
                            <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            <td><?php echo htmlspecialchars($createdAt); ?></td>
                            <td><?php echo $releasedAt !== '' ? htmlspecialchars($releasedAt) : '<span class="release-empty">-</span>'; ?></td>
                            <td class="text-end"><a href="<?php echo URLROOT; ?>/documents/show/<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-5">No documents found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
