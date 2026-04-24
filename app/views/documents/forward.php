<?php require_once '../app/views/layout/header.php'; ?>
<?php
$otherDepartments = [];
$divisionDepartments = [];

foreach ($departments as $dept) {
    if (is_null($dept['parent_id'])) {
        $otherDepartments[] = $dept;
    } else {
        $divisionDepartments[] = $dept;
    }
}

$sortDivisionFirst = function ($a, $b) {
    $aName = (string) ($a['division_name'] ?? '');
    $bName = (string) ($b['division_name'] ?? '');
    $aIsDivision = stripos($aName, 'division') !== false ? 0 : 1;
    $bIsDivision = stripos($bName, 'division') !== false ? 0 : 1;

    if ($aIsDivision !== $bIsDivision) {
        return $aIsDivision <=> $bIsDivision;
    }

    return strcasecmp($aName, $bName);
};

usort($otherDepartments, $sortDivisionFirst);
usort($divisionDepartments, $sortDivisionFirst);
?>

<div class="page-hero compact">
    <div><h1 class="section-title" style="font-size:1.7rem;">Forward Document</h1></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Select one or more valid target departments, use the search box to find offices faster, then give a clear instruction and deadline. Mark the item urgent only when the receiving office needs immediate attention.</p>
</div>

<div class="app-card p-4 p-lg-5">
    <?php if (!empty($formMessage)): ?>
        <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($formMessage); ?></div>
    <?php endif; ?>
    <form method="POST">
        <?php echo csrfInput(); ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <label class="form-label fw-semibold">Forward To</label>
                <div class="route-search-wrap">
                    <input
                        type="search"
                        class="form-control route-search-input"
                        placeholder="Search forward targets"
                        aria-label="Search forward targets"
                        data-route-search-target="forward-department-list"
                    >
                </div>
                <div class="route-checkbox-group" id="forward-department-list" role="group" aria-label="Forward To">
                    <div class="route-group-heading">Your Division</div>
                    <?php foreach ($divisionDepartments as $dept): ?>
                        <?php $deptId = (int) $dept['id']; ?>
                        <label class="route-checkbox-item" for="forward_department_<?php echo $deptId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($dept['division_name']), ENT_QUOTES, 'UTF-8'); ?>">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="forward_department_<?php echo $deptId; ?>"
                                name="department_ids[]"
                                value="<?php echo $deptId; ?>"
                                <?php echo in_array($deptId, $formValues['department_ids'] ?? [], true) ? 'checked' : ''; ?>
                            >
                            <span><?php echo htmlspecialchars($dept['division_name']); ?></span>
                        </label>
                    <?php endforeach; ?>

                    <div class="route-group-heading">Other Departments</div>
                    <?php foreach ($otherDepartments as $dept): ?>
                        <?php $deptId = (int) $dept['id']; ?>
                        <label class="route-checkbox-item" for="forward_department_<?php echo $deptId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($dept['division_name']), ENT_QUOTES, 'UTF-8'); ?>">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="forward_department_<?php echo $deptId; ?>"
                                name="department_ids[]"
                                value="<?php echo $deptId; ?>"
                                <?php echo in_array($deptId, $formValues['department_ids'] ?? [], true) ? 'checked' : ''; ?>
                            >
                            <span><?php echo htmlspecialchars($dept['division_name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="route-empty-state d-none" data-route-empty>No departments match your search.</div>
                </div>
                <div class="form-text">Select one or more division/departments for this action slip.</div>
                <?php if (!empty($errors['department_ids'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['department_ids']); ?></div><?php endif; ?>
            </div>
            <div class="col-lg-7">
                <div class="app-card p-3 mb-3" style="background:#f8fafc; border:1px dashed #cbd5e1; box-shadow:none;">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" value="1" id="urgent" name="urgent" <?php echo !empty($formValues['urgent']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="urgent">Urgent</label>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Action Type</label>
                        <select name="action_type" class="form-select <?php echo !empty($errors['action_type']) ? 'is-invalid' : ''; ?>" required>
                            <option value="">Select action</option>
                            <option value="For initial/signature" <?php echo ($formValues['action_type'] ?? '') === 'For initial/signature' ? 'selected' : ''; ?>>For initial/signature</option>
                            <option value="For meeting attendance" <?php echo ($formValues['action_type'] ?? '') === 'For meeting attendance' ? 'selected' : ''; ?>>For meeting attendance</option>
                            <option value="For coordination" <?php echo ($formValues['action_type'] ?? '') === 'For coordination' ? 'selected' : ''; ?>>For coordination</option>
                            <option value="For review/comments" <?php echo ($formValues['action_type'] ?? '') === 'For review/comments' ? 'selected' : ''; ?>>For review/comments</option>
                            <option value="For reference/filing" <?php echo ($formValues['action_type'] ?? '') === 'For reference/filing' ? 'selected' : ''; ?>>For reference/filing</option>
                            <option value="For appropriate action" <?php echo ($formValues['action_type'] ?? '') === 'For appropriate action' ? 'selected' : ''; ?>>For appropriate action</option>
                        </select>
                        <?php if (!empty($errors['action_type'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['action_type']); ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Deadline Date</label>
                        <input type="date" name="deadline_date" class="form-control <?php echo !empty($errors['deadline_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($formValues['deadline_date'] ?? ''); ?>" required>
                        <?php if (!empty($errors['deadline_date'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['deadline_date']); ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Instruction</label>
                        <textarea name="instruction" class="form-control <?php echo !empty($errors['instruction']) ? 'is-invalid' : ''; ?>" rows="6" required><?php echo htmlspecialchars($formValues['instruction'] ?? ''); ?></textarea>
                        <?php if (!empty($errors['instruction'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['instruction']); ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-4 pt-2">
            <button type="submit" class="btn btn-warning">Forward Document for Action</button>
            <a href="<?php echo URLROOT; ?>/documents/show/<?php echo $document['id']; ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.querySelector('[data-route-search-target="forward-department-list"]');
    var group = document.getElementById('forward-department-list');

    if (!input || !group) {
        return;
    }

    var items = Array.prototype.slice.call(group.querySelectorAll('.route-checkbox-item'));
    var headings = Array.prototype.slice.call(group.querySelectorAll('.route-group-heading'));
    var emptyState = group.querySelector('[data-route-empty]');

    var refreshGroups = function () {
        headings.forEach(function (heading) {
            var next = heading.nextElementSibling;
            var hasVisibleItems = false;

            while (next && !next.classList.contains('route-group-heading') && !next.hasAttribute('data-route-empty')) {
                if (next.classList.contains('route-checkbox-item') && !next.classList.contains('d-none')) {
                    hasVisibleItems = true;
                }
                next = next.nextElementSibling;
            }

            heading.classList.toggle('d-none', !hasVisibleItems);
        });
    };

    var applyFilter = function () {
        var query = input.value.trim().toLowerCase();
        var visibleCount = 0;

        items.forEach(function (item) {
            var label = item.getAttribute('data-route-label') || '';
            var matches = query === '' || label.indexOf(query) !== -1;
            item.classList.toggle('d-none', !matches);
            if (matches) {
                visibleCount++;
            }
        });

        refreshGroups();

        if (emptyState) {
            emptyState.classList.toggle('d-none', visibleCount !== 0);
        }
    };

    input.addEventListener('input', applyFilter);
    applyFilter();
});
</script>

<?php require_once '../app/views/layout/footer.php'; ?>
