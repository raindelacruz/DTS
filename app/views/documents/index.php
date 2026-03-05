<?php require_once '../app/views/layout/header.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
</head>
<body>

    <h2>Documents</h2>

<a href="<?php echo URLROOT; ?>/documents/create">Create New Document</a>

<form method="GET" class="row mb-4">

    <div class="col-md-4">
        <input type="text" 
               name="keyword" 
               class="form-control" 
               placeholder="Search by prefix or title..."
               value="<?php echo $data['keyword']; ?>">
    </div>

    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="Draft" <?php if($data['status']=='Draft') echo 'selected'; ?>>Draft</option>
            <option value="Released" <?php if($data['status']=='Released') echo 'selected'; ?>>Released</option>
            <option value="Received" <?php if($data['status']=='Received') echo 'selected'; ?>>Received</option>
        </select>
    </div>

    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">
            Search
        </button>
    </div>

    <div class="col-md-2">
        <a href="<?php echo URLROOT; ?>/documents" class="btn btn-secondary w-100">
            Reset
        </a>
    </div>

</form>

    <div class="col-md-3">
        <a href="<?php echo URLROOT; ?>/documents/create" class="btn btn-primary w-200">
            Create New Document
        </a>
    </div>

<table border="1" cellpadding="10">
    <tr>
        <th>Prefix</th>
        <th>Title</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

    <tbody>

    <?php if(!empty($data['documents'])): ?>

        <?php foreach($data['documents'] as $doc): ?>
            <tr>
                <td><?php echo $doc['prefix']; ?></td>
                <td><?php echo $doc['title']; ?></td>
                <td><?php echo $doc['status']; ?></td>
                <td>
                    <a href="<?php echo URLROOT; ?>/documents/show/<?php echo $doc['id']; ?>" 
                       class="btn btn-sm btn-info">
                        View
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>

    <?php else: ?>

        <tr>
            <td colspan="4" class="text-center">No documents found.</td>
        </tr>

    <?php endif; ?>

    </tbody>



    <?php if(!empty($data['documents'])): ?>
        <?php foreach($data['documents'] as $doc): ?>
            <!-- your row -->
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="5" class="text-center">No documents found.</td>
        </tr>
    <?php endif; ?>


</table>


</body>
</html>
<?php require_once '../app/views/layout/footer.php'; ?>