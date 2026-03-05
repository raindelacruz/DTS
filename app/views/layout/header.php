<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DTS - Document Tracking System</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            overflow-x: hidden;
        }

        .sidebar {
            min-height: 100vh;
        }
    </style>
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand">Document Tracking System</span>

        <div class="d-flex text-white">
            Logged in as: <?php echo $_SESSION['fullname']; ?>
            <a href="<?php echo URLROOT; ?>/auth/logout" class="btn btn-sm btn-danger ms-3">
                Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-md-2 bg-light sidebar p-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo URLROOT; ?>/dashboard">
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo URLROOT; ?>/documents">
                        Documents
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo URLROOT; ?>/documents/create">
                        Create Document
                    </a>
                </li>
            </ul>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-10 p-4">