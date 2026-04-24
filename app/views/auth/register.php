<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo SITENAME; ?> - Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0; min-height: 100vh; font-family: 'Plus Jakarta Sans', sans-serif; display: grid; place-items: center; padding: 28px; color: #0f172a;
            background: radial-gradient(circle at top left, rgba(15, 118, 110, 0.16), transparent 24%), radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.14), transparent 24%), linear-gradient(135deg, #f8fbfd 0%, #e8f1f6 100%);
        }
        .auth-card {
            width: 100%; max-width: 700px; padding: 36px; border-radius: 30px; background: rgba(255,255,255,0.92);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.12); backdrop-filter: blur(18px);
        }
        .auth-brand { display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 26px; text-align: left; }
        .auth-brand img { width: 92px; height: 92px; object-fit: contain; }
        .auth-brand h1 { margin: 0; font-size: 1.7rem; line-height: 1.15; }
        .auth-help {
            background: #eff6ff; border: 1px solid #cfe0ff; color: #1e3a8a;
            border-radius: 16px; padding: 14px 16px; margin-bottom: 18px; line-height: 1.5; font-size: 0.94rem;
        }
        .alert { padding: 12px 14px; border-radius: 14px; margin-bottom: 16px; font-size: 0.95rem; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; margin-bottom: 8px; font-size: 0.92rem; font-weight: 700; color: #334155; }
        .field input, .field select {
            width: 100%; padding: 14px 16px; border-radius: 16px; border: 1px solid #dbe4ee; font: inherit; box-sizing: border-box; background: white;
        }
        .field input.is-invalid, .field select.is-invalid { border-color: #dc2626; background: #fff7f7; }
        .field input:focus, .field select:focus { outline: none; border-color: rgba(15, 118, 110, 0.55); box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1); }
        .field-error { margin-top: 6px; color: #b91c1c; font-size: 0.85rem; }
        .btn-primary {
            width: 100%; border: 0; border-radius: 16px; padding: 14px 18px; font: inherit; font-weight: 800; color: white; cursor: pointer;
            background: linear-gradient(135deg, #0f766e 0%, #164e63 100%); box-shadow: 0 18px 32px rgba(15, 118, 110, 0.18); margin-top: 6px;
        }
        .auth-link { margin-top: 18px; text-align: center; color: #64748b; font-size: 0.94rem; }
        .auth-link a { color: #0f766e; font-weight: 700; text-decoration: none; }
        @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } .auth-brand { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-brand">
            <img src="<?php echo URLROOT; ?>/assets/logo-nfa-da.jpg" alt="NFA Logo">
            <h1><?php echo SITENAME; ?></h1>
        </div>

        <div class="auth-help">Provide your ID number, name, department, and active email address. Your registration stays inactive until an administrator verifies the account.</div>

        <?php if (!empty($data['message'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($data['message']); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <?php echo csrfInput(); ?>
            <div class="form-grid">
                <div class="field">
                    <label for="id_number">ID Number</label>
                    <input id="id_number" type="text" name="id_number" value="<?php echo htmlspecialchars($data['values']['id_number'] ?? ''); ?>" class="<?php echo !empty($data['errors']['id_number']) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($data['errors']['id_number'])): ?><div class="field-error"><?php echo htmlspecialchars($data['errors']['id_number']); ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" class="<?php echo !empty($data['errors']['department_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">Select Department</option>
                        <?php foreach (($data['departments'] ?? []) as $department): ?>
                            <option value="<?php echo $department['id']; ?>" <?php echo ((string) ($data['values']['department_id'] ?? '') === (string) $department['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['division_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($data['errors']['department_id'])): ?><div class="field-error"><?php echo htmlspecialchars($data['errors']['department_id']); ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="firstname">First Name</label>
                    <input id="firstname" type="text" name="firstname" value="<?php echo htmlspecialchars($data['values']['firstname'] ?? ''); ?>" class="<?php echo !empty($data['errors']['firstname']) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($data['errors']['firstname'])): ?><div class="field-error"><?php echo htmlspecialchars($data['errors']['firstname']); ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="lastname">Last Name</label>
                    <input id="lastname" type="text" name="lastname" value="<?php echo htmlspecialchars($data['values']['lastname'] ?? ''); ?>" class="<?php echo !empty($data['errors']['lastname']) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($data['errors']['lastname'])): ?><div class="field-error"><?php echo htmlspecialchars($data['errors']['lastname']); ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="email">Email Address</label>
                    <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($data['values']['email'] ?? ''); ?>" class="<?php echo !empty($data['errors']['email']) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($data['errors']['email'])): ?><div class="field-error"><?php echo htmlspecialchars($data['errors']['email']); ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" class="<?php echo !empty($data['errors']['password']) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($data['errors']['password'])): ?><div class="field-error"><?php echo htmlspecialchars($data['errors']['password']); ?></div><?php endif; ?>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="confirm_password">Confirm Password</label>
                    <input id="confirm_password" type="password" name="confirm_password" class="<?php echo !empty($data['errors']['confirm_password']) ? 'is-invalid' : ''; ?>" required>
                    <?php if (!empty($data['errors']['confirm_password'])): ?><div class="field-error"><?php echo htmlspecialchars($data['errors']['confirm_password']); ?></div><?php endif; ?>
                </div>
            </div>
            <button type="submit" class="btn-primary">Submit Registration</button>
        </form>

        <div class="auth-link">
            <a href="<?php echo URLROOT; ?>/auth/login">Back to Login</a>
        </div>
    </div>
</body>
</html>
