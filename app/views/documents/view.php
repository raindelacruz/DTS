<?php require_once '../app/views/layout/header.php'; ?>

<h2>Document Details</h2>

<div class="card mb-3">
    <div class="card-body">
        <p><strong>Prefix:</strong> <?php echo $document['prefix']; ?></p>
        <p><strong>Title:</strong> <?php echo $document['title']; ?></p>
        <p><strong>Status:</strong> 
            <span class="badge bg-secondary">
                <?php echo $document['status']; ?>
            </span>
        </p>

        <?php if (!empty($document['attachment'])): ?>
            <p>
                <strong>Attachment:</strong>
                <a href="<?php echo URLROOT; ?>/uploads/<?php echo $document['attachment']; ?>" 
                   target="_blank"
                   class="btn btn-sm btn-outline-primary">
                   View File
                </a>
            </p>
        <?php endif; ?>

        <!-- RELEASE BUTTON -->
        <?php if (
            $document['status'] === 'Draft' &&
            $document['origin_department_id'] == $_SESSION['department_id']
        ): ?>
            <a href="<?php echo URLROOT; ?>/documents/release/<?php echo $document['id']; ?>"
               class="btn btn-success"
               onclick="return confirm('Release this document?');">
               Release Document
            </a>
        <?php endif; ?>

        <!-- RECEIVE BUTTON -->
		<?php if (
		    $document['status'] === 'Released' &&
		    $document['destination_department_id'] == $_SESSION['department_id']
		): ?>
		    <a href="<?php echo URLROOT; ?>/documents/receive/<?php echo $document['id']; ?>"
		       class="btn btn-primary"
		       onclick="return confirm('Receive this document?');">
		       Receive Document
		    </a>
		<?php endif; ?>
		<!-- FORWARD BUTTON -->
		<?php if (
		    $document['status'] === 'Received' &&
		    $document['destination_department_id'] == $_SESSION['department_id']
		): ?>
		    <a href="<?php echo URLROOT; ?>/documents/forward/<?php echo $document['id']; ?>"
		       class="btn btn-warning">
		       Forward Document
		    </a>
		<?php endif; ?>

    </div>
</div>

<hr>

<h4>Document Timeline</h4>

<?php if (!empty($logs)): ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Action</th>
                <th>User</th>
                <th>Department</th>
                <th>Date</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($logs as $log): ?>
                <tr>
                    <td><?php echo $log['action']; ?></td>
                    <td><?php echo $log['user_name']; ?></td>
                    <td><?php echo $log['department_name']; ?></td>
                    <td><?php echo $log['timestamp']; ?></td>
                    <td><?php echo $log['remarks']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No activity yet.</p>
<?php endif; ?>

<br>
<a href="<?php echo URLROOT; ?>/documents" class="btn btn-secondary">Back</a>

<?php require_once '../app/views/layout/footer.php'; ?>