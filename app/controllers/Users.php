<?php

require_once '../app/models/User.php';
require_once '../app/models/Department.php';
require_once '../app/models/Notification.php';

class Users extends Controller
{
    private $userModel;
    private $departmentModel;
    private $notificationModel;

    public function __construct()
    {
        requireLogin();

        $this->userModel = new User();
        $this->departmentModel = new Department();
        $this->notificationModel = new Notification();
    }

    private function requireAdmin()
    {
        if (($_SESSION['role'] ?? '') !== 'admin') {
            throw new AuthorizationException('Access denied.');
        }
    }

    public function index()
    {
        try {
            $this->requireAdmin();

            $data = [
                'users' => $this->userModel->getAllWithDepartments(),
                'success' => pullFlash('users_success')['message'] ?? '',
                'error' => pullFlash('users_error')['message'] ?? ''
            ];

            $this->view('users/index', $data);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to access user management.', 'error');
            redirect('/dashboard', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.index', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('error', 'We could not load the user list right now.', 'error');
            redirect('/dashboard', 303);
        }
    }

    public function profile()
    {
        try {
            $user = $this->userModel->findWithDepartmentById((int) $_SESSION['user_id']);
            if (!$user) {
                throw new NotFoundException('User not found.');
            }

            $state = pullFormState('user_profile', [
                'department_id' => (string) ($user->department_id ?? ''),
                'email' => (string) ($user->email ?? '')
            ]);

            $data = [
                'user' => $user,
                'departments' => $this->departmentModel->getAll(),
                'success' => pullFlash('profile_success')['message'] ?? '',
                'error' => pullFlash('profile_error')['message'] ?? '',
                'values' => $state['values'],
                'errors' => $state['errors'],
                'message' => $state['message']
            ];

            $this->view('users/profile', $data);
        } catch (NotFoundException $e) {
            flash('error', 'Your profile could not be loaded.', 'error');
            redirect('/dashboard', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.profile', 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('error', 'We could not load your profile right now.', 'error');
            redirect('/dashboard', 303);
        }
    }

    public function updateProfile()
    {
        $values = [
            'department_id' => (string) ((int) ($_POST['department_id'] ?? 0)),
            'email' => strtolower(trim($_POST['email'] ?? ''))
        ];

        try {
            requirePost();
            validateCsrfOrFail();

            $errors = [];

            if ((int) $values['department_id'] <= 0) {
                $errors['department_id'] = 'Please select a department.';
            }

            if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please provide a valid email address.';
            }

            if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) && $this->userModel->emailExistsForOtherUser($values['email'], (int) $_SESSION['user_id'])) {
                $errors['email'] = 'That email address is already in use.';
            }

            if (!empty($errors)) {
                throw new ValidationException('Please correct the highlighted fields.', $errors);
            }

            $this->userModel->updateProfile((int) $_SESSION['user_id'], (int) $values['department_id'], $values['email']);
            $_SESSION['department_id'] = (int) $values['department_id'];
            $_SESSION['email'] = $values['email'];

            flash('profile_success', 'Profile updated successfully.', 'success');
            redirect('/users/profile', 303);
        } catch (ValidationException $e) {
            storeFormState('user_profile', $values, $e->getErrors(), $e->getMessage());
            redirect('/users/profile', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.updateProfile', 'user_id' => $_SESSION['user_id'] ?? null]);
            storeFormState('user_profile', $values, [], 'We could not save your profile right now. Please try again.');
            redirect('/users/profile', 303);
        }
    }

    public function activate($id)
    {
        $this->setStatus($id, 'active');
    }

    public function deactivate($id)
    {
        $this->setStatus($id, 'inactive');
    }

    private function setStatus($id, $status)
    {
        try {
            $this->requireAdmin();
            requirePost();
            validateCsrfOrFail();

            $user = $this->userModel->findById((int) $id);
            if (!$user) {
                throw new ValidationException('User not found.');
            }

            if ((int) $user->id === (int) $_SESSION['user_id'] && $status === 'inactive') {
                throw new ValidationException('You cannot deactivate your own account.');
            }

            $this->userModel->updateStatus((int) $id, $status);

            if ($status === 'active') {
                $this->notificationModel->create(
                    (int) $user->id,
                    'Account activated',
                    'Your account has been activated.',
                    '/dashboard'
                );
            }

            flash(
                'users_success',
                $status === 'active' ? 'User activated successfully.' : 'User deactivated successfully.',
                'success'
            );
            redirect('/users', 303);
        } catch (AuthorizationException $e) {
            flash('users_error', 'You are not allowed to change user status.', 'error');
            redirect('/dashboard', 303);
        } catch (ValidationException $e) {
            flash('users_error', $e->getMessage(), 'error');
            redirect('/users', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.setStatus', 'target_user_id' => (int) $id, 'status' => $status]);
            flash('users_error', 'We could not update that account right now. Please try again.', 'error');
            redirect('/users', 303);
        }
    }
}
