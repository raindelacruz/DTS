<?php

require_once '../app/models/User.php';
require_once '../app/models/Department.php';
require_once '../app/models/Notification.php';

class Auth extends Controller
{
    private $userModel;
    private $departmentModel;
    private $notificationModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->departmentModel = new Department();
        $this->notificationModel = new Notification();
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

                $user = $this->userModel->login($values['id_number'], $password);

                if (!$user) {
                    throw new ValidationException('Invalid credentials.', [
                        '_global' => 'The ID number or password you entered is incorrect.'
                    ]);
                }

                if (($user->status ?? 'inactive') !== 'active') {
                    throw new ValidationException('Your account is inactive.', [
                        '_global' => 'Your account is inactive. Please wait for administrator verification.'
                    ]);
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user->id;
                $_SESSION['department_id'] = $user->department_id;
                $_SESSION['role'] = $user->role;
                $_SESSION['fullname'] = $user->firstname . ' ' . $user->lastname;
                $_SESSION['email'] = $user->email ?? '';

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

                if (strlen($password) < 6) {
                    $errors['password'] = 'Password must be at least 6 characters.';
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
}
