<?php

require_once '../app/models/User.php';
require_once '../app/models/Department.php';
require_once '../app/models/Notification.php';

class Auth extends Controller
{
    private $userModel;
    private $departmentModel;
    private $notificationModel;
    private $securityService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->departmentModel = new Department();
        $this->notificationModel = new Notification();
        $this->securityService = new SecurityService();
    }

    public function index()
    {
        $this->login();
    }

    private function loginDefaults()
    {
        return [
            'id_number' => '',
            'password' => ''
        ];
    }

    private function registerDefaults()
    {
        return [
            'id_number' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
            'department_id' => '',
            'password' => '',
            'confirm_password' => ''
        ];
    }

    public function login()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $values = [
                'id_number' => trim($_POST['id_number'] ?? '')
            ];

            try {
                validateCsrfOrFail();

                $password = trim($_POST['password'] ?? '');
                $errors = [];

                if ($values['id_number'] === '') {
                    $errors['id_number'] = 'ID number is required.';
                }

                if ($password === '') {
                    $errors['password'] = 'Password is required.';
                }

                if (!empty($errors)) {
                    throw new ValidationException('Please correct the highlighted fields.', $errors);
                }

                $ipAddress = clientIpAddress();
                if ($this->securityService->isLoginBlocked($values['id_number'], $ipAddress)) {
                    securityAudit('login_blocked', null, null, ['identifier_hash' => hash('sha256', strtolower($values['id_number']))]);
                    throw new ValidationException('Too many sign-in attempts. Please try again later.', [
                        '_global' => 'Too many sign-in attempts. Please try again later.'
                    ]);
                }

                $user = $this->userModel->login($values['id_number'], $password);

                if (!$user) {
                    $this->securityService->recordLoginFailure($values['id_number'], $ipAddress);
                    securityAudit('login_failed', null, null, ['identifier_hash' => hash('sha256', strtolower($values['id_number']))]);
                    throw new ValidationException('Invalid credentials.', [
                        '_global' => 'The ID number or password you entered is incorrect.'
                    ]);
                }

                if (($user->status ?? 'inactive') !== 'active') {
                    $this->securityService->recordLoginFailure($values['id_number'], $ipAddress);
                    securityAudit('inactive_account_login', null, (int) $user->id);
                    throw new ValidationException('Your account is inactive.', [
                        '_global' => 'Your account is inactive. Please wait for administrator verification.'
                    ]);
                }

                $this->securityService->clearLoginFailures($values['id_number'], $ipAddress);
                $this->beginAuthenticatedSession($user);
                $this->userModel->markLoginSuccessful((int) $user->id);
                securityAudit('login_succeeded', (int) $user->id, (int) $user->id);

                if (($user->role ?? '') === 'admin' && empty($user->mfa_enabled)) {
                    redirect('/auth/mfaSetup', 303);
                }
                if (($user->role ?? '') === 'admin') {
                    $_SESSION['mfa_verified'] = false;
                    redirect('/auth/mfa', 303);
                }

                $_SESSION['mfa_verified'] = true;
                flash('auth_success', 'Welcome back.', 'success');
                redirect('/dashboard', 303);
            } catch (ValidationException $e) {
                storeFormState('auth_login', $values, $e->getErrors(), $e->getMessage());
                redirect('/auth/login', 303);
            } catch (Throwable $e) {
                reportException($e, ['action' => 'auth.login', 'id_number' => $values['id_number']]);
                flash('auth_error', 'We could not sign you in right now. Please try again.', 'error');
                redirect('/auth/login', 303);
            }
        }

        $state = pullFormState('auth_login', $this->loginDefaults());

        $data = [
            'values' => $state['values'],
            'errors' => $state['errors'],
            'message' => $state['message'],
            'error' => pullFlash('auth_error')['message'] ?? '',
            'success' => pullFlash('auth_success')['message'] ?? ''
        ];

        $this->view('auth/login', $data);
    }

    public function register()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $values = [
                'id_number' => trim($_POST['id_number'] ?? ''),
                'firstname' => trim($_POST['firstname'] ?? ''),
                'lastname' => trim($_POST['lastname'] ?? ''),
                'email' => strtolower(trim($_POST['email'] ?? '')),
                'department_id' => (string) ((int) ($_POST['department_id'] ?? 0))
            ];

            try {
                validateCsrfOrFail();

                $password = trim($_POST['password'] ?? '');
                $confirmPassword = trim($_POST['confirm_password'] ?? '');
                $errors = [];

                if ($values['id_number'] === '') {
                    $errors['id_number'] = 'ID number is required.';
                }

                if ($values['firstname'] === '') {
                    $errors['firstname'] = 'First name is required.';
                }

                if ($values['lastname'] === '') {
                    $errors['lastname'] = 'Last name is required.';
                }

                if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'A valid email address is required.';
                }

                if ((int) $values['department_id'] <= 0) {
                    $errors['department_id'] = 'Department is required.';
                }

                $passwordErrors = validatePasswordPolicy($password);
                if (!empty($passwordErrors)) {
                    $errors['password'] = $passwordErrors[0];
                }

                if ($confirmPassword === '') {
                    $errors['confirm_password'] = 'Please confirm your password.';
                } elseif ($password !== $confirmPassword) {
                    $errors['confirm_password'] = 'Passwords do not match.';
                }

                if ($values['id_number'] !== '' && $this->userModel->findByIdNumber($values['id_number'])) {
                    $errors['id_number'] = 'ID number is already registered.';
                }

                if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) && $this->userModel->emailExistsForOtherUser($values['email'], 0)) {
                    $errors['email'] = 'Email address is already registered.';
                }

                if (!empty($errors)) {
                    throw new ValidationException('Please correct the highlighted fields.', $errors);
                }

                $this->userModel->register([
                    'id_number' => $values['id_number'],
                    'firstname' => $values['firstname'],
                    'lastname' => $values['lastname'],
                    'email' => $values['email'],
                    'department_id' => (int) $values['department_id'],
                    'password' => $password,
                    'role' => 'staff',
                    'status' => 'inactive'
                ]);

                $registeredUser = $this->userModel->findByIdNumber($values['id_number']);
                securityAudit('account_registered', null, $registeredUser ? (int) $registeredUser->id : null);

                $this->notificationModel->notifyAdmins(
                    'New registration',
                    $values['firstname'] . ' ' . $values['lastname'] . ' submitted a registration request.',
                    '/users'
                );

                flash('auth_success', 'Registration submitted. Your account will remain inactive until an administrator verifies it.', 'success');
                redirect('/auth/login', 303);
            } catch (ValidationException $e) {
                storeFormState('auth_register', $values, $e->getErrors(), $e->getMessage());
                redirect('/auth/register', 303);
            } catch (Throwable $e) {
                reportException($e, ['action' => 'auth.register', 'id_number' => $values['id_number'], 'email' => $values['email']]);
                storeFormState('auth_register', $values, [], 'We could not complete your registration right now. Please try again.');
                redirect('/auth/register', 303);
            }
        }

        $state = pullFormState('auth_register', $this->registerDefaults());

        $data = [
            'values' => $state['values'],
            'errors' => $state['errors'],
            'message' => $state['message'],
            'departments' => $this->departmentModel->getAll()
        ];

        $this->view('auth/register', $data);
    }

    public function logout()
    {
        try {
            requirePost();
            validateCsrfOrFail();

            $userId = (int) ($_SESSION['user_id'] ?? 0);
            securityAudit('logout', $userId ?: null, $userId ?: null);
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }

            session_destroy();
            redirect('/auth/login', 303);
        } catch (ValidationException $e) {
            flash('auth_error', $e->getMessage(), 'error');
            redirect('/dashboard', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'auth.logout', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('auth_error', 'We could not sign you out right now. Please try again.', 'error');
            redirect('/dashboard', 303);
        }
    }

    private function beginAuthenticatedSession($user)
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['department_id'] = (int) $user->department_id;
        $_SESSION['role'] = (string) $user->role;
        $_SESSION['fullname'] = trim($user->firstname . ' ' . $user->lastname);
        $_SESSION['email'] = (string) ($user->email ?? '');
        $_SESSION['session_version'] = (int) ($user->session_version ?? 0);
        $_SESSION['authenticated_at'] = time();
        $_SESSION['last_activity_at'] = time();
    }

    public function mfaSetup()
    {
        $user = !empty($_SESSION['user_id']) ? $this->userModel->findById((int) $_SESSION['user_id']) : null;
        if (!$user || ($user->status ?? '') !== 'active' || ($user->role ?? '') !== 'admin') {
            clearAuthenticatedSession();
            redirect('/auth/login', 303);
        }
        if (!empty($user->mfa_enabled)) {
            redirect('/auth/mfa', 303);
        }
        if (empty($_SESSION['mfa_setup_secret'])) {
            $_SESSION['mfa_setup_secret'] = TotpService::generateSecret();
        }
        $secret = $_SESSION['mfa_setup_secret'];
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                validateCsrfOrFail();
                if (!TotpService::verify($secret, $_POST['code'] ?? '')) {
                    throw new ValidationException('The verification code is invalid.');
                }
                $this->userModel->configureMfa((int) $user->id, encryptSensitiveValue($secret));
                $_SESSION['session_version'] = $this->userModel->currentSessionVersion((int) $user->id);
                $_SESSION['mfa_verified'] = true;
                unset($_SESSION['mfa_setup_secret']);
                securityAudit('mfa_enabled', (int) $user->id, (int) $user->id);
                redirect('/dashboard', 303);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
        $provisioningUri = TotpService::provisioningUri($secret, $user->email ?: $user->id_number, SITENAME);
        require_once '../app/views/auth/mfa_setup.php';
    }

    public function mfa()
    {
        $user = !empty($_SESSION['user_id']) ? $this->userModel->findById((int) $_SESSION['user_id']) : null;
        if (!$user || ($user->status ?? '') !== 'active' || ($user->role ?? '') !== 'admin' || empty($user->mfa_enabled)) {
            clearAuthenticatedSession();
            redirect('/auth/login', 303);
        }
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                validateCsrfOrFail();
                $secret = decryptSensitiveValue($user->mfa_secret ?? '');
                if ($secret === '' || !TotpService::verify($secret, $_POST['code'] ?? '')) {
                    securityAudit('mfa_failed', (int) $user->id, (int) $user->id);
                    throw new ValidationException('The verification code is invalid.');
                }
                session_regenerate_id(true);
                $_SESSION['mfa_verified'] = true;
                $_SESSION['last_activity_at'] = time();
                securityAudit('mfa_succeeded', (int) $user->id, (int) $user->id);
                redirect('/dashboard', 303);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
        require_once '../app/views/auth/mfa.php';
    }

    public function forgotPassword()
    {
        $message = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            validateCsrfOrFail();
            $email = strtolower(trim($_POST['email'] ?? ''));
            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->userModel->findByEmail($email) : null;
            if ($user && ($user->status ?? '') === 'active') {
                $token = $this->securityService->createPasswordReset((int) $user->id);
                $resetUrl = buildUrl('/auth/resetPassword/' . rawurlencode($token));
                $delivered = @mail($email, SITENAME . ' password reset', "Use this link within 30 minutes to reset your password:\n\n" . $resetUrl, 'From: ' . PASSWORD_RESET_FROM);
                securityAudit('password_reset_requested', null, (int) $user->id, ['mail_accepted' => (bool) $delivered]);
            }
            $message = 'If the address belongs to an active account, password-reset instructions have been sent.';
        }
        require_once '../app/views/auth/forgot_password.php';
    }

    public function resetPassword($token = '')
    {
        $reset = $this->securityService->findValidPasswordReset((string) $token);
        $error = '';
        if (!$reset) {
            $error = 'This password-reset link is invalid or expired.';
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                validateCsrfOrFail();
                $password = (string) ($_POST['password'] ?? '');
                $confirmation = (string) ($_POST['confirm_password'] ?? '');
                $policyErrors = validatePasswordPolicy($password);
                if (!empty($policyErrors)) {
                    throw new ValidationException($policyErrors[0]);
                }
                if (!hash_equals($password, $confirmation)) {
                    throw new ValidationException('Password confirmation does not match.');
                }
                $this->userModel->updatePassword((int) $reset->user_id, $password);
                $this->securityService->consumePasswordReset((int) $reset->id);
                securityAudit('password_reset_completed', (int) $reset->user_id, (int) $reset->user_id);
                flash('auth_success', 'Your password has been reset. You can now sign in.', 'success');
                redirect('/auth/login', 303);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
        require_once '../app/views/auth/reset_password.php';
    }
}
