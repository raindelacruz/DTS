<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo SITENAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <?php
    require_once '../app/models/Notification.php';
    $headerNotificationModel = new Notification();
    $headerNotifications = $headerNotificationModel->getRecentByUser((int) $_SESSION['user_id']);
    $headerUnreadCount = $headerNotificationModel->countUnreadByUser((int) $_SESSION['user_id']);
    $pageFlashMessages = pullAllFlash();

    $currentUrl = $_GET['url'] ?? 'dashboard';
    $currentSection = explode('/', trim($currentUrl, '/'))[0] ?? 'dashboard';
    ?>

    <style>
        :root {
            --app-bg: #eef3f7;
            --surface-strong: #ffffff;
            --border-soft: rgba(148, 163, 184, 0.22);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --brand-deep: #115e59;
            --shadow-soft: 0 18px 45px rgba(15, 23, 42, 0.08);
            --shadow-card: 0 16px 30px rgba(15, 23, 42, 0.06);
            --radius-lg: 18px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 28%),
                radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.10), transparent 26%),
                linear-gradient(180deg, #f8fbfd 0%, var(--app-bg) 100%);
        }

        .app-shell { display: flex; min-height: 100vh; }
        .app-sidebar { width: 280px; padding: 1.5rem; position: sticky; top: 0; height: 100vh; }
        .app-sidebar-panel {
            height: 100%; border-radius: 28px; padding: 1.4rem;
            background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(245,248,252,0.92) 100%);
            border: 1px solid rgba(255,255,255,0.75); box-shadow: var(--shadow-soft);
            display: flex; flex-direction: column; backdrop-filter: blur(14px);
        }
        .sidebar-nav { gap: 0.4rem; }
        .sidebar-link {
            display: flex; align-items: center; gap: 0.85rem; padding: 0.95rem 1rem;
            border-radius: 16px; color: #1e293b; text-decoration: none; font-weight: 600; transition: 0.2s ease;
        }
        .sidebar-link:hover { background: rgba(15, 118, 110, 0.08); color: var(--brand-deep); transform: translateX(2px); }
        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.14), rgba(20, 78, 99, 0.12));
            color: var(--brand-deep); box-shadow: inset 0 0 0 1px rgba(15, 118, 110, 0.12);
        }
        .sidebar-icon {
            width: 2.2rem; height: 2.2rem; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.9); color: var(--brand-deep); box-shadow: 0 10px 18px rgba(15, 23, 42, 0.06); font-size: 0.95rem;
        }
        .app-main { flex: 1; padding: 1.5rem 1.5rem 1.5rem 0; }
        .topbar {
            position: relative;
            z-index: 1200;
            overflow: visible;
            display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem;
            margin-bottom: 1.5rem; border-radius: 22px; background: rgba(255,255,255,0.88);
            border: 1px solid rgba(255,255,255,0.75); box-shadow: var(--shadow-card); backdrop-filter: blur(14px);
        }
        .topbar-brand { display: flex; align-items: center; gap: 0.85rem; }
        .topbar-brand img { width: 48px; height: 48px; object-fit: contain; border-radius: 12px; }
        .topbar h2 { margin: 0; font-size: 1.15rem; font-weight: 800; }
        .topbar-user { position: relative; z-index: 1201; display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap; justify-content: flex-end; }
        .notification-toggle {
            position: relative; width: 44px; height: 44px; border-radius: 14px; border: 1px solid var(--border-soft);
            background: #fff; font-size: 1.15rem; display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; color: #0f172a;
        }
        .notification-toggle svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .notification-badge {
            position: absolute; top: -6px; right: -4px; min-width: 20px; height: 20px; border-radius: 999px;
            background: #dc2626; color: white; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; padding: 0 6px;
        }
        .notification-menu {
            position: absolute;
            z-index: 1300;
            width: 360px; padding: 0; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }
        .notification-head {
            display: flex; justify-content: space-between; align-items: center; padding: 0.9rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        }
        .notification-item {
            display: block; padding: 0.9rem 1rem; text-decoration: none; color: inherit; border-bottom: 1px solid #edf2f7;
        }
        .notification-item.unread { background: #eff6ff; }
        .notification-item:last-child { border-bottom: 0; }
        .notification-item:hover { background: #f8fafc; }
        .notification-title { font-weight: 700; font-size: 0.92rem; }
        .notification-message { color: #475569; font-size: 0.85rem; margin-top: 0.2rem; }
        .notification-time { color: #94a3b8; font-size: 0.76rem; margin-top: 0.35rem; }
        .user-chip {
            display: inline-flex; align-items: center; gap: 0.75rem; padding: 0.55rem 0.9rem; border-radius: 999px;
            background: var(--surface-strong); border: 1px solid var(--border-soft); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
        }
        .user-avatar {
            width: 2.2rem; height: 2.2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0f766e 0%, #164e63 100%); color: white; font-weight: 800;
        }
        .content-frame { min-height: calc(100vh - 7rem); }
        .app-card {
            background: rgba(255,255,255,0.9); border: 1px solid rgba(255,255,255,0.85);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-card); backdrop-filter: blur(12px);
        }
        .section-title { font-size: 1.85rem; font-weight: 800; margin: 0; }
        .table-modern thead th {
            border: 0; background: #f8fafc; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em;
            font-size: 0.78rem; white-space: nowrap;
        }
        .table-modern tbody td { border-color: #edf2f7; vertical-align: middle; }
        .table-modern tbody tr:hover { background: #f8fbfd; }
        .badge-soft { border-radius: 999px; padding: 0.45rem 0.8rem; font-weight: 700; font-size: 0.78rem; }
        .page-hero {
            display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.5rem; margin-bottom: 1.5rem;
            border-radius: 22px; background: linear-gradient(135deg, rgba(255,255,255,0.92) 0%, rgba(239,246,255,0.94) 100%);
            border: 1px solid rgba(255,255,255,0.85); box-shadow: var(--shadow-card);
        }
        .page-hero.compact { padding: 1.25rem 1.35rem; }
        .instruction-card {
            margin-bottom: 1.5rem;
            padding: 1rem 1.15rem;
            border-radius: 18px;
            background: linear-gradient(135deg, #ecfeff 0%, #f8fafc 100%);
            border: 1px solid #cfe9ec;
            box-shadow: var(--shadow-card);
        }
        .instruction-card h3 {
            margin: 0 0 0.45rem;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .instruction-card p {
            margin: 0;
            color: #334155;
            line-height: 1.55;
        }
        .instruction-card strong { color: #0f172a; }
        .form-control, .form-select, textarea.form-control {
            border-radius: 14px; border-color: #dbe4ee; padding: 0.75rem 0.9rem; box-shadow: none;
        }
        .form-control:focus, .form-select:focus, textarea.form-control:focus {
            border-color: rgba(15, 118, 110, 0.45); box-shadow: 0 0 0 0.2rem rgba(15, 118, 110, 0.12);
        }
        .route-checkbox-group {
            display: grid;
            gap: 0.7rem;
            max-height: 24rem;
            overflow-y: auto;
            padding: 0.95rem;
            border: 1px solid #dbe4ee;
            border-radius: 16px;
            background: rgba(248, 250, 252, 0.75);
        }
        .route-search-wrap {
            margin-bottom: 0.8rem;
        }
        .route-search-input {
            background: rgba(255, 255, 255, 0.96);
        }
        .route-checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.8rem 0.9rem;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid rgba(219, 228, 238, 0.9);
            cursor: pointer;
            transition: 0.18s ease;
            line-height: 1.4;
        }
        .route-checkbox-item:hover {
            border-color: rgba(15, 118, 110, 0.35);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
            transform: translateY(-1px);
        }
        .route-checkbox-item .form-check-input {
            margin-top: 0.18rem;
            flex-shrink: 0;
        }
        .route-checkbox-item:has(.form-check-input:checked) {
            border-color: rgba(15, 118, 110, 0.45);
            background: linear-gradient(135deg, rgba(236, 253, 245, 0.95), rgba(240, 249, 255, 0.95));
        }
        .route-empty-state {
            padding: 1rem;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            color: #64748b;
            background: rgba(255, 255, 255, 0.85);
            text-align: center;
            font-weight: 600;
        }
        .route-group-heading {
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0f766e;
            padding: 0.2rem 0.15rem 0;
        }
        .btn { border-radius: 14px; font-weight: 700; padding: 0.7rem 1rem; }
        .btn-primary { background: linear-gradient(135deg, #0f766e 0%, #164e63 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #115e59 0%, #0f3f53 100%); }
        .btn-warning { border: none; color: #111827; }

        @media (max-width: 991.98px) {
            .app-shell { display: block; }
            .app-sidebar { width: 100%; height: auto; position: static; padding: 1rem 1rem 0; }
            .app-main { padding: 1rem; }
            .app-sidebar-panel { height: auto; }
            .page-hero, .topbar { flex-direction: column; align-items: stretch; }
            .topbar-brand { justify-content: center; }
            .notification-menu {
                position: absolute;
                z-index: 1300;
                width: 100%;
            }
            .route-checkbox-group { max-height: 18rem; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-sidebar-panel">
            <nav class="nav flex-column sidebar-nav">
                <a class="sidebar-link <?php echo $currentSection === 'dashboard' ? 'active' : ''; ?>" href="<?php echo URLROOT; ?>/dashboard">
                    <span class="sidebar-icon">D</span>
                    <span>Dashboard</span>
                </a>
                <a class="sidebar-link <?php echo $currentSection === 'documents' && $currentUrl === 'documents' ? 'active' : ''; ?>" href="<?php echo URLROOT; ?>/documents">
                    <span class="sidebar-icon">F</span>
                    <span>Documents</span>
                </a>
                <a class="sidebar-link <?php echo $currentSection === 'documents' && strpos($currentUrl, 'documents/outgoing') === 0 ? 'active' : ''; ?>" href="<?php echo URLROOT; ?>/documents/outgoing">
                    <span class="sidebar-icon">O</span>
                    <span>Outgoing</span>
                </a>
                <a class="sidebar-link <?php echo $currentSection === 'documents' && strpos($currentUrl, 'documents/incoming') === 0 ? 'active' : ''; ?>" href="<?php echo URLROOT; ?>/documents/incoming">
                    <span class="sidebar-icon">I</span>
                    <span>Incoming</span>
                </a>
                <a class="sidebar-link <?php echo $currentSection === 'documents' && strpos($currentUrl, 'documents/create') === 0 ? 'active' : ''; ?>" href="<?php echo URLROOT; ?>/documents/create">
                    <span class="sidebar-icon">+</span>
                    <span>Create Document</span>
                </a>
                <a class="sidebar-link <?php echo $currentSection === 'users' && strpos($currentUrl, 'users/profile') === 0 ? 'active' : ''; ?>" href="<?php echo URLROOT; ?>/users/profile">
                    <span class="sidebar-icon">P</span>
                    <span>My Profile</span>
                </a>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a class="sidebar-link <?php echo $currentSection === 'users' && $currentUrl === 'users' ? 'active' : ''; ?>" href="<?php echo URLROOT; ?>/users">
                        <span class="sidebar-icon">U</span>
                        <span>User Management</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="topbar">
            <div class="topbar-brand">
                <img src="<?php echo URLROOT; ?>/assets/logo-nfa-da.jpg" alt="NFA Logo">
                <h2><?php echo SITENAME; ?></h2>
            </div>
            <div class="topbar-user">
                <div class="dropdown">
                    <a href="#" class="notification-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M15 17h5l-1.4-1.4a2 2 0 0 1-.6-1.4V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path>
                            <path d="M10 17a2 2 0 0 0 4 0"></path>
                        </svg>
                        <?php if ($headerUnreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $headerUnreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notification-menu">
                        <div class="notification-head">
                            <strong>Notifications</strong>
                            <form action="<?php echo URLROOT; ?>/notifications/readAll" method="POST">
                                <?php echo csrfInput(); ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark all read</button>
                            </form>
                        </div>
                        <?php if (!empty($headerNotifications)): ?>
                            <?php foreach ($headerNotifications as $notification): ?>
                                <?php $target = !empty($notification['link']) ? (URLROOT . $notification['link']) : (URLROOT . '/dashboard'); ?>
                                <a href="<?php echo URLROOT; ?>/notifications/read/<?php echo $notification['id']; ?>?redirect=<?php echo urlencode($target); ?>" class="notification-item <?php echo ((int) $notification['is_read'] === 0) ? 'unread' : ''; ?>">
                                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-time"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($notification['created_at']))); ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-muted small">No notifications.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="user-chip">
                    <span class="user-avatar"><?php echo strtoupper(substr(trim($_SESSION['fullname']), 0, 1)); ?></span>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'user')); ?></div>
                    </div>
                </div>
                <a href="<?php echo URLROOT; ?>/users/profile" class="btn btn-outline-secondary">Profile</a>
                <form action="<?php echo URLROOT; ?>/auth/logout" method="POST" class="d-inline">
                    <?php echo csrfInput(); ?>
                    <button type="submit" class="btn btn-outline-danger">Logout</button>
                </form>
            </div>
        </div>

        <div class="content-frame">
            <?php foreach ($pageFlashMessages as $flashMessage): ?>
                <?php if (!empty($flashMessage['message'])): ?>
                    <div class="alert alert-<?php echo ($flashMessage['type'] ?? 'success') === 'error' ? 'danger' : 'success'; ?> app-card border-0 mb-4">
                        <?php echo htmlspecialchars($flashMessage['message']); ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
