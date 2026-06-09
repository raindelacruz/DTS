<?php require_once '../app/views/layout/header.php'; ?>

<?php
$values = $data['values'] ?? [];
$errors = $data['errors'] ?? [];
$isParentDepartment = !empty($data['is_parent_department']);
$isStaffDraft = !empty($data['is_staff_draft']);
$selectedStaffIds = array_map('intval', (array) ($values['assigned_staff_ids'] ?? []));
if (empty($selectedStaffIds) && !empty($values['assigned_staff_id'])) {
    $selectedStaffIds = [(int) $values['assigned_staff_id']];
}
?>

<style>
    .staff-picker {
        border: 1px solid #dbe5ef;
        border-radius: 0.95rem;
        background: #fff;
        overflow: hidden;
    }
    .staff-picker.is-invalid {
        border-color: #dc3545;
    }
    .staff-picker-search {
        border: 0;
        border-bottom: 1px solid #e2e8f0;
        border-radius: 0;
    }
    .staff-picker-search:focus {
        box-shadow: none;
        border-color: #b7c7d8;
    }
    .staff-picker-list {
        max-height: 174px;
        overflow-y: auto;
        padding: 0.35rem;
    }
    .staff-picker-option {
        min-height: 54px;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.62rem 0.7rem;
        border-radius: 0.65rem;
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease;
    }
    .staff-picker-option:hover,
    .staff-picker-option:has(.form-check-input:checked) {
        background: #ecfdf5;
        color: #0f766e;
    }
    .staff-picker-empty {
        display: none;
        padding: 0.8rem;
        color: #64748b;
    }
    .staff-picker-count {
        padding: 0.5rem 0.8rem;
        border-top: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 0.85rem;
    }
</style>

<div class="page-hero compact">
    <div>
        <h1 class="section-title"><?php echo $isStaffDraft ? 'Draft Action Slip' : 'New Action Slip'; ?></h1>
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
        <?php if (!$isStaffDraft): ?>
        <div class="col-lg-3 col-md-6 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="checkbox" id="urgent" name="urgent" value="1" class="form-check-input" <?php echo !empty($values['urgent']) ? 'checked' : ''; ?>>
                <label for="urgent" class="form-check-label fw-semibold">Urgent</label>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isStaffDraft): ?>
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
        <?php endif; ?>
        <div class="col-lg-4">
            <label for="attachment" class="form-label fw-semibold">Attachment</label>
            <input type="file" id="attachment" name="attachment" class="form-control <?php echo isset($errors['attachment']) ? 'is-invalid' : ''; ?>" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx" <?php echo $isStaffDraft ? 'required' : ''; ?>>
            <?php if (isset($errors['attachment'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['attachment']); ?></div><?php endif; ?>
        </div>
        <?php if (!$isStaffDraft): ?>
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
            <label for="staff_search" class="form-label fw-semibold">Target Staff</label>
            <div class="staff-picker <?php echo isset($errors['assigned_staff_id']) ? 'is-invalid' : ''; ?>" data-staff-picker>
                <input type="search" id="staff_search" class="form-control staff-picker-search" placeholder="Search staff" autocomplete="off" data-staff-search>
                <div class="staff-picker-list" data-staff-list>
                    <?php foreach (($data['division_staff'] ?? []) as $staff): ?>
                        <?php
                            $name = trim(($staff['firstname'] ?? '') . ' ' . (!empty($staff['middle_initial']) ? $staff['middle_initial'] . '. ' : '') . ($staff['lastname'] ?? ''));
                            $staffId = (int) $staff['id'];
                        ?>
                        <label class="staff-picker-option" data-staff-option data-staff-name="<?php echo htmlspecialchars(strtolower($name)); ?>">
                            <input type="checkbox" name="assigned_staff_ids[]" value="<?php echo $staffId; ?>" class="form-check-input m-0" <?php echo in_array($staffId, $selectedStaffIds, true) ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($name); ?></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="staff-picker-empty" data-staff-empty>No staff found.</div>
                </div>
                <div class="staff-picker-count" data-staff-count></div>
            </div>
            <div class="invalid-feedback <?php echo isset($errors['assigned_staff_id']) ? 'd-block' : ''; ?>" data-staff-error><?php echo htmlspecialchars($errors['assigned_staff_id'] ?? 'Select at least one staff member.'); ?></div>
        </div>
        <?php else: ?>
            <input type="hidden" name="receiving_level" value="Department">
            <input type="hidden" name="required_action" value="">
        <?php endif; ?>

        <div class="col-12 d-flex gap-2 justify-content-end pt-2">
            <a href="<?php echo URLROOT; ?>/actionSlips" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?php echo $isStaffDraft ? 'Create Draft' : 'Create Action Slip'; ?></button>
        </div>
    </form>
</div>

<?php if (!$isStaffDraft): ?>
<script>
(() => {
    const level = document.getElementById('receiving_level');
    const deptWrap = document.querySelector('[data-department-wrap]');
    const divisionWrap = document.querySelector('[data-division-wrap]');
    const staffWrap = document.querySelector('[data-staff-wrap]');
    const department = document.getElementById('receiving_department_id');
    const division = document.getElementById('receiving_division_id');
    const form = document.querySelector('form[action$="/actionSlips/store"]');
    const staffPicker = document.querySelector('[data-staff-picker]');
    const staffSearch = document.querySelector('[data-staff-search]');
    const staffOptions = Array.from(document.querySelectorAll('[data-staff-option]'));
    const staffChecks = Array.from(document.querySelectorAll('input[name="assigned_staff_ids[]"]'));
    const staffEmpty = document.querySelector('[data-staff-empty]');
    const staffCount = document.querySelector('[data-staff-count]');
    const staffError = document.querySelector('[data-staff-error]');
    const currentDepartmentId = '<?php echo (int) ($_SESSION['department_id'] ?? 0); ?>';

    function selectedStaffCount() {
        return staffChecks.filter((input) => input.checked).length;
    }

    function updateStaffState(showError = false) {
        const count = selectedStaffCount();
        staffCount.textContent = count === 1 ? '1 staff selected' : `${count} staff selected`;

        const isStaffTarget = level.value === 'Staff';
        const isInvalid = isStaffTarget && count === 0 && showError;
        staffPicker.classList.toggle('is-invalid', isInvalid);
        staffError.classList.toggle('d-block', isInvalid || staffError.textContent.trim() !== 'Select at least one staff member.');
    }

    function filterStaffOptions() {
        const term = staffSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        staffOptions.forEach((option) => {
            const isVisible = option.getAttribute('data-staff-name').includes(term);
            option.style.display = isVisible ? '' : 'none';
            if (isVisible) {
                visibleCount++;
            }
        });

        staffEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    function refreshTargets() {
        const target = level.value;
        deptWrap.style.display = target === 'Department' ? '' : 'none';
        divisionWrap.style.display = target === 'Division' ? '' : 'none';
        staffWrap.style.display = target === 'Staff' ? '' : 'none';
        department.required = target === 'Department';
        division.required = target === 'Division';
        updateStaffState(false);

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

    staffSearch.addEventListener('input', filterStaffOptions);
    staffChecks.forEach((input) => input.addEventListener('change', () => updateStaffState(false)));
    form.addEventListener('submit', (event) => {
        if (level.value === 'Staff' && selectedStaffCount() === 0) {
            event.preventDefault();
            updateStaffState(true);
            staffSearch.focus();
        }
    });

    level.addEventListener('change', refreshTargets);
    filterStaffOptions();
    refreshTargets();
})();
</script>
<?php endif; ?>

<?php require_once '../app/views/layout/footer.php'; ?>
