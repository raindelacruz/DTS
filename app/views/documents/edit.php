<?php require_once '../app/views/layout/header.php'; ?>

<div class="page-hero">
    <div><h1 class="section-title">Edit Document</h1></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Update the draft details, including <strong>Particulars</strong> and any optional <strong>Document Reference</strong>, before release. Use the checkbox lists for <strong>TO</strong> and <strong>CC</strong> to make selection easier, and keep <strong>THRU</strong> as a single clearance step only when needed.</p>
</div>

<div class="app-card p-4 p-lg-5">
    <?php require '../app/views/documents/_form.php'; ?>
</div>

<?php require_once '../app/views/layout/footer.php'; ?>
