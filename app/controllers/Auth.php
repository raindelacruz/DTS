<?php

require_once APPROOT . '/models/User.php';
require_once APPROOT . '/models/Department.php';
require_once APPROOT . '/models/Notification.php';

class Auth extends Controller
{
    private $userModel;
    private $departmentModel;
    private $notificationModel;
    private $securityService;

    public function __construct()
    {
    }

    private function users()
    {
        if (!$this->userModel) {
            $this->userModel = new User();
        }
        return $this->userModel;
    }

    private function departments()
    {
        if (!$this->departmentModel) {
            $this->departmentModel = new Department();
        }
        return $this->departmentModel;
    }

    private function notifications()
    {
        if (!$this->notificationModel) {
            $this->notificationModel = new Notification();
        }
        return $this->notificationModel;
    }

    private function security()
    {
        if (!$this->securityService) {
            $this->securityService = new SecurityService();
        }
        return $this->securityService;
    }

    private function isLoginBlocked($identifier, $ipAddress)
    {
        try {
            return $this->security()->isLoginBlocked($identifier, $ipAddress);
        } catch (Throwable $e) {
            appLog('error', 'Login throttling unavailable', ['message' => $e->getMessage()]);
            return false;
        }
    }

    private function recordLoginFailure($identifier, $ipAddress)
    {
        try {
            $this->security()->recordLoginFailure($identifier, $ipAddress);
        } catch (Throwable $e) {
            appLog('error', 'Login failure recording unavailable', ['message' => $e->getMessage()]);
        }
    }

    private function clearLoginFailures($identifier, $ipAddress)
    {
        try {
            $this->security()->clearLoginFailures($identifier, $ipAddress);
        } catch (Throwable $e) {
            appLog('error', 'Login failure clearing unavailable', ['message' => $e->getMessage()]);
        }
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

    private function renderLogin($values = [], $errors = [], $message = '', $error = '', $success = '')
    {
        $data = [
            'values' => array_merge($this->loginDefaults(), is_array($values) ? $values : []),
            'errors' => is_array($errors) ? $errors : [],
            'message' => (string) $message,
            'error' => (string) $error,
            'success' => (string) $success
        ];

        $this->view('auth/login', $data);
    }

    public function login()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            observeSecurityRequest('login', $_POST['id_number'] ?? '');
            $values = [
                'id_number' => trim($_POST['id_number'] ?? '')
            ];
            $phase = 'start';

            try {
                $phase = 'csrf';
                validateCsrfOrFail('login');

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

                $phase = 'throttle';
                $ipAddress = clientIpAddress();
                if (!AuthenticationGuard::enabled() && $this->isLoginBlocked($values['id_number'], $ipAddress)) {
                    securityAudit('login_blocked', null, null, ['identifier_hash' => hash('sha256', strtolower($values['id_number']))]);
                    throw new ValidationException('Too many sign-in attempts. Please try again later.', [
                        '_global' => 'Too many sign-in attempts. Please try again later.'
                    ]);
                }

                $phase = 'credential_lookup';
                if (AuthenticationGuard::enabled()) {
                    $result = AuthenticationGuard::configured()->check('login', $values['id_number'], function () use ($values, $password) {
                        $candidate = $this->users()->login($values['id_number'], $password);
                        return $candidate && ($candidate->status ?? '') === 'active' ? $candidate : false;
                    });
                    if ($result['status'] === 'limited') {
                        header('Retry-After: ' . $result['retry']);
                        throw new ValidationException('Too many sign-in attempts. Try again in ' . $result['retry'] . ' seconds. Contact your system administrator if you need help.');
                    }
                    $user = $result['value'] ?? false;
                } else {
                    $user = $this->users()->login($values['id_number'], $password);
                }

                if (!$user) {
                    $this->recordLoginFailure($values['id_number'], $ipAddress);
                    securityAudit('login_failed', null, null, ['identifier_hash' => hash('sha256', strtolower($values['id_number']))]);
                    throw new ValidationException('Invalid credentials.', [
                        '_global' => 'The ID number or password you entered is incorrect.'
                    ]);
                }

                if (($user->status ?? 'inactive') !== 'active') {
                    $this->recordLoginFailure($values['id_number'], $ipAddress);
                    securityAudit('inactive_account_login', null, (int) $user->id);
                    throw new ValidationException('Your account is inactive.', [
                        '_global' => 'Your account is inactive. Please wait for administrator verification.'
                    ]);
                }

                $phase = 'session_start';
                $this->clearLoginFailures($values['id_number'], $ipAddress);
                $this->beginAuthenticatedSession($user);

                $phase = 'post_login_updates';
                $this->users()->markLoginSuccessful((int) $user->id);
                // Preserve the legacy event for existing reports, with an explicit stage.
                securityAudit('login_succeeded', (int) $user->id, (int) $user->id, ['stage' => 'password_only']);
                securityAudit('password_accepted', (int) $user->id, (int) $user->id);

                $phase = 'post_login_redirect';
                if (($user->role ?? '') === 'admin' && empty($user->mfa_enabled)) {
                    redirect('/auth/mfaSetup', 303);
                }
                if (!empty($user->mfa_enabled)) {
                    if ($this->hasTrustedMfaDevice($user)) {
                        $_SESSION['mfa_verified'] = true;
                        securityAudit('mfa_trusted_device_used', (int) $user->id, (int) $user->id);
                        securityAudit('authentication_completed', (int) $user->id, (int) $user->id, ['method' => 'trusted_device']);
                        flash('auth_success', 'Welcome back.', 'success');
                        redirect('/dashboard', 303);
                    }
                    $_SESSION['mfa_verified'] = false;
                    redirect('/auth/mfa', 303);
                }

                $_SESSION['mfa_verified'] = true;
                securityAudit('authentication_completed', (int) $user->id, (int) $user->id, ['method' => 'password_only']);
                flash('auth_success', 'Welcome back.', 'success');
                redirect('/dashboard', 303);
            } catch (ValidationException $e) {
                http_response_code(422);
                securityAudit('security_request_rejected', null, null, ['action' => 'login', 'reason' => 'validation']);
                $errors = $e->getErrors();
                $message = $errors['_global'] ?? $e->getMessage();
                $this->renderLogin($values, $errors, $message);
                return;
            } catch (Throwable $e) {
                $incidentId = 'LOGIN-' . strtoupper(bin2hex(random_bytes(4)));
                reportException($e, [
                    'action' => 'auth.login',
                    'phase' => $phase,
                    'incident_id' => $incidentId,
                    'identifier_hash' => hash('sha256', strtolower($values['id_number']))
                ]);
                $detail = APP_ENV !== 'production'
                    ? ' (' . $phase . ': ' . $e->getMessage() . ')'
                    : '';
                http_response_code(500);
                $this->renderLogin($values, [], '', 'We could not sign you in right now. Reference: ' . $incidentId . $detail);
                return;
            }
        }

        $state = pullFormState('auth_login', $this->loginDefaults());
        $this->renderLogin(
            $state['values'],
            $state['errors'],
            $state['message'],
            pullFlash('auth_error')['message'] ?? '',
            pullFlash('auth_success')['message'] ?? ''
        );
    }

    public function register()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            observeSecurityRequest('registration', $_POST['email'] ?? '');
            $values = [
                'id_number' => is_string($_POST['id_number'] ?? '') ? trim($_POST['id_number'] ?? '') : '',
                'firstname' => is_string($_POST['firstname'] ?? '') ? trim($_POST['firstname'] ?? '') : '',
                'lastname' => is_string($_POST['lastname'] ?? '') ? trim($_POST['lastname'] ?? '') : '',
                'email' => is_string($_POST['email'] ?? '') ? strtolower(trim($_POST['email'] ?? '')) : '',
                'department_id' => (string) (is_scalar($_POST['department_id'] ?? 0) ? (int) ($_POST['department_id'] ?? 0) : 0)
            ];

            try {
                validateCsrfOrFail();

                $password = is_string($_POST['password'] ?? '') ? trim($_POST['password'] ?? '') : '';
                $confirmPassword = is_string($_POST['confirm_password'] ?? '') ? trim($_POST['confirm_password'] ?? '') : '';
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

                if (RegistrationProtection::enabled()) {
                    if (!RegistrationProtection::validId($values['id_number'])) { $errors['id_number'] = 'Check the employee ID supplied with your invitation.'; }
                    if (strlen($values['email']) > 150) { $errors['email'] = 'The email address is too long.'; }
                    if (!empty($errors)) { throw new ValidationException('Please correct the highlighted fields.', $errors); }
                    $code = is_string($_POST['invitation_code'] ?? '') ? trim($_POST['invitation_code'] ?? '') : '';
                    // Never store invitation codes or passwords in form state or exception logs.
                    $result = RegistrationProtection::configured()->register($values, $password, substr($code, 0, 256));
                    if ($result['status'] === 'limited') {
                        throw new ValidationException('Too many registration submissions. Please try again later or contact your system administrator.');
                    }
                    if ($result['status'] === 'invalid') {
                        throw new ValidationException('Registration could not be accepted. Check your invitation and employee details, or contact your system administrator.');
                    }
                    flash('auth_success', 'Your registration has been received. The account remains inactive until administrator review. If you already submitted it, contact your system administrator for its status.', 'success');
                    redirect('/auth/login', 303);
                }

                if ($values['id_number'] !== '' && $this->users()->findByIdNumber($values['id_number'])) {
                    $errors['id_number'] = 'ID number is already registered.';
                }

                if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) && $this->users()->emailExistsForOtherUser($values['email'], 0)) {
                    $errors['email'] = 'Email address is already registered.';
                }

                if (!empty($errors)) {
                    throw new ValidationException('Please correct the highlighted fields.', $errors);
                }

                $this->users()->register([
                    'id_number' => $values['id_number'],
                    'firstname' => $values['firstname'],
                    'lastname' => $values['lastname'],
                    'email' => $values['email'],
                    'department_id' => (int) $values['department_id'],
                    'password' => $password,
                    'role' => 'staff',
                    'status' => 'inactive'
                ]);

                $registeredUser = $this->users()->findByIdNumber($values['id_number']);
                securityAudit('account_registered', null, $registeredUser ? (int) $registeredUser->id : null);

                $this->notifications()->notifyAdmins(
                    'New registration',
                    $values['firstname'] . ' ' . $values['lastname'] . ' submitted a registration request.',
                    '/users'
                );

                flash('auth_success', 'Registration submitted. Your account will remain inactive until an administrator verifies it.', 'success');
                redirect('/auth/login', 303);
            } catch (ValidationException $e) {
                storeFormState('auth_register', $values, $e->getErrors(), $e->getMessage());
                securityAudit('security_request_rejected', null, null, ['action' => 'registration', 'reason' => 'validation']);
                redirect('/auth/register', 303);
            } catch (Throwable $e) {
                appLog('error', 'Registration unavailable', ['exception_class' => get_class($e)]);
                storeFormState('auth_register', $values, [], 'We could not complete your registration right now. Please try again.');
                redirect('/auth/register', 303);
            }
        }

        $state = pullFormState('auth_register', $this->registerDefaults());

        $data = [
            'values' => $state['values'],
            'errors' => $state['errors'],
            'message' => $state['message'],
            'departments' => $this->departments()->getAll(),
            'invitation_required' => RegistrationProtection::enabled()
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
                expireCookieOnKnownPaths(session_name());
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
        unset($_SESSION['mfa_verified'], $_SESSION['mfa_challenge'], $_SESSION['mfa_setup_secret'], $_SESSION['admin_challenges'], $_SESSION['admin_emergency_logged']);
        expireCookieOnLegacyPaths(session_name());
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['department_id'] = (int) $user->department_id;
        $_SESSION['role'] = (string) $user->role;
        $_SESSION['fullname'] = trim($user->firstname . ' ' . $user->lastname);
        $_SESSION['email'] = (string) ($user->email ?? '');
        $_SESSION['session_version'] = (int) ($user->session_version ?? 0);
        $_SESSION['authenticated_at'] = time();
        $_SESSION['last_activity_at'] = time();
        if (AuthenticationGuard::enabled()) { AuthenticationGuard::startChallenge($_SESSION, time()); }
    }

    private function requireMfaChallenge($user)
    {
        if (AuthenticationGuard::enabled() && !empty($_SESSION['mfa_verified'])) { redirect('/dashboard', 303); }
        if (AuthenticationGuard::enabled() && !AuthenticationGuard::challengeValid($_SESSION, (int) $user->id,
            (int) ($user->session_version ?? 0), time())) {
            clearAuthenticatedSession();
            flash('auth_error', 'Verification expired or too many codes were submitted. Please sign in again.', 'error');
            redirect('/auth/login', 303);
        }
    }

    private function verifyMfaCode($user, $secret)
    {
        if (!AuthenticationGuard::enabled()) { return TotpService::verify($secret, $_POST['code'] ?? ''); }
        $result = AuthenticationGuard::configured()->check('mfa', 'user:' . (int) $user->id,
            static function ($now) use ($secret) {
                return $secret === '' ? false : TotpService::matchingStep($secret, $_POST['code'] ?? '', $now);
            }, $secret);
        if ($result['status'] !== 'limited') { $_SESSION['mfa_challenge']['attempts']++; }
        if ($result['status'] !== 'accepted') {
            securityAudit('mfa_failed', (int) $user->id, (int) $user->id, ['reason' => $result['status']]);
            if ($_SESSION['mfa_challenge']['attempts'] >= 5) {
                clearAuthenticatedSession();
                flash('auth_error', 'Too many verification attempts. Sign in again after five minutes or contact your system administrator.', 'error');
                redirect('/auth/login', 303);
            }
            header('Retry-After: ' . $result['retry']);
            throw new ValidationException('Verification unsuccessful. Try again in ' . $result['retry'] . ' seconds. Contact your system administrator if you need help.');
        }
        return true;
    }

    private function trustedMfaCookieOptions($expires)
    {
        return [
            'expires' => (int) $expires,
            'path' => APP_COOKIE_PATH,
            'secure' => isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
    }

    private function rememberMfaDevice($user)
    {
        try {
            $sessionVersion = $this->users()->currentSessionVersion((int) $user->id);
            $cookieValue = $this->security()->createTrustedMfaDevice((int) $user->id, $sessionVersion);
            expireCookieOnKnownPaths(MFA_TRUSTED_DEVICE_COOKIE);
            setCookieOnKnownPaths(MFA_TRUSTED_DEVICE_COOKIE, $cookieValue, $this->trustedMfaCookieOptions(time() + MFA_REMEMBER_DEVICE_SECONDS));
            return true;
        } catch (Throwable $e) {
            reportException($e, ['action' => 'auth.rememberMfaDevice', 'user_id' => (int) $user->id]);
            return false;
        }
    }

    private function hasTrustedMfaDevice($user)
    {
        $cookieValue = $_COOKIE[MFA_TRUSTED_DEVICE_COOKIE] ?? '';
        if ($cookieValue === '') {
            return false;
        }
        try {
            return $this->security()->isTrustedMfaDevice($cookieValue, (int) $user->id, (int) ($user->session_version ?? 0));
        } catch (Throwable $e) {
            appLog('error', 'Trusted MFA device check failed', ['user_id' => (int) $user->id, 'message' => $e->getMessage()]);
            return false;
        }
    }

    private function passwordResetHeaders()
    {
        $from = PASSWORD_RESET_FROM !== '' ? PASSWORD_RESET_FROM : 'no-reply@localhost';
        return [
            'From' => $from,
            'Reply-To' => $from,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];
    }

    private function sendPasswordResetInstructions($email, $resetUrl)
    {
        $subject = SITENAME . ' password reset';
        $body = "Use this link within 30 minutes to reset your password:\n\n" . $resetUrl;
        $headers = $this->passwordResetHeaders();

        if (SMTP_HOST !== '') {
            return $this->sendSmtpMessage($email, $subject, $body, $headers);
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        return @mail($email, $subject, $body, implode("\r\n", $headerLines));
    }

    private function logDevelopmentPasswordResetUrl($email, $resetUrl)
    {
        if (APP_ENV === 'production') {
            return;
        }

        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s %s%s",
            date('Y-m-d H:i:s'),
            hash('sha256', strtolower(trim((string) $email))),
            $resetUrl,
            PHP_EOL
        );

        if (@file_put_contents($logDir . '/password_resets.log', $line, FILE_APPEND | LOCK_EX) === false) {
            appLog('error', 'Development password reset URL could not be written.');
        }
    }

    private function sendSmtpMessage($to, $subject, $body, $headers)
    {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $target = (SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }

        stream_set_timeout($socket, 20);
        try {
            $this->smtpExpect($socket, [220]);
            $this->smtpCommand($socket, 'EHLO ' . (parse_url(URLROOT, PHP_URL_HOST) ?: 'localhost'), [250]);
            if (SMTP_ENCRYPTION === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS negotiation failed.');
                }
                $this->smtpCommand($socket, 'EHLO ' . (parse_url(URLROOT, PHP_URL_HOST) ?: 'localhost'), [250]);
            }
            if (SMTP_USER !== '') {
                $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
                $this->smtpCommand($socket, base64_encode(SMTP_USER), [334]);
                $this->smtpCommand($socket, base64_encode(SMTP_PASS), [235]);
            }

            $from = $headers['From'] ?? PASSWORD_RESET_FROM;
            $this->smtpCommand($socket, 'MAIL FROM:<' . $this->smtpAddress($from) . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $this->smtpAddress($to) . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);

            $messageHeaders = array_merge($headers, [
                'To' => $to,
                'Subject' => $subject,
                'Date' => date(DATE_RFC2822)
            ]);
            $lines = [];
            foreach ($messageHeaders as $name => $value) {
                $lines[] = $name . ': ' . str_replace(["\r", "\n"], '', (string) $value);
            }
            $message = implode("\r\n", $lines) . "\r\n\r\n" . str_replace("\n.", "\n..", str_replace(["\r\n", "\r"], "\n", $body));
            fwrite($socket, str_replace("\n", "\r\n", $message) . "\r\n.\r\n");
            $this->smtpExpect($socket, [250]);
            // DATA acceptance is the delivery milestone; QUIT failure cannot undo it.
            try { $this->smtpCommand($socket, 'QUIT', [221]); } catch (Throwable $e) {}
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            fclose($socket);
            throw $e;
        }
    }

    private function smtpAddress($address)
    {
        if (preg_match('/<([^>]+)>/', (string) $address, $matches)) {
            return trim($matches[1]);
        }
        return trim((string) $address);
    }

    private function smtpCommand($socket, $command, $expectedCodes)
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $expectedCodes);
    }

    private function smtpExpect($socket, $expectedCodes)
    {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('SMTP server did not respond.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP command failed: ' . trim($response));
        }

        return $response;
    }

    public function mfaSetup()
    {
        $user = !empty($_SESSION['user_id']) ? $this->users()->findById((int) $_SESSION['user_id']) : null;
        if (!$user || ($user->status ?? '') !== 'active') {
            clearAuthenticatedSession();
            redirect('/auth/login', 303);
        }
        $this->requireMfaChallenge($user);
        if (!empty($user->mfa_enabled)) {
            redirect('/dashboard', 303);
        }
        if (empty($_SESSION['mfa_setup_secret'])) {
            $_SESSION['mfa_setup_secret'] = TotpService::generateSecret();
        }
        $secret = $_SESSION['mfa_setup_secret'];
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                validateCsrfOrFail();
                if (!$this->verifyMfaCode($user, $secret)) {
                    throw new ValidationException('The verification code is invalid.');
                }
                $this->users()->configureMfa((int) $user->id, encryptSensitiveValue($secret));
                $_SESSION['session_version'] = $this->users()->currentSessionVersion((int) $user->id);
                $_SESSION['mfa_verified'] = true;
                if (!empty($_POST['remember_device'])) {
                    $user->session_version = $_SESSION['session_version'];
                    $this->rememberMfaDevice($user);
                }
                unset($_SESSION['mfa_setup_secret']);
                securityAudit('mfa_enabled', (int) $user->id, (int) $user->id);
                securityAudit('authentication_completed', (int) $user->id, (int) $user->id, ['method' => 'mfa_enrollment']);
                redirect('/dashboard', 303);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                reportException($e, ['action' => 'auth.mfaSetup', 'user_id' => (int) $user->id]);
                $error = 'MFA setup could not be completed right now. Please try again.';
            }
        }
        $provisioningUri = TotpService::provisioningUri($secret, $user->email ?: $user->id_number, SITENAME);
        $qrCodeDataUri = '';
        try {
            $qrCodeDataUri = QrCodeService::generateSvgDataUri($provisioningUri, 5);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'auth.mfaSetup.qr', 'user_id' => $user->id]);
        }
        $rememberDeviceDays = (int) ceil(MFA_REMEMBER_DEVICE_SECONDS / 86400);
        require_once APPROOT . '/views/auth/mfa_setup.php';
    }

    public function mfa()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
            observeSecurityRequest('mfa', $userId ? 'user:' . $userId : '', $userId);
        }
        $user = !empty($_SESSION['user_id']) ? $this->users()->findById((int) $_SESSION['user_id']) : null;
        if (!$user || ($user->status ?? '') !== 'active' || empty($user->mfa_enabled)) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                securityAudit('security_request_rejected', null, null, ['action' => 'mfa', 'reason' => 'authentication_required']);
            }
            clearAuthenticatedSession();
            redirect('/auth/login', 303);
        }
        $this->requireMfaChallenge($user);
        $rememberDeviceDays = (int) ceil(MFA_REMEMBER_DEVICE_SECONDS / 86400);
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                validateCsrfOrFail();
                $secret = decryptSensitiveValue($user->mfa_secret ?? '');
                if (!$this->verifyMfaCode($user, $secret)) {
                    securityAudit('mfa_failed', (int) $user->id, (int) $user->id);
                    throw new ValidationException('The verification code is invalid.');
                }
                session_regenerate_id(true);
                $_SESSION['mfa_verified'] = true;
                unset($_SESSION['mfa_challenge']);
                $_SESSION['last_activity_at'] = time();
                if (!empty($_POST['remember_device'])) {
                    $this->rememberMfaDevice($user);
                }
                securityAudit('mfa_succeeded', (int) $user->id, (int) $user->id);
                securityAudit('authentication_completed', (int) $user->id, (int) $user->id, ['method' => 'totp']);
                redirect('/dashboard', 303);
            } catch (ValidationException $e) {
                securityAudit('security_request_rejected', null, (int) $user->id, ['action' => 'mfa', 'reason' => 'validation']);
                $error = $e->getMessage();
            } catch (Throwable $e) {
                reportException($e, ['action' => 'auth.mfa', 'user_id' => (int) $user->id]);
                $error = 'Two-factor verification could not be completed right now. Please try again.';
            }
        }
        require_once APPROOT . '/views/auth/mfa.php';
    }

    public function forgotPassword()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            observeSecurityRequest('password_reset', $_POST['email'] ?? '');
        }
        $message = PASSWORD_RESET_ENABLED ? '' : 'Forgot password is temporarily disabled.';
        $developmentResetUrl = '';
        if (!PASSWORD_RESET_ENABLED && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            securityAudit('security_request_rejected', null, null, ['action' => 'password_reset', 'reason' => 'feature_disabled']);
        }
        if (PASSWORD_RESET_ENABLED && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                validateCsrfOrFail();
            } catch (ValidationException $e) {
                securityAudit('security_request_rejected', null, null, ['action' => 'password_reset', 'reason' => 'csrf']);
                throw $e;
            }
            if (PasswordResetProtection::enabled()) {
                $submitted = $_POST['email'] ?? '';
                try {
                    $outcome = PasswordResetProtection::configured()->request(is_string($submitted) ? $submitted : '', 'encryptSensitiveValue');
                    securityAudit('password_reset_guard_processed', null, null, ['outcome' => $outcome]);
                } catch (Throwable $e) {
                    appLog('error', 'Password reset protection unavailable', ['exception_class' => get_class($e)]);
                    securityAudit('password_reset_guard_unavailable');
                }
                $message = 'If the address belongs to an active account and the request is eligible, reset instructions will be emailed. Allow time for delivery and wait at least one minute before trying again. If you still need help, contact your system administrator.';
                require APPROOT . '/views/auth/forgot_password.php';
                return;
            }
            $email = strtolower(trim($_POST['email'] ?? ''));
            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->users()->findByEmail($email) : null;
            if ($user && ($user->status ?? '') === 'active') {
                $token = $this->security()->createPasswordReset((int) $user->id);
                $resetUrl = buildUrl('/auth/resetPassword/' . rawurlencode($token));
                $delivered = false;
                try {
                    $delivered = $this->sendPasswordResetInstructions($email, $resetUrl);
                } catch (Throwable $e) {
                    reportException($e, ['action' => 'auth.forgotPassword.mail', 'user_id' => $user->id, 'email_hash' => hash('sha256', $email)]);
                }
                if (APP_ENV !== 'production') {
                    $this->logDevelopmentPasswordResetUrl($email, $resetUrl);
                    $developmentResetUrl = $resetUrl;
                }
                securityAudit('password_reset_requested', null, (int) $user->id, [
                    'mail_accepted' => (bool) $delivered,
                    'transport' => SMTP_HOST !== '' ? 'smtp' : 'mail'
                ]);
            } else {
                securityAudit('security_request_processed', null, null, ['action' => 'password_reset', 'delivery' => 'not_attempted']);
            }
            $message = 'If the address belongs to an active account, password-reset instructions have been sent.';
        }
        require_once APPROOT . '/views/auth/forgot_password.php';
    }

    public function resetPassword($token = '')
    {
        $reset = false;
        try {
            $reset = $this->security()->findValidPasswordReset((string) $token);
        } catch (Throwable $e) {
            appLog('error', 'Password reset lookup unavailable', ['exception_class' => get_class($e)]);
            $error = 'Your password could not be reset right now. Please try again later or contact your system administrator.';
            require APPROOT . '/views/auth/reset_password.php';
            return;
        }
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
                if (PasswordResetProtection::enabled()) {
                    $completedUser = PasswordResetProtection::configured()->complete((string) $token, $password);
                    if ($completedUser === null) { throw new ValidationException('This password-reset link is invalid or expired.'); }
                } else {
                    $this->users()->updatePassword((int) $reset->user_id, $password);
                    $this->security()->consumePasswordReset((int) $reset->id);
                }
                securityAudit('password_reset_completed', (int) $reset->user_id, (int) $reset->user_id);
                flash('auth_success', 'Your password has been reset. You can now sign in.', 'success');
                redirect('/auth/login', 303);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                appLog('error', 'Password reset completion unavailable', ['exception_class' => get_class($e)]);
                $error = 'Your password could not be reset right now. Please try again later or contact your system administrator.';
            }
        }
        require_once APPROOT . '/views/auth/reset_password.php';
    }

    public function deliverQueuedResetMail()
    {
        if (PHP_SAPI !== 'cli') { http_response_code(404); return; }
        if (!PASSWORD_RESET_ENABLED || !PasswordResetProtection::enabled()) { throw new RuntimeException('Protected password reset delivery is disabled.'); }
        $status = PasswordResetProtection::configured()->deliverOne('decryptSensitiveValue', function ($email, $token) {
            return $this->sendPasswordResetInstructions($email, buildUrl('/auth/resetPassword/' . rawurlencode($token)));
        });
        if ($status !== 'empty') { securityAudit('password_reset_mail_processed', null, null, ['delivery' => $status]); }
        return $status;
    }
}
