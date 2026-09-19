<?php

require_once APPROOT . '/models/Department.php';

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

    public function create()
    {
        $this->view('departments/create', [
            'department_form' => pullFormState('department_create', [
                'department_name' => '', 'division_name' => '', 'code' => '', 'email' => ''
            ])
        ]);
    }

    public function store()
    {
        $values = $this->values(true);
        try {
            requirePost();
            validateCsrfOrFail();
            $this->validate($values);
            if ($this->departmentModel->parentDepartmentNameExists($values['department_name'])) {
                throw new ValidationException('Please correct the highlighted fields.', [
                    'department_name' => 'That department already exists.'
                ]);
            }
            $id = $this->departmentModel->createParent(
                $values['department_name'], $values['division_name'], $values['code'], $values['email']
            );
            flash('departments_success', 'Department added successfully.', 'success');
            redirect('/departments/show/' . $id, 303);
        } catch (ValidationException $e) {
            storeFormState('department_create', $values, $e->getErrors(), $e->getMessage());
        } catch (Throwable $e) {
            reportException($e, ['action' => 'departments.store']);
            flash('departments_error', 'The department could not be added.', 'error');
        }
        redirect('/departments/create', 303);
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
            'units_by_division' => $this->unitsByDivision($id),
            'department_form' => pullFormState('department_edit_' . $id, $department),
            'division_form' => pullFormState('division_add_' . $id, [
                'division_name' => '', 'code' => '', 'email' => ''
            ]),
            'unit_forms' => $this->unitForms($id)
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

    public function addUnit($id)
    {
        $divisionId = (int) $id;
        $values = $this->values(false);
        $redirectDepartmentId = $this->getDepartmentRootId($divisionId);
        try {
            requirePost();
            validateCsrfOrFail();
            $this->requireDivision($divisionId);
            $this->validate($values, null, $divisionId, 'unit');
            $this->departmentModel->createUnit($divisionId, $values['division_name'], $values['code'], $values['email']);
            flash('departments_success', 'Unit added successfully.', 'success');
        } catch (ValidationException $e) {
            storeFormState('unit_add_' . $divisionId, $values, $e->getErrors(), $e->getMessage());
        } catch (Throwable $e) {
            reportException($e, ['action' => 'departments.addUnit', 'division_id' => $divisionId]);
            flash('departments_error', 'The unit could not be added.', 'error');
        }
        redirect($redirectDepartmentId > 0 ? '/departments/show/' . $redirectDepartmentId : '/departments', 303);
    }

    public function updateUnit($id)
    {
        $unitId = (int) $id;
        $divisionId = (int) ($_POST['division_id'] ?? 0);
        $values = $this->values(false);
        $redirectDepartmentId = $this->getDepartmentRootId($divisionId);
        try {
            requirePost();
            validateCsrfOrFail();
            $this->requireDivision($divisionId);
            $unit = $this->departmentModel->getDepartmentById($unitId);
            if (!$unit || (int) $unit['parent_id'] !== $divisionId || ($unit['office_level'] ?? '') !== 'unit') {
                throw new ValidationException('Unit not found.');
            }
            $this->validate($values, $unitId, $divisionId, 'unit');
            $this->departmentModel->updateUnit($unitId, $divisionId, $values['division_name'], $values['code'], $values['email']);
            flash('departments_success', 'Unit updated successfully.', 'success');
        } catch (ValidationException $e) {
            flash('departments_error', $e->getMessage(), 'error');
        } catch (Throwable $e) {
            reportException($e, ['action' => 'departments.updateUnit', 'unit_id' => $unitId]);
            flash('departments_error', 'The unit could not be updated.', 'error');
        }
        redirect($redirectDepartmentId > 0 ? '/departments/show/' . $redirectDepartmentId : '/departments', 303);
    }

    private function requireParent($id)
    {
        $department = $this->departmentModel->getDepartmentById((int) $id);
        if (!$department || $department['parent_id'] !== null) {
            throw new ValidationException('Department not found.');
        }
        return $department;
    }

    private function requireDivision($id)
    {
        $division = $this->departmentModel->getDepartmentById((int) $id);
        if (!$division || ($division['office_level'] ?? '') !== 'division') {
            throw new ValidationException('Division not found.');
        }
        return $division;
    }

    private function getDepartmentRootId($officeId)
    {
        $office = $this->departmentModel->getDepartmentById((int) $officeId);
        if (!$office) {
            return 0;
        }

        if (($office['office_level'] ?? '') === 'department') {
            return (int) $office['id'];
        }

        $parent = $this->departmentModel->getDepartmentById((int) ($office['parent_id'] ?? 0));
        if ($parent && ($parent['office_level'] ?? '') === 'department') {
            return (int) $parent['id'];
        }

        return $parent ? $this->getDepartmentRootId((int) $parent['id']) : 0;
    }

    private function unitsByDivision($departmentId)
    {
        $unitsByDivision = [];
        foreach ($this->departmentModel->getChildDepartmentsForParent((int) $departmentId) as $division) {
            $unitsByDivision[(int) $division['id']] = $this->departmentModel->getUnitsForDivision((int) $division['id']);
        }
        return $unitsByDivision;
    }

    private function unitForms($departmentId)
    {
        $forms = [];
        foreach ($this->departmentModel->getChildDepartmentsForParent((int) $departmentId) as $division) {
            $forms[(int) $division['id']] = pullFormState('unit_add_' . (int) $division['id'], [
                'division_name' => '', 'code' => '', 'email' => ''
            ]);
        }
        return $forms;
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

    private function validate($values, $excludeId = null, $parentId = null, $officeLevel = 'division')
    {
        $errors = [];
        if (array_key_exists('department_name', $values) && $values['department_name'] === '') {
            $errors['department_name'] = 'Department name is required.';
        } elseif (array_key_exists('department_name', $values) && strlen($values['department_name']) > 150) {
            $errors['department_name'] = 'Department name must not exceed 150 characters.';
        }
        if ($values['division_name'] === '') {
            $errors['division_name'] = $officeLevel === 'unit' ? 'Unit name is required.' : 'Office or division name is required.';
        } elseif (strlen($values['division_name']) > 150) {
            $errors['division_name'] = 'Office or division name must not exceed 150 characters.';
        }
        if ($values['code'] === '') {
            $errors['code'] = 'Code is required.';
        } elseif (strlen($values['code']) > 50) {
            $errors['code'] = 'Code must not exceed 50 characters.';
        } elseif ($this->departmentModel->codeExists($values['code'], $excludeId)) {
            $errors['code'] = 'That code is already in use.';
        }
        if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        } elseif (strlen($values['email']) > 150) {
            $errors['email'] = 'Email must not exceed 150 characters.';
        }
        if ($parentId !== null && $values['division_name'] !== '' &&
            $this->departmentModel->divisionNameExists($parentId, $values['division_name'], $excludeId)) {
            $errors['division_name'] = $officeLevel === 'unit' ? 'That unit already exists in this division.' : 'That division already exists in this department.';
        }
        if ($errors) throw new ValidationException('Please correct the highlighted fields.', $errors);
    }
}
