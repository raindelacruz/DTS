<?php require_once '../app/views/layout/header.php'; ?>

<h3 class="mb-4">Dashboard</h3>

<div class="row">

    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <h5>Total Documents</h5>
                <h2><?php echo $data['total']; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <h5>Pending</h5>
                <h2><?php echo $data['pending']; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <h5>Completed</h5>
                <h2><?php echo $data['completed']; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-white bg-dark mb-3">
            <div class="card-body">
                <h5>Your Department</h5>
                <h2><?php echo $data['department_docs']; ?></h2>
            </div>
        </div>
    </div>

</div>
<?php require_once '../app/views/layout/footer.php'; ?>
