<?php require_once '../app/views/layout/header.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
</head>
<body>


<h2>Create Document</h2>

<form action="<?php echo URLROOT; ?>/documents/store" method="POST" enctype="multipart/form-data">

    <label>Title:</label><br>
    <input type="text" name="title" required><br><br>

    <label>Type:</label><br>
    <input type="text" name="type" required><br><br>

    <label>Destination Department ID:</label><br>
    <select name="destination_department_id" required>
        <option value="">Select Department</option>

        <?php foreach($departments as $dept): ?>
            <option value="<?php echo $dept['id']; ?>">
                <?php echo $dept['division_name']; ?>
            </option>
        <?php endforeach; ?>

    </select><br><br>

    <label class="form-label">Attachment (PDF, DOCX, XLSX)</label>
    <input type="file" name="attachment" class="form-control">
    
    <br><br>

    <button type="submit">Create</button>

</form>


</body>
</html>
<?php require_once '../app/views/layout/footer.php'; ?>

