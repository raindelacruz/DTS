<?php require_once '../app/views/layout/header.php'; ?>

<?php
$values = $data['values'] ?? [];
$errors = $data['errors'] ?? [];
$isParentDepartment = !empty($data['is_parent_department']);
?>

<div class="page-hero compact">
    <div>
        <h1 class="section-title">New Action Slip</h1>
        <div class="text-muted small mt-1"><?php echo htmlspecialchars($data['next_slip_number'] ?? ''); ?></div>
    </div>
    <a href="<?php echo URLROOT; ?>/actionSlips" class="btn btn-outline-secondary">Back</a>
</div>

<?php if (!empty($data['message'])): ?>
    <div class="alert alert-danger app-card border-0"><?php echo htmlspecialchars($data['message']); ?></div>
<?php endif; ?>

<div class="app-card p-4">
    <form action="<?php echo URLROOT; ?>/actionSlips/store" method="POST" enctype="multipart/form-data" class="row g-3">
        <?php echo csrfInput(); ?>

        <div class="col-lg-6">
            <label class="form-label fw-semibold">Tracking Number</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['next_slip_number'] ?? ''); ?>" readonly>
        </div>
        <div class="col-lg-3 col-md-6">
            <label for="date_received" class="form-label fw-semibold">Date of Action Slip</label>
            <input type="date" id="date_received" name="date_received" class="form-control <?php echo isset($errors['date_received']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($values['date_received'] ?? date('Y-m-d')); ?>" required>
            <?php if (isset($errors['date_received'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['date_received']); ?></div><?php endif; ?>
        </div>
        <div class="col-lg-3 col-md-6 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="checkbox" id="urgent" name="urgent" value="1" class="form-check-input" <?php echo !empty($values['urgent']) ? 'checked' : ''; ?>>
                <label for="urgent" class="form-check-label fw-semibold">Urgent</label>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <label for="deadline" class="form-label fw-semibold">Deadline</label>
            <input type="date" id="deadline" name="deadline" class="form-control <?php echo isset($errors['deadline']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($values['deadline'] ?? ''); ?>">
            <?php if (isset($errors['deadline'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['deadline']); ?></div><?php endif; ?>
        </div>
        <div class="col-lg-4 col-md-6">
            <label for="required_action" class="form-label fw-semibold">Action</label>
            <select id="required_action" name="required_action" class="form-select <?php echo isset($errors['required_action']) ? 'is-invalid' : ''; ?>" required>
                <option value="">Select action</option>
                <?php foreach (($data['action_options'] ?? []) as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (($values['required_action'] ?? '') === $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['required_action'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['required_action']); ?></div><?php endif; ?>
        </div>
        <div class="col-lg-4">
            <label for="attachment" class="form-label fw-semibold">Attachment</label>
            <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx">
        </div>
        <div class="col-12">
            <label for="remarks" class="form-label fw-semibold">Instruction</label>
            <textarea id="remarks" name="remarks" rows="3" class="form-control"><?php echo htmlspecialchars($values['remarks'] ?? ''); ?></textarea>
        </div>

        <div class="col-12 pt-2">
            <div class="border-top pt-3"></div>
        </div>

        <div class="col-lg-4 col-md-6">
            <label for="receiving_level" class="form-label fw-semibold">Release To</label>
            <select id="receiving_level" name="receiving_level" class="form-select <?php echo isset($errors['receiving_level']) ? 'is-invalid' : ''; ?>">
                <?php if (($_SESSION['role'] ?? '') === 'manager' && $isParentDepartment): ?>
                    <option value="Department" <?php echo (($values['receiving_level'] ?? 'Department') === 'Department') ? 'selected' : ''; ?>>Department</option>
                    <option value="Division" <?php echo (($values['receiving_level'] ?? '') === 'Division') ? 'selected' : ''; ?>>Division</option>
                <?php else: ?>
                    <option value="Staff" selected>Staff</option>
                <?php endif; ?>
            </select>
            <?php if (isset($errors['receiving_level'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['receiving_level']); ?></div><?php endif; ?>
        </div>

        <div class="col-lg-4 col-md-6" data-department-wrap>
            <label for="receiving_department_id" class="form-label fw-semibold">Target Department</label>
            <select id="receiving_department_id" name="receiving_department_id" class="form-select <?php echo isset($errors['receiving_department_id']) ? 'is-invalid' : ''; ?>">
                <option value="">Select department</option>
                <?php foreach (($data['departments'] ?? []) as $department): ?>
                    <?php if ((int) $department['id'] === (int) ($_SESSION['department_id'] ?? 0)) { continue; } ?>
                    <option value="<?php echo (int) $department['id']; ?>" <?php echo ((int) ($values['receiving_department_id'] ?? 0) === (int) $department['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['division_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['receiving_department_id'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['receiving_department_id']); ?></div><?php endif; ?>
        </div>

        <div class="col-lg-4 col-md-6" data-division-wrap>
            <label for="receiving_division_id" class="form-label fw-semibold">Target Division</label>
            <select id="receiving_division_id" name="receiving_division_id" class="form-select <?php echo isset($errors['receiving_division_id']) ? 'is-invalid' : ''; ?>">
                <option value="">Select division</option>
                <?php foreach (($data['divisions'] ?? []) as $division): ?>
                    <option value="<?php echo (int) $division['id']; ?>" data-parent="<?php echo (int) $division['parent_id']; ?>" <?php echo ((int) ($values['receiving_division_id'] ?? 0) === (int) $division['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($division['division_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['receiving_division_id'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['receiving_division_id']); ?></div><?php endif; ?>
        </div>

        <div class="col-lg-4 col-md-6" data-staff-wrap>
            <label for="assigned_staff_id" class="form-label fw-semibold">Target Staff</label>
            <select id="assigned_staff_id" name="assigned_staff_id" class="form-select <?php echo isset($errors['assigned_staff_id']) ? 'is-invalid' : ''; ?>">
                <option value="">Select staff</option>
                <?php foreach (($data['division_staff'] ?? []) as $staff): ?>
                    <?php $name = trim(($staff['firstname'] ?? '') . ' ' . (!empty($staff['middle_initial']) ? $staff['middle_initial'] . '. ' : '') . ($staff['lastname'] ?? '')); ?>
                    <option value="<?php echo (int) $staff['id']; ?>" <?php echo ((int) ($values['assigned_staff_id'] ?? 0) === (int) $staff['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['assigned_staff_id'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['assigned_staff_id']); ?></div><?php endif; ?>
        </div>

        <div class="col-12 d-flex gap-2 justify-content-end pt-2">
            <a href="<?php echo URLROOT; ?>/actionSlips" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Action Slip</button>
        </div>
    </form>
</div>

<script>
(() => {
    const level = document.getElementById('receiving_level');
    const deptWrap = document.querySelector('[data-department-wrap]');
    const divisionWrap = document.querySelector('[data-division-wrap]');
    const staffWrap = document.querySelector('[data-staff-wrap]');
    const department = document.getElementById('receiving_department_id');
    const division = document.getElementById('receiving_division_id');
    const staff = document.getElementById('assigned_staff_id');
    const currentDepartmentId = '<?php echo (int) ($_SESSION['department_id'] ?? 0); ?>';

    function refreshTargets() {
        const target = level.value;
        deptWrap.style.display = target === 'Department' ? '' : 'none';
        divisionWrap.style.display = target === 'Division' ? '' : 'none';
        staffWrap.style.display = target === 'Staff' ? '' : 'none';
        department.required = target === 'Department';
        division.required = target === 'Division';
        staff.required = target === 'Staff';

        Array.from(division.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }
            option.hidden = option.getAttribute('data-parent') !== currentDepartmentId;
            if (option.hidden && option.selected) {
                division.value = '';
            }
        });
    }

    level.addEventListener('change', refreshTargets);
    refreshTargets();
})();
</script>

<?php require_once '../app/views/layout/footer.php'; ?>
