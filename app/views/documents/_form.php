<?php
$documentData = $documentData ?? [];
$selectedThruDepartmentId = isset($selectedThruDepartmentId) ? (int) $selectedThruDepartmentId : 0;
$selectedToDepartmentIds = array_map('intval', $selectedToDepartmentIds ?? []);
$selectedCcDepartmentIds = array_map('intval', $selectedCcDepartmentIds ?? []);
$submitLabel = $submitLabel ?? 'Save Document';
$formAction = $formAction ?? '';
$cancelUrl = $cancelUrl ?? (URLROOT . '/documents');
$showAttachmentHint = $showAttachmentHint ?? false;
$errors = $errors ?? [];
$formMessage = $formMessage ?? '';
?>

<form action="<?php echo $formAction; ?>" method="POST" enctype="multipart/form-data">
    <?php echo csrfInput(); ?>
    <?php if ($formMessage !== ''): ?>
        <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($formMessage); ?></div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="title">Title</label>
            <input
                type="text"
                id="title"
                name="title"
                class="form-control <?php echo !empty($errors['title']) ? 'is-invalid' : ''; ?>"
                value="<?php echo htmlspecialchars($documentData['title'] ?? ''); ?>"
                required
            >
            <?php if (!empty($errors['title'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['title']); ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="type">Type</label>
            <input
                type="text"
                id="type"
                name="type"
                class="form-control <?php echo !empty($errors['type']) ? 'is-invalid' : ''; ?>"
                value="<?php echo htmlspecialchars($documentData['type'] ?? ''); ?>"
                required
            >
            <?php if (!empty($errors['type'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['type']); ?></div><?php endif; ?>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold" for="particulars">Particulars</label>
            <textarea
                id="particulars"
                name="particulars"
                rows="4"
                class="form-control <?php echo !empty($errors['particulars']) ? 'is-invalid' : ''; ?>"
                placeholder="Add the document particulars or short context."
            ><?php echo htmlspecialchars($documentData['particulars'] ?? ''); ?></textarea>
            <div class="form-text">Use this textbox for key details, context, or a short summary of the document.</div>
            <?php if (!empty($errors['particulars'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['particulars']); ?></div><?php endif; ?>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold" for="reference_document_id">Document Reference</label>
            <select
                name="reference_document_id"
                id="reference_document_id"
                class="form-select <?php echo !empty($errors['reference_document_id']) ? 'is-invalid' : ''; ?>"
            >
                <option value="">None</option>
                <?php foreach (($referenceDocuments ?? []) as $referenceDocument): ?>
                    <?php $referenceDocumentId = (int) ($referenceDocument['id'] ?? 0); ?>
                    <option
                        value="<?php echo $referenceDocumentId; ?>"
                        <?php echo (int) ($documentData['reference_document_id'] ?? 0) === $referenceDocumentId ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars(($referenceDocument['prefix'] ?? 'Document') . ' - ' . ($referenceDocument['title'] ?? 'Untitled')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Optional. Link this document to an earlier related or replied-to document.</div>
            <?php if (!empty($errors['reference_document_id'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['reference_document_id']); ?></div><?php endif; ?>
        </div>
        <div class="col-lg-4">
            <label class="form-label fw-semibold" for="thru_department_id">THRU Department</label>
            <select name="thru_department_id" id="thru_department_id" class="form-select">
                <option value="">None</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>" <?php echo $selectedThruDepartmentId === (int) $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['division_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Choose one THRU department only when a clearance step is needed.</div>
        </div>
        <div class="col-lg-4">
            <label class="form-label fw-semibold">TO Departments</label>
            <div class="route-search-wrap">
                <input
                    type="search"
                    class="form-control route-search-input"
                    placeholder="Search TO departments"
                    aria-label="Search TO departments"
                    data-route-search-target="to-department-list"
                >
            </div>
            <div class="route-checkbox-group" id="to-department-list" role="group" aria-label="TO Departments">
                <?php foreach ($departments as $dept): ?>
                    <?php $deptId = (int) $dept['id']; ?>
                    <label class="route-checkbox-item" for="to_department_<?php echo $deptId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($dept['division_name']), ENT_QUOTES, 'UTF-8'); ?>">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="to_department_<?php echo $deptId; ?>"
                            name="to_department_ids[]"
                            value="<?php echo $deptId; ?>"
                            <?php echo in_array($deptId, $selectedToDepartmentIds, true) ? 'checked' : ''; ?>
                        >
                        <span><?php echo htmlspecialchars($dept['division_name']); ?></span>
                    </label>
                <?php endforeach; ?>
                <div class="route-empty-state d-none" data-route-empty>No departments match your search.</div>
            </div>
            <div class="form-text">Select one or more main recipient departments.</div>
            <?php if (!empty($errors['to_department_ids'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['to_department_ids']); ?></div><?php endif; ?>
        </div>
        <div class="col-lg-4">
            <label class="form-label fw-semibold">CC Departments</label>
            <div class="route-search-wrap">
                <input
                    type="search"
                    class="form-control route-search-input"
                    placeholder="Search CC departments"
                    aria-label="Search CC departments"
                    data-route-search-target="cc-department-list"
                >
            </div>
            <div class="route-checkbox-group" id="cc-department-list" role="group" aria-label="CC Departments">
                <?php foreach ($departments as $dept): ?>
                    <?php $deptId = (int) $dept['id']; ?>
                    <label class="route-checkbox-item" for="cc_department_<?php echo $deptId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($dept['division_name']), ENT_QUOTES, 'UTF-8'); ?>">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="cc_department_<?php echo $deptId; ?>"
                            name="cc_department_ids[]"
                            value="<?php echo $deptId; ?>"
                            <?php echo in_array($deptId, $selectedCcDepartmentIds, true) ? 'checked' : ''; ?>
                        >
                        <span><?php echo htmlspecialchars($dept['division_name']); ?></span>
                    </label>
                <?php endforeach; ?>
                <div class="route-empty-state d-none" data-route-empty>No departments match your search.</div>
            </div>
            <div class="form-text">Use CC for offices that only need a copy or visibility.</div>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold" for="attachment">Attachment</label>
            <input type="file" id="attachment" name="attachment" class="form-control <?php echo !empty($errors['attachment']) ? 'is-invalid' : ''; ?>" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/jpeg,image/png,image/gif,image/webp">
            <?php if ($showAttachmentHint): ?>
                <div class="form-text">Leave this blank to keep the current file attached.</div>
            <?php else: ?>
                <div class="form-text">Accepted formats: PDF, JPG, PNG, GIF, and WEBP.</div>
            <?php endif; ?>
            <?php if (!empty($errors['attachment'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['attachment']); ?></div><?php endif; ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-3 mt-4 pt-2">
        <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($submitLabel); ?></button>
        <a href="<?php echo $cancelUrl; ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.route-search-input').forEach(function (input) {
        var targetId = input.getAttribute('data-route-search-target');
        var group = targetId ? document.getElementById(targetId) : null;

        if (!group) {
            return;
        }

        var items = Array.prototype.slice.call(group.querySelectorAll('.route-checkbox-item'));
        var emptyState = group.querySelector('[data-route-empty]');

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

            if (emptyState) {
                emptyState.classList.toggle('d-none', visibleCount !== 0);
            }
        };

        input.addEventListener('input', applyFilter);
        applyFilter();
    });
});
</script>
