<?php

require_once '../app/models/Department.php';

class Departments extends Controller
{
    private $departmentModel;

    public function __construct()
    {
        requireLogin();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            throw new AuthorizationException('Access denied.');
        }
        $this->departmentModel = new Department();
    }

    public function index()
    {
        $this->view('departments/index', [
            'departments' => $this->departmentModel->getParentDepartmentsWithDivisionCount()
        ]);
    }

    public function show($id)
    {
        $id = (int) $id;
        $department = $this->departmentModel->getDepartmentById($id);
        if (!$department || $department['parent_id'] !== null) {
            flash('departments_error', 'Department not found.', 'error');
            redirect('/departments', 303);
        }

        $this->view('departments/show', [
            'department' => $department,
            'divisions' => $this->departmentModel->getChildDepartmentsForParent($id),
            'department_form' => pullFormState('department_edit_' . $id, $department),
            'division_form' => pullFormState('division_add_' . $id, [
                'division_name' => '', 'code' => '', 'email' => ''
            ])
        ]);
    }

    public function update($id)
    {
        $id = (int) $id;
        $values = $this->values(true);
        try {
            requirePost();
            validateCsrfOrFail();
            $department = $this->requireParent($id);
            $this->validate($values, $id);
            $this->departmentModel->updateParent(
                $id, $values['department_name'], $values['division_name'],
                $values['code'], $values['email']
            );
            flash('departments_success', 'Department details updated.', 'success');
        } catch (ValidationException $e) {
            storeFormState('department_edit_' . $id, $values, $e->getErrors(), $e->getMessage());
        } catch (Throwable $e) {
            reportException($e, ['action' => 'departments.update', 'department_id' => $id]);
            flash('departments_error', 'The department could not be updated.', 'error');
        }
        redirect('/departments/show/' . $id, 303);
    }

    public function addDivision($id)
    {
        $id = (int) $id;
        $values = $this->values(false);
        try {
            requirePost();
            validateCsrfOrFail();
            $this->requireParent($id);
            $this->validate($values, null, $id);
            $this->departmentModel->createDivision($id, $values['division_name'], $values['code'], $values['email']);
            flash('departments_success', 'Division added successfully.', 'success');
        } catch (ValidationException $e) {
            storeFormState('division_add_' . $id, $values, $e->getErrors(), $e->getMessage());
        } catch (Throwable $e) {
            reportException($e, ['action' => 'departments.addDivision', 'department_id' => $id]);
            flash('departments_error', 'The division could not be added.', 'error');
        }
        redirect('/departments/show/' . $id, 303);
    }

    public function updateDivision($id)
    {
        $divisionId = (int) $id;
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $values = $this->values(false);
        try {
            requirePost();
            validateCsrfOrFail();
            $this->requireParent($parentId);
            $division = $this->departmentModel->getDepartmentById($divisionId);
            if (!$division || (int) $division['parent_id'] !== $parentId) {
                throw new ValidationException('Division not found.');
            }
            $this->validate($values, $divisionId, $parentId);
            $this->departmentModel->updateDivision($divisionId, $parentId, $values['division_name'], $values['code'], $values['email']);
            flash('departments_success', 'Division updated successfully.', 'success');
        } catch (ValidationException $e) {
            flash('departments_error', $e->getMessage(), 'error');
        } catch (Throwable $e) {
            reportException($e, ['action' => 'departments.updateDivision', 'division_id' => $divisionId]);
            flash('departments_error', 'The division could not be updated.', 'error');
        }
        redirect('/departments/show/' . $parentId, 303);
    }

    private function requireParent($id)
    {
        $department = $this->departmentModel->getDepartmentById((int) $id);
        if (!$department || $department['parent_id'] !== null) {
            throw new ValidationException('Department not found.');
        }
        return $department;
    }

    private function values($withDepartment)
    {
        $values = [
            'division_name' => trim($_POST['division_name'] ?? ''),
            'code' => strtoupper(trim($_POST['code'] ?? '')),
            'email' => strtolower(trim($_POST['email'] ?? ''))
        ];
        if ($withDepartment) {
            $values['department_name'] = trim($_POST['department_name'] ?? '');
        }
        return $values;
    }

    private function validate($values, $excludeId = null, $parentId = null)
    {
        $errors = [];
        if (array_key_exists('department_name', $values) && $values['department_name'] === '') {
            $errors['department_name'] = 'Department name is required.';
        }
        if ($values['division_name'] === '') $errors['division_name'] = 'Office or division name is required.';
        if ($values['code'] === '') {
            $errors['code'] = 'Code is required.';
        } elseif (strlen($values['code']) > 50) {
            $errors['code'] = 'Code must not exceed 50 characters.';
        } elseif ($this->departmentModel->codeExists($values['code'], $excludeId)) {
            $errors['code'] = 'That code is already in use.';
        }
        if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }
        if ($parentId !== null && $values['division_name'] !== '' &&
            $this->departmentModel->divisionNameExists($parentId, $values['division_name'], $excludeId)) {
            $errors['division_name'] = 'That division already exists in this department.';
        }
        if ($errors) throw new ValidationException('Please correct the highlighted fields.', $errors);
    }
}
