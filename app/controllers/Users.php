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
                'roles' => User::roles(),
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

    public function show($id)
    {
        try {
            $this->requireAdmin();

            $user = $this->userModel->findWithDepartmentById((int) $id);
            if (!$user) {
                throw new NotFoundException('User not found.');
            }

            $data = [
                'user' => $user,
                'departments' => $this->departmentModel->getAll(),
                'roles' => User::roles(),
                'success' => pullFlash('users_success')['message'] ?? '',
                'error' => pullFlash('users_error')['message'] ?? ''
            ];

            $this->view('users/show', $data);
        } catch (AuthorizationException $e) {
            flash('error', 'You are not allowed to access user management.', 'error');
            redirect('/dashboard', 303);
        } catch (NotFoundException $e) {
            flash('users_error', 'User not found.', 'error');
            redirect('/users', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.show', 'target_user_id' => (int) $id, 'user_id' => $_SESSION['user_id'] ?? null]);
            flash('users_error', 'We could not load that user right now.', 'error');
            redirect('/users', 303);
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
            $passwordState = pullFormState('user_password', []);

            $data = [
                'user' => $user,
                'departments' => $this->departmentModel->getAll(),
                'success' => pullFlash('profile_success')['message'] ?? '',
                'error' => pullFlash('profile_error')['message'] ?? '',
                'values' => $state['values'],
                'errors' => $state['errors'],
                'message' => $state['message'],
                'password_errors' => $passwordState['errors'],
                'password_message' => $passwordState['message']
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

    public function updatePassword()
    {
        try {
            requirePost();
            validateCsrfOrFail();

            $currentPassword = trim($_POST['current_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            $errors = [];

            if ($currentPassword === '') {
                $errors['current_password'] = 'Current password is required.';
            }

            if (strlen($newPassword) < 6) {
                $errors['new_password'] = 'New password must be at least 6 characters.';
            }

            if ($confirmPassword === '') {
                $errors['confirm_password'] = 'Please confirm your new password.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }

            $user = $this->userModel->findById((int) $_SESSION['user_id']);
            if (!$user) {
                throw new NotFoundException('User not found.');
            }

            if ($currentPassword !== '' && !password_verify($currentPassword, $user->password)) {
                $errors['current_password'] = 'Current password is incorrect.';
            }

            if (!empty($errors)) {
                throw new ValidationException('Please correct the highlighted password fields.', $errors);
            }

            $this->userModel->updatePassword((int) $_SESSION['user_id'], $newPassword);

            flash('profile_success', 'Password updated successfully.', 'success');
            redirect('/users/profile', 303);
        } catch (ValidationException $e) {
            storeFormState('user_password', [], $e->getErrors(), $e->getMessage());
            redirect('/users/profile', 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.updatePassword', 'user_id' => $_SESSION['user_id'] ?? null]);
            storeFormState('user_password', [], [], 'We could not update your password right now. Please try again.');
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

    public function updateRole($id)
    {
        $role = trim($_POST['role'] ?? '');
        $redirectPath = '/users/show/' . (int) $id;

        try {
            $this->requireAdmin();
            requirePost();
            validateCsrfOrFail();

            $user = $this->userModel->findById((int) $id);
            if (!$user) {
                throw new ValidationException('User not found.');
            }

            if (!User::roleExists($role)) {
                throw new ValidationException('Please select a valid role.');
            }

            if ((int) $user->id === (int) $_SESSION['user_id'] && $role !== 'admin') {
                throw new ValidationException('You cannot remove your own administrator role.');
            }

            if ((string) $user->role === $role) {
                flash('users_success', 'Role is already up to date.', 'success');
                redirect($redirectPath, 303);
            }

            $this->userModel->updateRole((int) $id, $role);

            $this->notificationModel->create(
                (int) $user->id,
                'Role updated',
                'Your account role has been updated to ' . User::roles()[$role] . '.',
                '/dashboard'
            );

            flash('users_success', 'User role updated successfully.', 'success');
            redirect($redirectPath, 303);
        } catch (AuthorizationException $e) {
            flash('users_error', 'You are not allowed to change user roles.', 'error');
            redirect('/dashboard', 303);
        } catch (ValidationException $e) {
            flash('users_error', $e->getMessage(), 'error');
            redirect($redirectPath, 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.updateRole', 'target_user_id' => (int) $id, 'role' => $role]);
            flash('users_error', 'We could not update that role right now. Please try again.', 'error');
            redirect($redirectPath, 303);
        }
    }

    public function updateDepartment($id)
    {
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $redirectPath = '/users/show/' . (int) $id;

        try {
            $this->requireAdmin();
            requirePost();
            validateCsrfOrFail();

            $user = $this->userModel->findById((int) $id);
            if (!$user) {
                throw new ValidationException('User not found.');
            }

            if ($departmentId <= 0 || !$this->departmentModel->getDepartmentById($departmentId)) {
                throw new ValidationException('Please select a valid department.');
            }

            if ((int) $user->department_id === $departmentId) {
                flash('users_success', 'Department is already up to date.', 'success');
                redirect($redirectPath, 303);
            }

            $this->userModel->updateDepartment((int) $id, $departmentId);

            if ((int) $user->id === (int) $_SESSION['user_id']) {
                $_SESSION['department_id'] = $departmentId;
            }

            $this->notificationModel->create(
                (int) $user->id,
                'Department updated',
                'Your account department has been updated by an administrator.',
                '/users/profile'
            );

            flash('users_success', 'User department updated successfully.', 'success');
            redirect($redirectPath, 303);
        } catch (AuthorizationException $e) {
            flash('users_error', 'You are not allowed to change user departments.', 'error');
            redirect('/dashboard', 303);
        } catch (ValidationException $e) {
            flash('users_error', $e->getMessage(), 'error');
            redirect($redirectPath, 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.updateDepartment', 'target_user_id' => (int) $id, 'department_id' => $departmentId]);
            flash('users_error', 'We could not update that department right now. Please try again.', 'error');
            redirect($redirectPath, 303);
        }
    }

    public function updateUserPassword($id)
    {
        $redirectPath = '/users/show/' . (int) $id;

        try {
            $this->requireAdmin();
            requirePost();
            validateCsrfOrFail();

            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            $user = $this->userModel->findById((int) $id);
            if (!$user) {
                throw new ValidationException('User not found.');
            }

            if (strlen($newPassword) < 6) {
                throw new ValidationException('New password must be at least 6 characters.');
            }

            if ($confirmPassword === '' || $newPassword !== $confirmPassword) {
                throw new ValidationException('Password confirmation does not match.');
            }

            $this->userModel->updatePassword((int) $id, $newPassword);

            $this->notificationModel->create(
                (int) $user->id,
                'Password updated',
                'Your account password has been updated by an administrator.',
                '/users/profile'
            );

            flash('users_success', 'User password updated successfully.', 'success');
            redirect($redirectPath, 303);
        } catch (AuthorizationException $e) {
            flash('users_error', 'You are not allowed to change user passwords.', 'error');
            redirect('/dashboard', 303);
        } catch (ValidationException $e) {
            flash('users_error', $e->getMessage(), 'error');
            redirect($redirectPath, 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.updateUserPassword', 'target_user_id' => (int) $id]);
            flash('users_error', 'We could not update that password right now. Please try again.', 'error');
            redirect($redirectPath, 303);
        }
    }

    public function updateStatus($id)
    {
        $status = trim($_POST['status'] ?? '');

        if (!in_array($status, ['active', 'inactive'], true)) {
            flash('users_error', 'Please select a valid status.', 'error');
            redirect('/users/show/' . (int) $id, 303);
        }

        $this->setStatus($id, $status);
    }

    private function setStatus($id, $status)
    {
        $redirectPath = '/users/show/' . (int) $id;

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
            redirect($redirectPath, 303);
        } catch (AuthorizationException $e) {
            flash('users_error', 'You are not allowed to change user status.', 'error');
            redirect('/dashboard', 303);
        } catch (ValidationException $e) {
            flash('users_error', $e->getMessage(), 'error');
            redirect($redirectPath, 303);
        } catch (Throwable $e) {
            reportException($e, ['action' => 'users.setStatus', 'target_user_id' => (int) $id, 'status' => $status]);
            flash('users_error', 'We could not update that account right now. Please try again.', 'error');
            redirect($redirectPath, 303);
        }
    }
}
