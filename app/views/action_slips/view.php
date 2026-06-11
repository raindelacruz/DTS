<?php require_once '../app/views/layout/header.php'; ?>

<?php
$slip = $data['slip'] ?? [];
$actions = $data['actions'] ?? [];
$currentDepartmentIdForSlip = (int) (($slip['current_department_id'] ?? 0) ?: ($slip['receiving_department_id'] ?? 0));
$currentDivisionIdForSlip = (int) ($slip['current_division_id'] ?? 0);
$isDivisionDraftManager = !empty($actions['finalize_draft'])
    && (($_SESSION['role'] ?? '') === 'manager')
    && $currentDivisionIdForSlip > 0
    && (int) ($_SESSION['department_id'] ?? 0) === $currentDivisionIdForSlip;
$statusStyle = [
    'Draft' => 'background:#e0f2fe; color:#075985;',
    'Released' => 'background:#fef3c7; color:#92400e;',
    'Received' => 'background:#dbeafe; color:#1e40af;',
    'Delegated' => 'background:#ede9fe; color:#5b21b6;',
    'For Action' => 'background:#fef3c7; color:#92400e;',
    'Completed' => 'background:#dcfce7; color:#166534;',
    'Returned' => 'background:#fee2e2; color:#991b1b;'
];
?>

<div class="page-hero compact">
    <div>
        <h1 class="section-title">Action Slip Details</h1>
        <div class="text-muted small mt-1"><?php echo htmlspecialchars($slip['slip_number'] ?? ''); ?></div>
    </div>
    <a href="<?php echo URLROOT; ?>/actionSlips" class="btn btn-outline-secondary">Back to List</a>
</div>

<div class="row g-4 mb-4 action-slip-main-row">
    <div class="col-lg-8">
        <div class="app-card p-4 h-100 action-slip-info-card">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4 action-slip-info-head">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Action</div>
                    <div class="fs-4 fw-bold"><?php echo htmlspecialchars($slip['required_action'] ?? ''); ?></div>
                </div>
                <span class="badge-soft" style="<?php echo $statusStyle[$slip['status'] ?? ''] ?? 'background:#f1f5f9; color:#0f172a;'; ?>"><?php echo htmlspecialchars($slip['status'] ?? ''); ?></span>
            </div>
            <div class="row g-3 action-slip-detail-grid">
                <div class="col-md-3 action-slip-detail-item"><div class="app-card p-3 h-100 action-slip-detail-tile" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Tracking Number</div><div class="fw-bold mt-2"><?php echo htmlspecialchars($slip['slip_number'] ?? ''); ?></div></div></div>
                <div class="col-md-3 action-slip-detail-item"><div class="app-card p-3 h-100 action-slip-detail-tile" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Date of Action Slip</div><div class="fw-bold mt-2"><?php echo !empty($slip['date_received']) ? htmlspecialchars(date('M d, Y', strtotime($slip['date_received']))) : '-'; ?></div></div></div>
                <div class="col-md-3 action-slip-detail-item"><div class="app-card p-3 h-100 action-slip-detail-tile" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Deadline</div><div class="fw-bold mt-2"><?php echo !empty($slip['deadline']) ? htmlspecialchars(date('M d, Y', strtotime($slip['deadline']))) : '-'; ?></div></div></div>
                <div class="col-md-3 action-slip-detail-item"><div class="app-card p-3 h-100 action-slip-detail-tile" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Urgent</div><div class="fw-bold mt-2"><?php echo !empty($slip['urgent']) ? 'Yes' : 'No'; ?></div></div></div>
                <div class="col-md-6 action-slip-detail-item action-slip-detail-wide"><div class="app-card p-3 h-100 action-slip-detail-tile" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Released To</div><div class="fw-bold mt-2"><?php echo htmlspecialchars($slip['current_division_name'] ?: ($slip['current_department_name'] ?? '-')); ?><?php echo !empty($slip['assigned_staff_name']) ? '<div class="small text-muted mt-1">' . htmlspecialchars($slip['assigned_staff_name']) . '</div>' : ''; ?></div></div></div>
                <?php if (!empty($slip['remarks'])): ?>
                    <div class="col-12 action-slip-detail-item action-slip-detail-wide">
                        <div class="app-card p-3 action-slip-detail-tile" style="background:#f8fafc; box-shadow:none;">
                            <div class="text-muted small text-uppercase fw-semibold">Instruction</div>
                            <div class="mt-2"><?php echo nl2br(htmlspecialchars($slip['remarks'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($slip['attachment'])): ?>
                <div class="mt-4 pt-3 border-top action-slip-attachment-row">
                    <a href="<?php echo URLROOT; ?>/actionSlips/attachment/<?php echo (int) $slip['id']; ?>" target="_blank" class="btn btn-outline-primary">View Attachment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4 action-slip-actions-col">
        <div class="app-card p-4 h-100 action-slip-actions-card">
            <h3 class="h5 fw-bold mb-3">Actions</h3>
            <div class="d-grid gap-2">
                <?php if (!empty($actions['receive_department'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/receiveDepartment/<?php echo (int) $slip['id']; ?>" method="POST">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-primary w-100">Receive</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['finalize_draft'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/finalizeDraft/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#f8fafc; border:1px solid #dbeafe; box-shadow:none;" data-draft-finalize-form>
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-2">Finalize Draft</div>
                        <label for="draft_date_received" class="form-label small fw-semibold">Date of Action Slip</label>
                        <input type="date" id="draft_date_received" name="date_received" class="form-control mb-2" value="<?php echo htmlspecialchars($slip['date_received'] ?? ''); ?>" required>
                        <div class="form-check mb-2">
                            <input type="checkbox" id="draft_urgent" name="urgent" value="1" class="form-check-input" <?php echo !empty($slip['urgent']) ? 'checked' : ''; ?>>
                            <label for="draft_urgent" class="form-check-label fw-semibold">Urgent</label>
                        </div>
                        <label for="draft_required_action" class="form-label small fw-semibold">Action</label>
                        <select id="draft_required_action" name="required_action" class="form-select mb-2" required>
                            <option value="">Select action</option>
                            <?php foreach (($data['action_options'] ?? []) as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (($slip['required_action'] ?? '') === $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="draft_deadline" class="form-label small fw-semibold">Deadline</label>
                        <input type="date" id="draft_deadline" name="deadline" class="form-control mb-2" value="<?php echo htmlspecialchars($slip['deadline'] ?? ''); ?>">
                        <label for="draft_receiving_level" class="form-label small fw-semibold">Forward To</label>
                        <select id="draft_receiving_level" name="receiving_level" class="form-select mb-2" data-draft-level>
                            <?php if ($isDivisionDraftManager): ?>
                                <option value="Staff">Staff</option>
                            <?php else: ?>
                                <option value="Department">Department</option>
                                <option value="Division">Division</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($isDivisionDraftManager): ?>
                            <input type="hidden" name="receiving_department_id" value="<?php echo (int) ($slip['current_department_id'] ?? $slip['receiving_department_id'] ?? 0); ?>">
                            <input type="hidden" name="receiving_division_id" value="<?php echo (int) ($_SESSION['department_id'] ?? 0); ?>">
                        <?php endif; ?>
                        <div data-draft-department-wrap>
                            <label id="draft_receiving_department_label" class="form-label small fw-semibold">Target Department</label>
                            <div class="route-multiselect mb-2" data-draft-department-multiselect>
                                <button
                                    type="button"
                                    class="form-select route-multiselect-toggle text-start"
                                    aria-labelledby="draft_receiving_department_label"
                                    aria-expanded="false"
                                    data-route-multiselect-toggle
                                >
                                    <span data-route-multiselect-summary>Select department</span>
                                </button>
                                <div class="route-multiselect-menu d-none" data-route-multiselect-menu>
                                    <input
                                        type="search"
                                        class="form-control route-search-input mb-2"
                                        placeholder="Search departments"
                                        aria-label="Search departments"
                                        data-route-multiselect-search
                                    >
                                    <div class="route-checkbox-group route-multiselect-options" role="group" aria-labelledby="draft_receiving_department_label">
                                        <?php foreach (($data['departments'] ?? []) as $department): ?>
                                            <?php if ((int) $department['id'] === (int) ($_SESSION['department_id'] ?? 0)) { continue; } ?>
                                            <?php $departmentId = (int) $department['id']; ?>
                                            <label class="route-checkbox-item" for="draft_receiving_department_<?php echo $departmentId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($department['division_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="draft_receiving_department_<?php echo $departmentId; ?>"
                                                    name="receiving_department_ids[]"
                                                    value="<?php echo $departmentId; ?>"
                                                >
                                                <span><?php echo htmlspecialchars($department['division_name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        <div class="route-empty-state d-none" data-route-empty>No departments match your search.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div data-draft-division-wrap>
                            <label id="draft_receiving_division_label" class="form-label small fw-semibold">Target Division</label>
                            <div class="route-multiselect mb-2" data-draft-division-multiselect>
                                <button
                                    type="button"
                                    class="form-select route-multiselect-toggle text-start"
                                    aria-labelledby="draft_receiving_division_label"
                                    aria-expanded="false"
                                    data-route-multiselect-toggle
                                >
                                    <span data-route-multiselect-summary>Select division</span>
                                </button>
                                <div class="route-multiselect-menu d-none" data-route-multiselect-menu>
                                    <input
                                        type="search"
                                        class="form-control route-search-input mb-2"
                                        placeholder="Search divisions"
                                        aria-label="Search divisions"
                                        data-route-multiselect-search
                                    >
                                    <div class="route-checkbox-group route-multiselect-options" role="group" aria-labelledby="draft_receiving_division_label">
                                        <?php foreach (($data['child_divisions'] ?? []) as $division): ?>
                                            <?php $divisionId = (int) $division['id']; ?>
                                            <label class="route-checkbox-item" for="draft_receiving_division_<?php echo $divisionId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($division['division_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="draft_receiving_division_<?php echo $divisionId; ?>"
                                                    name="receiving_division_ids[]"
                                                    value="<?php echo $divisionId; ?>"
                                                >
                                                <span><?php echo htmlspecialchars($division['division_name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        <div class="route-empty-state d-none" data-route-empty>No divisions match your search.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($isDivisionDraftManager): ?>
                            <div data-draft-staff-wrap>
                                <label id="draft_assigned_staff_label" class="form-label small fw-semibold">Target Staff</label>
                                <div class="route-multiselect mb-2" data-draft-staff-multiselect>
                                    <button
                                        type="button"
                                        class="form-select route-multiselect-toggle text-start"
                                        aria-labelledby="draft_assigned_staff_label"
                                        aria-expanded="false"
                                        data-route-multiselect-toggle
                                    >
                                        <span data-route-multiselect-summary>Select staff</span>
                                    </button>
                                    <div class="route-multiselect-menu d-none" data-route-multiselect-menu>
                                        <input
                                            type="search"
                                            class="form-control route-search-input mb-2"
                                            placeholder="Search staff"
                                            aria-label="Search staff"
                                            data-route-multiselect-search
                                        >
                                        <div class="route-checkbox-group route-multiselect-options" role="group" aria-labelledby="draft_assigned_staff_label">
                                            <?php foreach (($data['division_staff'] ?? []) as $staff): ?>
                                                <?php $staffId = (int) $staff['id']; ?>
                                                <?php $staffName = trim(($staff['firstname'] ?? '') . ' ' . (!empty($staff['middle_initial']) ? $staff['middle_initial'] . '. ' : '') . ($staff['lastname'] ?? '')); ?>
                                                <label class="route-checkbox-item" for="draft_assigned_staff_<?php echo $staffId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($staffName), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        id="draft_assigned_staff_<?php echo $staffId; ?>"
                                                        name="assigned_staff_ids[]"
                                                        value="<?php echo $staffId; ?>"
                                                    >
                                                    <span><?php echo htmlspecialchars($staffName); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <div class="route-empty-state d-none" data-route-empty>No staff match your search.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <label for="draft_remarks" class="form-label small fw-semibold">Instruction</label>
                        <textarea id="draft_remarks" name="remarks" class="form-control mb-2" rows="3" placeholder="Instructions or remarks"><?php echo htmlspecialchars($slip['remarks'] ?? ''); ?></textarea>
                        <button type="submit" class="btn btn-primary w-100">Create and Forward</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['route_department'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/routeDepartment/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#f8fafc; border:1px solid #dbeafe; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-2">Release to Department</div>
                        <select name="target_department_id" class="form-select mb-2" required>
                            <option value="">Select department</option>
                            <?php foreach (($data['departments'] ?? []) as $department): ?>
                                <?php if ((int) $department['id'] === (int) ($slip['current_department_id'] ?? 0)) { continue; } ?>
                                <option value="<?php echo (int) $department['id']; ?>"><?php echo htmlspecialchars($department['division_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="remarks" class="form-control mb-2" rows="3" placeholder="Instructions or remarks"></textarea>
                        <button type="submit" class="btn btn-outline-dark w-100">Release to Department</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['delegate_division'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/delegateDivision/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#f8fafc; border:1px solid #dbeafe; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-2">Delegate to Division</div>
                        <select name="division_id" class="form-select mb-2" required>
                            <option value="">Select division</option>
                            <?php foreach (($data['child_divisions'] ?? []) as $division): ?>
                                <option value="<?php echo (int) $division['id']; ?>"><?php echo htmlspecialchars($division['division_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="remarks" class="form-control mb-2" rows="3" placeholder="Instructions or remarks"></textarea>
                        <button type="submit" class="btn btn-primary w-100">Delegate to Division</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['receive_division'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/receiveDivision/<?php echo (int) $slip['id']; ?>" method="POST">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-primary w-100">Receive</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['delegate_staff'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/delegateStaff/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#f8fafc; border:1px solid #dbeafe; box-shadow:none;" data-staff-delegate-form>
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-2">Further Delegate to Staff</div>
                        <label id="delegate_staff_label" class="visually-hidden">Select staff</label>
                        <div class="route-multiselect mb-2" data-delegate-staff-multiselect data-route-required>
                            <button
                                type="button"
                                class="form-select route-multiselect-toggle text-start"
                                aria-labelledby="delegate_staff_label"
                                aria-expanded="false"
                                data-route-multiselect-toggle
                            >
                                <span data-route-multiselect-summary>Select staff</span>
                            </button>
                            <div class="route-multiselect-menu d-none" data-route-multiselect-menu>
                                <input
                                    type="search"
                                    class="form-control route-search-input mb-2"
                                    placeholder="Search staff"
                                    aria-label="Search staff"
                                    data-route-multiselect-search
                                >
                                <div class="route-checkbox-group route-multiselect-options" role="group" aria-labelledby="delegate_staff_label">
                                    <?php foreach (($data['division_staff'] ?? []) as $staff): ?>
                                        <?php $staffId = (int) $staff['id']; ?>
                                        <?php $staffName = trim(($staff['firstname'] ?? '') . ' ' . (!empty($staff['middle_initial']) ? $staff['middle_initial'] . '. ' : '') . ($staff['lastname'] ?? '')); ?>
                                        <label class="route-checkbox-item" for="delegate_staff_<?php echo $staffId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($staffName), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="delegate_staff_<?php echo $staffId; ?>"
                                                name="staff_ids[]"
                                                value="<?php echo $staffId; ?>"
                                            >
                                            <span><?php echo htmlspecialchars($staffName); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="route-empty-state d-none" data-route-empty>No staff match your search.</div>
                                </div>
                            </div>
                            <div class="invalid-feedback" data-route-required-error>Select at least one staff member.</div>
                        </div>
                        <textarea name="remarks" class="form-control mb-2" rows="3" placeholder="Instructions or remarks"></textarea>
                        <button type="submit" class="btn btn-outline-dark w-100">Further Delegate to Staff</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['start_staff'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/startStaff/<?php echo (int) $slip['id']; ?>" method="POST">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-warning w-100">Receive</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['complete_staff'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/completeStaff/<?php echo (int) $slip['id']; ?>" method="POST" enctype="multipart/form-data" class="app-card p-3" style="background:#f0fdf4; border:1px solid #bbf7d0; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <?php $isReturnedToStaff = ($slip['status'] ?? '') === 'Returned'; ?>
                        <div class="fw-bold mb-2"><?php echo $isReturnedToStaff ? 'Resubmit Completed Task' : 'Mark as Completed'; ?></div>
                        <input type="file" name="completion_attachment" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx">
                        <textarea name="remarks" class="form-control mb-2" rows="3" placeholder="<?php echo $isReturnedToStaff ? 'Revision remarks' : 'Completion remarks'; ?>"></textarea>
                        <button type="submit" class="btn btn-success w-100"><?php echo $isReturnedToStaff ? 'Resubmit' : 'Mark as Completed'; ?></button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['confirm_division'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/confirmDivision/<?php echo (int) $slip['id']; ?>" method="POST">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-success w-100">Confirm Completion</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['complete_division'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/completeDivision/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#f0fdf4; border:1px solid #bbf7d0; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <textarea name="remarks" class="form-control mb-2" rows="3" placeholder="Completion remarks"></textarea>
                        <button type="submit" class="btn btn-success w-100">Mark as Completed</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['complete_department'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/completeDepartment/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#f0fdf4; border:1px solid #bbf7d0; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <textarea name="remarks" class="form-control mb-2" rows="3" placeholder="Completion remarks"></textarea>
                        <button type="submit" class="btn btn-success w-100">Mark as Completed</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['close'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/close/<?php echo (int) $slip['id']; ?>" method="POST">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-dark w-100">Close Slip</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($actions['return'])): ?>
                    <form action="<?php echo URLROOT; ?>/actionSlips/returnSlip/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#fff7ed; border:1px solid #fed7aa; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-2"><?php echo !empty($actions['return_staff_completion']) ? 'Return to Staff' : 'Return'; ?></div>
                        <?php if (!empty($actions['return_staff_completion'])): ?>
                            <select name="return_reason" class="form-select mb-2" required>
                                <option value="">Select reason</option>
                                <option value="Did not pass standards">Did not pass standards</option>
                                <option value="Change of instruction">Change of instruction</option>
                                <option value="Other">Other</option>
                            </select>
                        <?php endif; ?>
                        <textarea name="remarks" class="form-control mb-2" rows="3" placeholder="Reason and revised instruction" required></textarea>
                        <button type="submit" class="btn btn-outline-danger w-100"><?php echo !empty($actions['return_staff_completion']) ? 'Return to Staff' : 'Return'; ?></button>
                    </form>
                <?php endif; ?>

                <?php if (empty(array_filter($actions))): ?>
                    <div class="text-muted small">No action is available for your role at this status.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="app-card p-4 mb-4 action-slip-history-card">
    <h3 class="h5 fw-bold mb-3">Action History</h3>
    <?php if (!empty($data['events'])): ?>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Action</th><th>Actor</th><th>Target</th><th>Status</th><th>Date</th><th>Remarks</th></tr></thead>
                <tbody>
                    <?php foreach ($data['events'] as $event): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars($event['action']); ?></td>
                            <td><?php echo htmlspecialchars($event['actor_name']); ?><div class="small text-muted"><?php echo htmlspecialchars($event['actor_department_name']); ?></div></td>
                            <td>
                                <?php echo htmlspecialchars($event['to_user_name'] ?: ($event['to_department_name'] ?: '-')); ?>
                                <?php if (!empty($event['attachment'])): ?>
                                    <div><a href="<?php echo URLROOT; ?>/actionSlips/eventAttachment/<?php echo (int) $event['id']; ?>" target="_blank" class="small">Attachment</a></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($event['new_status'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($event['created_at']))); ?></td>
                            <td class="text-muted"><?php echo nl2br(htmlspecialchars($event['remarks'] ?: '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-muted">No action history recorded.</div>
    <?php endif; ?>
</div>

<style>
    .route-multiselect {
        position: relative;
    }
    [data-draft-finalize-form] {
        position: relative;
        z-index: 30;
    }
    [data-staff-delegate-form] {
        position: relative;
        z-index: 30;
    }
    [data-draft-finalize-form]:has(.route-multiselect-menu:not(.d-none)) {
        z-index: 1400;
    }
    [data-staff-delegate-form]:has(.route-multiselect-menu:not(.d-none)) {
        z-index: 1400;
    }
    .route-multiselect-toggle {
        white-space: normal;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .route-multiselect.is-open {
        z-index: 1450;
    }
    .route-multiselect-menu {
        position: absolute;
        z-index: 1450;
        top: calc(100% + 0.35rem);
        left: 0;
        right: 0;
        padding: 0.65rem;
        border: 1px solid #dbe4ee;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
    }
    .route-multiselect-options {
        max-height: 13rem;
        padding: 0.45rem;
        overflow-y: auto;
    }
    .action-slip-main-row,
    .action-slip-actions-col,
    .action-slip-actions-card {
        position: relative;
        z-index: 1;
    }
    .action-slip-history-card {
        position: relative;
        z-index: 1;
    }
    @media (max-width: 575.98px) {
        .action-slip-info-card {
            padding: 0.72rem !important;
        }
        .action-slip-info-head {
            margin-bottom: 0.65rem !important;
            gap: 0.55rem !important;
        }
        .action-slip-info-head .fs-4 {
            font-size: 1.05rem !important;
            line-height: 1.25;
        }
        .action-slip-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem;
        }
        .action-slip-detail-item {
            width: auto;
            max-width: none;
            padding: 0 !important;
        }
        .action-slip-detail-wide {
            grid-column: 1 / -1;
        }
        .action-slip-detail-tile {
            min-height: 0;
            padding: 0.62rem !important;
            border-radius: 13px;
        }
        .action-slip-detail-tile .small {
            font-size: 0.66rem !important;
            letter-spacing: 0.02em;
        }
        .action-slip-detail-tile .fw-bold {
            margin-top: 0.28rem !important;
            font-size: 0.88rem;
            line-height: 1.28;
        }
        .action-slip-attachment-row {
            margin-top: 0.65rem !important;
            padding-top: 0.65rem !important;
        }
    }
</style>

<script>
(() => {
    const pickers = [];

    const draftForm = document.querySelector('[data-draft-finalize-form]');
    const delegateStaffForm = document.querySelector('[data-staff-delegate-form]');

    function createRouteMultiselect(container, emptySummary, pluralSummary) {
        if (!container) {
            return null;
        }

        const toggle = container.querySelector('[data-route-multiselect-toggle]');
        const menu = container.querySelector('[data-route-multiselect-menu]');
        const search = container.querySelector('[data-route-multiselect-search]');
        const summary = container.querySelector('[data-route-multiselect-summary]');
        const checks = Array.prototype.slice.call(container.querySelectorAll('input[type="checkbox"]'));
        const items = Array.prototype.slice.call(container.querySelectorAll('.route-checkbox-item'));
        const empty = container.querySelector('[data-route-empty]');
        const error = container.querySelector('[data-route-required-error]');

        const selectedLabels = () => checks
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => {
                const label = checkbox.closest('.route-checkbox-item');
                const text = label ? label.textContent : '';
                return text.trim();
            });

        const updateSummary = () => {
            if (!summary) {
                return;
            }

            const labels = selectedLabels();
            if (labels.length === 0) {
                summary.textContent = emptySummary;
            } else if (labels.length === 1) {
                summary.textContent = labels[0];
            } else {
                summary.textContent = labels.length + ' ' + pluralSummary;
            }
        };

        const filterOptions = () => {
            if (!search) {
                return;
            }

            const query = search.value.trim().toLowerCase();
            let visibleCount = 0;

            items.forEach((item) => {
                const label = item.getAttribute('data-route-label') || '';
                const matches = query === '' || label.indexOf(query) !== -1;
                item.classList.toggle('d-none', !matches);
                if (matches) {
                    visibleCount++;
                }
            });

            if (empty) {
                empty.classList.toggle('d-none', visibleCount !== 0);
            }
        };

        const setOpen = (isOpen) => {
            if (!menu || !toggle) {
                return;
            }

            container.classList.toggle('is-open', isOpen);
            menu.classList.toggle('d-none', !isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen && search) {
                search.focus();
                filterOptions();
            }
        };

        if (toggle) {
            toggle.addEventListener('click', () => {
                setOpen(toggle.getAttribute('aria-expanded') !== 'true');
            });
        }
        if (search) {
            search.addEventListener('input', filterOptions);
        }
        checks.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                updateSummary();
                container.classList.remove('is-invalid');
                if (error) {
                    error.classList.remove('d-block');
                }
            });
        });

        updateSummary();
        filterOptions();

        const picker = {
            container,
            checks,
            setOpen,
            setDisabled(isDisabled) {
                checks.forEach((checkbox) => {
                    checkbox.disabled = isDisabled;
                });
                if (isDisabled) {
                    setOpen(false);
                }
            },
            isValid() {
                return checks.some((checkbox) => checkbox.checked);
            },
            showRequiredError() {
                container.classList.add('is-invalid');
                if (error) {
                    error.classList.add('d-block');
                }
                setOpen(true);
                if (search) {
                    search.focus();
                }
            }
        };

        pickers.push(picker);
        return picker;
    }

    if (draftForm) {
        const level = draftForm.querySelector('[data-draft-level]');
        const departmentWrap = draftForm.querySelector('[data-draft-department-wrap]');
        const divisionWrap = draftForm.querySelector('[data-draft-division-wrap]');
        const staffWrap = draftForm.querySelector('[data-draft-staff-wrap]');
        const departmentPicker = createRouteMultiselect(
            draftForm.querySelector('[data-draft-department-multiselect]'),
            'Select department',
            'departments selected'
        );
        const divisionPicker = createRouteMultiselect(
            draftForm.querySelector('[data-draft-division-multiselect]'),
            'Select division',
            'divisions selected'
        );
        const staffPicker = createRouteMultiselect(
            draftForm.querySelector('[data-draft-staff-multiselect]'),
            'Select staff',
            'staff selected'
        );

        function refreshDraftTargets() {
            const target = level.value;
            departmentWrap.style.display = target === 'Department' ? '' : 'none';
            divisionWrap.style.display = target === 'Division' ? '' : 'none';
            if (staffWrap) {
                staffWrap.style.display = target === 'Staff' ? '' : 'none';
            }
            if (departmentPicker) {
                departmentPicker.setDisabled(target !== 'Department');
            }
            if (divisionPicker) {
                divisionPicker.setDisabled(target !== 'Division');
            }
            if (staffPicker) {
                staffPicker.setDisabled(target !== 'Staff');
            }
        }

        level.addEventListener('change', refreshDraftTargets);
        refreshDraftTargets();
    }

    if (delegateStaffForm) {
        const delegateStaffPicker = createRouteMultiselect(
            delegateStaffForm.querySelector('[data-delegate-staff-multiselect]'),
            'Select staff',
            'staff selected'
        );

        delegateStaffForm.addEventListener('submit', (event) => {
            if (delegateStaffPicker && !delegateStaffPicker.isValid()) {
                event.preventDefault();
                delegateStaffPicker.showRequiredError();
            }
        });
    }

    document.addEventListener('click', (event) => {
        pickers.forEach((picker) => {
            if (!picker.container.contains(event.target)) {
                picker.setOpen(false);
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            pickers.forEach((picker) => picker.setOpen(false));
        }
    });
})();
</script>

<?php require_once '../app/views/layout/footer.php'; ?>
