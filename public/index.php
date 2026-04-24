<?php

session_name('DTSSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    ),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once dirname(__DIR__) . '/app/init.php';

try {
    $app = new App();
} catch (ValidationException $e) {
    flash('error', $e->getMessage(), 'error');
    redirect('/dashboard', 303);
} catch (AuthorizationException $e) {
    flash('error', 'You are not allowed to perform that action.', 'error');
    redirect(isset($_SESSION['user_id']) ? '/dashboard' : '/auth/login', 303);
} catch (NotFoundException $e) {
    flash('error', 'The requested resource could not be found.', 'error');
    redirect(isset($_SESSION['user_id']) ? '/dashboard' : '/auth/login', 303);
} catch (Throwable $e) {
    reportException($e, [
        'url' => $_GET['url'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'user_id' => $_SESSION['user_id'] ?? null
    ]);

    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo htmlspecialchars(SITENAME, ENT_QUOTES, 'UTF-8'); ?></title>
        <style>
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; padding: 24px; }
            .card { max-width: 520px; background: #fff; border-radius: 18px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); padding: 28px; }
            h1 { margin-top: 0; font-size: 1.5rem; }
            p { line-height: 1.5; color: #475569; }
            a { color: #0f766e; font-weight: 700; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Something went wrong</h1>
            <p>We couldn't complete your request right now. The issue has been logged, and no internal details were exposed.</p>
            <p><a href="<?php echo htmlspecialchars(isset($_SESSION['user_id']) ? URLROOT . '/dashboard' : URLROOT . '/auth/login', ENT_QUOTES, 'UTF-8'); ?>">Continue</a></p>
        </div>
    </body>
    </html>
    <?php
}
