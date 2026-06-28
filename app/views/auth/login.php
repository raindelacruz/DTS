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
        .password-field { position: relative; }
        .password-field input { padding-right: 52px; }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover,
        .password-toggle:focus {
            color: #0f766e;
            background: rgba(15, 118, 110, 0.08);
            outline: none;
        }
        .password-toggle svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .password-toggle .icon-eye-off { display: none; }
        .password-toggle.is-visible .icon-eye { display: none; }
        .password-toggle.is-visible .icon-eye-off { display: block; }
        .field input.is-invalid { border-color: #dc2626; background: #fff7f7; }
        .field input:focus { outline: none; border-color: rgba(15, 118, 110, 0.55); box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1); }
        .field-error { margin-top: 6px; color: #b91c1c; font-size: 0.85rem; }
        .btn-primary {
            width: 100%; border: 0; border-radius: 16px; padding: 14px 18px; font: inherit; font-weight: 800; color: white; cursor: pointer;
            background: linear-gradient(135deg, #0f766e 0%, #164e63 100%); box-shadow: 0 18px 32px rgba(15, 118, 110, 0.18);
        }
        .auth-link { margin-top: 18px; text-align: center; color: #64748b; font-size: 0.94rem; }
        .auth-link a { color: #0f766e; font-weight: 700; text-decoration: none; }

        @media (max-width: 575.98px) {
            body {
                display: block;
                min-height: 100dvh;
                padding: 18px 12px;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 30%),
                    radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.10), transparent 28%),
                    linear-gradient(180deg, #f8fbfd 0%, #e8f1f6 100%);
            }
            .auth-card {
                max-width: none;
                min-height: calc(100dvh - 36px);
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 22px;
                border-radius: 22px;
                box-shadow: 0 18px 42px rgba(15, 23, 42, 0.10);
            }
            .auth-brand {
                justify-content: flex-start;
                gap: 10px;
                margin-bottom: 18px;
            }
            .auth-brand img {
                width: 72px;
                height: 72px;
            }
            .auth-brand h1 {
                font-size: 1.42rem;
                line-height: 1.12;
            }
            .auth-help {
                padding: 11px 13px;
                margin-bottom: 16px;
                border-radius: 14px;
                font-size: 0.86rem;
                line-height: 1.45;
            }
            .alert {
                padding: 10px 12px;
                margin-bottom: 12px;
                border-radius: 12px;
                font-size: 0.86rem;
            }
            .field {
                margin-bottom: 12px;
            }
            .field label {
                margin-bottom: 6px;
                font-size: 0.86rem;
            }
            .field input {
                min-height: 48px;
                padding: 12px 14px;
                border-radius: 14px;
                font-size: 1rem;
                background: rgba(255, 255, 255, 0.98);
            }
            .password-field input {
                padding-right: 48px;
            }
            .password-toggle {
                right: 8px;
                width: 38px;
                height: 38px;
            }
            .btn-primary {
                min-height: 48px;
                padding: 12px 16px;
                border-radius: 14px;
            }
            .auth-link {
                margin-top: 16px;
            }
        }

        @media (max-width: 360px) {
            body { padding: 10px; }
            .auth-card {
                min-height: calc(100dvh - 20px);
                padding: 18px;
            }
            .auth-brand img {
                width: 62px;
                height: 62px;
            }
            .auth-brand h1 {
                font-size: 1.24rem;
            }
            .auth-help {
                font-size: 0.82rem;
            }
        }
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
            <?php echo csrfInput('login'); ?>
            <div class="field">
                <label for="id_number">ID Number</label>
                <input id="id_number" type="text" name="id_number" value="<?php echo htmlspecialchars($data['values']['id_number'] ?? ''); ?>" class="<?php echo !empty($data['errors']['id_number']) ? 'is-invalid' : ''; ?>" required>
                <?php if (!empty($data['errors']['id_number'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($data['errors']['id_number']); ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" type="password" name="password" class="<?php echo !empty($data['errors']['password']) ? 'is-invalid' : ''; ?>" required>
                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-password-toggle="password">
                        <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M3 3l18 18"></path>
                            <path d="M10.6 10.6A2 2 0 0 0 13.4 13.4"></path>
                            <path d="M9.9 4.2A10.5 10.5 0 0 1 12 4c6.5 0 10 8 10 8a18.2 18.2 0 0 1-3.1 4.4"></path>
                            <path d="M6.6 6.6C3.8 8.5 2 12 2 12s3.5 8 10 8a9.7 9.7 0 0 0 4.8-1.3"></path>
                        </svg>
                    </button>
                </div>
                <?php if (!empty($data['errors']['password'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($data['errors']['password']); ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-primary">Login</button>
        </form>

        <div class="auth-link">
            <a href="<?php echo URLROOT; ?>/auth/register">Register</a> · <a href="<?php echo URLROOT; ?>/auth/forgotPassword">Forgot password?</a>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-password-toggle'));
                if (!input) {
                    return;
                }

                var shouldShow = input.type === 'password';
                input.type = shouldShow ? 'text' : 'password';
                button.classList.toggle('is-visible', shouldShow);
                button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
                input.focus();
            });
        });
    </script>
</body>
</html>
