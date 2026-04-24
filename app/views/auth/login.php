<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo SITENAME; ?> - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: grid;
            place-items: center;
            padding: 28px;
            color: #0f172a;
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.16), transparent 24%),
                radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.14), transparent 24%),
                linear-gradient(135deg, #f8fbfd 0%, #e8f1f6 100%);
        }
        .auth-card {
            width: 100%; max-width: 520px; padding: 36px; border-radius: 30px;
            background: rgba(255,255,255,0.92); box-shadow: 0 30px 60px rgba(15, 23, 42, 0.12); backdrop-filter: blur(18px);
        }
        .auth-brand {
            display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 26px; text-align: left;
        }
        .auth-brand img { width: 92px; height: 92px; object-fit: contain; }
        .auth-brand h1 { margin: 0; font-size: 1.7rem; line-height: 1.15; }
        .auth-help {
            background: #eff6ff; border: 1px solid #cfe0ff; color: #1e3a8a;
            border-radius: 16px; padding: 14px 16px; margin-bottom: 18px; line-height: 1.5; font-size: 0.94rem;
        }
        .alert { padding: 12px 14px; border-radius: 14px; margin-bottom: 16px; font-size: 0.95rem; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #dcfce7; color: #166534; }
        .field { margin-bottom: 15px; }
        .field label { display: block; margin-bottom: 8px; font-size: 0.92rem; font-weight: 700; color: #334155; }
        .field input {
            width: 100%; padding: 14px 16px; border-radius: 16px; border: 1px solid #dbe4ee; font: inherit; box-sizing: border-box;
        }
        .field input.is-invalid { border-color: #dc2626; background: #fff7f7; }
        .field input:focus { outline: none; border-color: rgba(15, 118, 110, 0.55); box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1); }
        .field-error { margin-top: 6px; color: #b91c1c; font-size: 0.85rem; }
        .btn-primary {
            width: 100%; border: 0; border-radius: 16px; padding: 14px 18px; font: inherit; font-weight: 800; color: white; cursor: pointer;
            background: linear-gradient(135deg, #0f766e 0%, #164e63 100%); box-shadow: 0 18px 32px rgba(15, 118, 110, 0.18);
        }
        .auth-link { margin-top: 18px; text-align: center; color: #64748b; font-size: 0.94rem; }
        .auth-link a { color: #0f766e; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-brand">
            <img src="<?php echo URLROOT; ?>/assets/logo-nfa-da.jpg" alt="NFA Logo">
            <h1><?php echo SITENAME; ?></h1>
        </div>

        <div class="auth-help">Sign in with your ID number and password. If you are new, register first and wait for account activation before trying to log in.</div>

        <?php if (!empty($data['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($data['error']); ?></div>
        <?php endif; ?>

        <?php if (!empty($data['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($data['success']); ?></div>
        <?php endif; ?>

        <?php if (!empty($data['message'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($data['message']); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <?php echo csrfInput(); ?>
            <div class="field">
                <label for="id_number">ID Number</label>
                <input id="id_number" type="text" name="id_number" value="<?php echo htmlspecialchars($data['values']['id_number'] ?? ''); ?>" class="<?php echo !empty($data['errors']['id_number']) ? 'is-invalid' : ''; ?>" required>
                <?php if (!empty($data['errors']['id_number'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($data['errors']['id_number']); ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" class="<?php echo !empty($data['errors']['password']) ? 'is-invalid' : ''; ?>" required>
                <?php if (!empty($data['errors']['password'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($data['errors']['password']); ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-primary">Login</button>
        </form>

        <div class="auth-link">
            <a href="<?php echo URLROOT; ?>/auth/register">Register</a>
        </div>
    </div>
</body>
</html>
