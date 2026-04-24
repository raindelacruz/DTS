<?php require_once '../app/views/layout/header.php'; ?>

<div class="page-hero">
    <div>
        <h1 class="section-title">Dashboard</h1>
    </div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Start here to monitor document volume, pending work, and department activity. Use the action buttons below to open the full document list or create a new routing entry.</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="app-card h-100 p-4" style="background:linear-gradient(135deg, #dcfce7 0%, #ecfdf5 100%);">
            <div class="text-uppercase small text-muted fw-semibold">Total Documents</div>
            <div class="display-6 fw-bold mt-2"><?php echo $data['total']; ?></div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="app-card h-100 p-4" style="background:linear-gradient(135deg, #fef3c7 0%, #fff7ed 100%);">
            <div class="text-uppercase small text-muted fw-semibold">Pending</div>
            <div class="display-6 fw-bold mt-2"><?php echo $data['pending']; ?></div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="app-card h-100 p-4" style="background:linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);">
            <div class="text-uppercase small text-muted fw-semibold">Completed</div>
            <div class="display-6 fw-bold mt-2"><?php echo $data['completed']; ?></div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="app-card h-100 p-4" style="background:linear-gradient(135deg, #ede9fe 0%, #f5f3ff 100%);">
            <div class="text-uppercase small text-muted fw-semibold">Your Department</div>
            <div class="display-6 fw-bold mt-2"><?php echo $data['department_docs']; ?></div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-3">
    <a href="<?php echo URLROOT; ?>/documents" class="btn btn-primary">Documents</a>
    <a href="<?php echo URLROOT; ?>/documents/create" class="btn btn-outline-secondary">Create Document</a>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
