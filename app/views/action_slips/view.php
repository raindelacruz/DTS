<?php require_once '../app/views/layout/header.php'; ?>

<?php
$slip = $data['slip'] ?? [];
$actions = $data['actions'] ?? [];
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

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="app-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Action</div>
                    <div class="fs-4 fw-bold"><?php echo htmlspecialchars($slip['required_action'] ?? ''); ?></div>
                </div>
                <span class="badge-soft" style="<?php echo $statusStyle[$slip['status'] ?? ''] ?? 'background:#f1f5f9; color:#0f172a;'; ?>"><?php echo htmlspecialchars($slip['status'] ?? ''); ?></span>
            </div>
            <div class="row g-3">
                <div class="col-md-3"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Tracking Number</div><div class="fw-bold mt-2"><?php echo htmlspecialchars($slip['slip_number'] ?? ''); ?></div></div></div>
                <div class="col-md-3"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Date of Action Slip</div><div class="fw-bold mt-2"><?php echo !empty($slip['date_received']) ? htmlspecialchars(date('M d, Y', strtotime($slip['date_received']))) : '-'; ?></div></div></div>
                <div class="col-md-3"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Deadline</div><div class="fw-bold mt-2"><?php echo !empty($slip['deadline']) ? htmlspecialchars(date('M d, Y', strtotime($slip['deadline']))) : '-'; ?></div></div></div>
                <div class="col-md-3"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Urgent</div><div class="fw-bold mt-2"><?php echo !empty($slip['urgent']) ? 'Yes' : 'No'; ?></div></div></div>
                <div class="col-md-6"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Released To</div><div class="fw-bold mt-2"><?php echo htmlspecialchars($slip['current_division_name'] ?: ($slip['current_department_name'] ?? '-')); ?><?php echo !empty($slip['assigned_staff_name']) ? '<div class="small text-muted mt-1">' . htmlspecialchars($slip['assigned_staff_name']) . '</div>' : ''; ?></div></div></div>
                <?php if (!empty($slip['remarks'])): ?>
                    <div class="col-12">
                        <div class="app-card p-3" style="background:#f8fafc; box-shadow:none;">
                            <div class="text-muted small text-uppercase fw-semibold">Instruction</div>
                            <div class="mt-2"><?php echo nl2br(htmlspecialchars($slip['remarks'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($slip['attachment'])): ?>
                <div class="mt-4 pt-3 border-top">
                    <a href="<?php echo URLROOT; ?>/actionSlips/attachment/<?php echo (int) $slip['id']; ?>" target="_blank" class="btn btn-outline-primary">View Attachment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="app-card p-4 h-100">
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
                            <option value="Department">Department</option>
                            <option value="Division">Division</option>
                        </select>
                        <div data-draft-department-wrap>
                            <label for="draft_receiving_department_id" class="form-label small fw-semibold">Target Department</label>
                            <select id="draft_receiving_department_id" name="receiving_department_id" class="form-select mb-2">
                                <option value="">Select department</option>
                                <?php foreach (($data['departments'] ?? []) as $department): ?>
                                    <?php if ((int) $department['id'] === (int) ($_SESSION['department_id'] ?? 0)) { continue; } ?>
                                    <option value="<?php echo (int) $department['id']; ?>"><?php echo htmlspecialchars($department['division_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div data-draft-division-wrap>
                            <label for="draft_receiving_division_id" class="form-label small fw-semibold">Target Division</label>
                            <select id="draft_receiving_division_id" name="receiving_division_id" class="form-select mb-2">
                                <option value="">Select division</option>
                                <?php foreach (($data['child_divisions'] ?? []) as $division): ?>
                                    <option value="<?php echo (int) $division['id']; ?>"><?php echo htmlspecialchars($division['division_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                    <form action="<?php echo URLROOT; ?>/actionSlips/delegateStaff/<?php echo (int) $slip['id']; ?>" method="POST" class="app-card p-3" style="background:#f8fafc; border:1px solid #dbeafe; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-2">Further Delegate to Staff</div>
                        <select name="staff_id" class="form-select mb-2" required>
                            <option value="">Select staff</option>
                            <?php foreach (($data['division_staff'] ?? []) as $staff): ?>
                                <?php $staffName = trim(($staff['firstname'] ?? '') . ' ' . (!empty($staff['middle_initial']) ? $staff['middle_initial'] . '. ' : '') . ($staff['lastname'] ?? '')); ?>
                                <option value="<?php echo (int) $staff['id']; ?>"><?php echo htmlspecialchars($staffName); ?></option>
                            <?php endforeach; ?>
                        </select>
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

<div class="app-card p-4 mb-4">
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

<script>
(() => {
    const form = document.querySelector('[data-draft-finalize-form]');
    if (!form) {
        return;
    }

    const level = form.querySelector('[data-draft-level]');
    const departmentWrap = form.querySelector('[data-draft-department-wrap]');
    const divisionWrap = form.querySelector('[data-draft-division-wrap]');
    const department = document.getElementById('draft_receiving_department_id');
    const division = document.getElementById('draft_receiving_division_id');

    function refreshDraftTargets() {
        const target = level.value;
        departmentWrap.style.display = target === 'Department' ? '' : 'none';
        divisionWrap.style.display = target === 'Division' ? '' : 'none';
        department.required = target === 'Department';
        division.required = target === 'Division';
    }

    level.addEventListener('change', refreshDraftTargets);
    refreshDraftTargets();
})();
</script>

<?php require_once '../app/views/layout/footer.php'; ?>
