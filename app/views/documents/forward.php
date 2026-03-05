<?php require_once '../app/views/layout/header.php'; ?>

<h2>Forward Document</h2>

<form method="POST">
    <div class="mb-3">
        <label>Select Department:</label>
        <select name="department_id" class="form-control">

        <?php foreach ($departments as $dept): ?>
            
            <?php if (is_null($dept['parent_id'])): ?>

                <!-- Parent Department -->
                <option value="<?php echo $dept['id']; ?>" style="font-weight:bold;">
                    <?php echo $dept['department_name']; ?>
                </option>

                <!-- Child Divisions -->
                <?php foreach ($departments as $child): ?>
                    <?php if ($child['parent_id'] == $dept['id']): ?>
                        <option value="<?php echo $child['id']; ?>">
                            &nbsp;&nbsp;&nbsp;&nbsp;↳ <?php echo $child['division_name']; ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php endif; ?>

        <?php endforeach; ?>

        </select>
    </div>

    <button type="submit" class="btn btn-warning">Forward</button>
    <a href="<?php echo URLROOT; ?>/documents/show/<?php echo $document['id']; ?>" class="btn btn-secondary">Cancel</a>
</form>

<?php require_once '../app/views/layout/footer.php'; ?>