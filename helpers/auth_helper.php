<?php

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login");
        exit;
    }
}

function requireRole($role)
{
    if ($_SESSION['role'] !== $role) {
        die("Access denied.");
    }
}

function allowRoles($roles = [])
{
    if (!in_array($_SESSION['role'], $roles)) {
        die("Access denied.");
    }
}

