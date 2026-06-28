<?php require_once APPROOT . '/views/layout/header.php'; ?>

<?php
$routeType = $routeRole['route_type'] ?? null;
$routeCleared = isset($routeRole['is_cleared']) ? ((int) $routeRole['is_cleared'] === 1) : false;
$statusClasses = [
    'Draft' => 'background:#e2e8f0; color:#334155;',
    'Released' => 'background:#fef3c7; color:#92400e;',
    'Received' => 'background:#dcfce7; color:#166534;',
    'Returned' => 'background:#fee2e2; color:#991b1b;',
    'Cancelled' => 'background:#fee2e2; color:#991b1b;',
    'Re-released' => 'background:#dbeafe; color:#1e40af;'
];
$receivableStatuses = ['Released', 'Re-released'];

$renderTimelineRemarks = function ($log) use ($document) {
    $remarks = (string) ($log['remarks'] ?? '');
    $action = (string) ($log['action'] ?? '');

    if ($action === 'Internal Assignment Completed' && preg_match('/(^|\R)Attachment:\s*(.+)$/', $remarks, $matches, PREG_OFFSET_CAPTURE)) {
        $prefix = substr($remarks, 0, $matches[0][1]);
        $filename = trim($matches[2][0]);
        $attachmentUrl = URLROOT . '/documents/internalAssignmentAttachment/' . (int) $document['id'] . '/' . rawurlencode($filename);
        $prefixHtml = nl2br(htmlspecialchars(rtrim($prefix)));
        $separator = trim($prefix) !== '' ? '<br>' : '';

        return $prefixHtml
            . $separator
            . '<a href="' . htmlspecialchars($attachmentUrl) . '" target="_blank">Attachment</a>';
    }

    return nl2br(htmlspecialchars($remarks));
};
?>

<div class="page-hero">
    <div><h1 class="section-title">Document Details</h1></div>
    <div class="d-flex gap-2 flex-wrap"><a href="<?php echo URLROOT; ?>/documents" class="btn btn-outline-secondary">Back to Documents</a></div>
</div>

<div class="instruction-card">
    <h3>Quick Guide</h3>
    <p>Read the status, routing, and timeline before taking action. The buttons in the <strong>Actions</strong> panel appear only when your current role and department are allowed to release, receive, clear, note, or forward this document.</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="app-card p-4 h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Prefix</div>
                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($document['prefix']); ?></div>
                    <div class="mt-2 fs-4 fw-bold"><?php echo htmlspecialchars($document['title']); ?></div>
                </div>
                <span class="badge-soft" style="<?php echo $statusClasses[$document['status']] ?? 'background:#e5e7eb; color:#111827;'; ?>"><?php echo htmlspecialchars($document['status']); ?></span>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Type</div><div class="fw-bold mt-2"><?php echo htmlspecialchars($document['type'] ?? 'N/A'); ?></div></div></div>
                <div class="col-md-4"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Created At</div><div class="fw-bold mt-2"><?php echo !empty($document['created_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime($document['created_at']))) : 'N/A'; ?></div></div></div>
                <div class="col-md-4"><div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;"><div class="text-muted small text-uppercase fw-semibold">Released At</div><div class="fw-bold mt-2"><?php echo !empty($document['released_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime($document['released_at']))) : '-'; ?></div></div></div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-8">
                    <div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;">
                        <div class="text-muted small text-uppercase fw-semibold">Particulars</div>
                        <div class="mt-2 text-body-secondary"><?php echo !empty(trim((string) ($document['particulars'] ?? ''))) ? nl2br(htmlspecialchars($document['particulars'])) : 'No particulars provided.'; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="app-card p-3 h-100" style="background:#f8fafc; box-shadow:none;">
                        <div class="text-muted small text-uppercase fw-semibold">Document Reference</div>
                        <div class="fw-bold mt-2">
                            <?php if (!empty($referencedDocument)): ?>
                                <a href="<?php echo URLROOT; ?>/documents/show/<?php echo (int) $referencedDocument['id']; ?>">
                                    <?php echo htmlspecialchars(($referencedDocument['prefix'] ?? 'Document') . ' - ' . ($referencedDocument['title'] ?? 'Untitled')); ?>
                                </a>
                            <?php else: ?>
                                None
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($document['attachment'])): ?>
                <div class="mt-4 pt-3 border-top"><a href="<?php echo URLROOT; ?>/documents/attachment/<?php echo $document['id']; ?>" target="_blank" class="btn btn-outline-primary">View Attachment</a></div>
            <?php endif; ?>
            <?php if (!empty($openReturn)): ?>
                <div class="mt-4 p-3 rounded-3" style="background:#fff1f2; border:1px solid #fecdd3;">
                    <div class="fw-bold text-danger mb-1">Returned due to attachment issue</div>
                    <div class="small text-muted mb-2">
                        Returned by <?php echo htmlspecialchars($openReturn['returned_by_name']); ?>
                        from <?php echo htmlspecialchars($openReturn['returned_department_name']); ?>
                        on <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($openReturn['returned_at']))); ?>
                    </div>
                    <div><span class="fw-semibold">Reason:</span> <?php echo htmlspecialchars($openReturn['return_reason']); ?></div>
                    <?php if (!empty($openReturn['attachment_issue'])): ?>
                        <div><span class="fw-semibold">Issue:</span> <?php echo htmlspecialchars($openReturn['attachment_issue']); ?></div>
                    <?php endif; ?>
                    <div class="mt-2 text-body-secondary"><?php echo nl2br(htmlspecialchars($openReturn['remarks'])); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($internalAssignment)): ?>
                <div class="mt-4 p-3 rounded-3" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                    <div class="fw-bold mb-1">Internal Staff Assignment</div>
                    <div class="small text-muted mb-2">
                        Assigned to <?php echo htmlspecialchars($internalAssignment['assigned_to_name']); ?>
                        by <?php echo htmlspecialchars($internalAssignment['assigned_by_name']); ?>
                        on <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($internalAssignment['assigned_at']))); ?>
                    </div>
                    <div><span class="fw-semibold">Status:</span> <?php echo htmlspecialchars($internalAssignment['status']); ?></div>
                    <?php if (!empty($internalAssignment['completed_at'])): ?>
                        <div><span class="fw-semibold">Completed:</span> <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($internalAssignment['completed_at']))); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($internalAssignment['completion_attachment'])): ?>
                        <div class="mt-2">
                            <a href="<?php echo URLROOT; ?>/documents/internalAssignmentAttachment/<?php echo (int) $document['id']; ?>" target="_blank" class="btn btn-sm btn-outline-success">View Completion Attachment</a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($internalAssignment['return_remarks'])): ?>
                        <div class="mt-2"><span class="fw-semibold">Return remarks:</span> <?php echo nl2br(htmlspecialchars($internalAssignment['return_remarks'])); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($internalAssignment['instructions'])): ?>
                        <div class="mt-2 text-body-secondary"><?php echo nl2br(htmlspecialchars($internalAssignment['instructions'])); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="app-card p-4 h-100">
            <h3 class="h5 fw-bold mb-3">Actions</h3>
            <div class="d-grid gap-2">
                <?php if (!empty($recipientActionDetails)): ?>
                    <div class="app-card p-3 mb-2" style="background:#fffaf0; border:1px solid #f1d58a; box-shadow:none;">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <div class="fw-bold">Action Slip</div>
                                <?php if (($recipientActionDetails['context'] ?? 'incoming') === 'outgoing' && !empty($recipientActionDetails['to_department_name'])): ?>
                                    <div class="text-muted small">Forwarded to <?php echo htmlspecialchars($recipientActionDetails['to_department_name']); ?></div>
                                <?php elseif (!empty($recipientActionDetails['from_department_name'])): ?>
                                    <div class="text-muted small">From <?php echo htmlspecialchars($recipientActionDetails['from_department_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (($recipientActionDetails['urgent'] ?? 'No') === 'Yes'): ?>
                                <span class="badge-soft" style="background:#fee2e2; color:#991b1b;">Urgent</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted text-uppercase fw-semibold">Deadline</div>
                        <div class="fw-semibold mb-2"><?php echo !empty($recipientActionDetails['deadline']) ? htmlspecialchars(date('M d, Y', strtotime($recipientActionDetails['deadline']))) : 'N/A'; ?></div>
                        <div class="small text-muted text-uppercase fw-semibold">Action</div>
                        <div class="fw-semibold mb-2"><?php echo htmlspecialchars($recipientActionDetails['action'] ?: 'N/A'); ?></div>
                        <div class="small text-muted text-uppercase fw-semibold">Instruction</div>
                        <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($recipientActionDetails['instruction'] ?: 'N/A')); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($document['status'] === 'Draft' && $document['origin_department_id'] == $_SESSION['department_id']): ?>
                    <a href="<?php echo URLROOT; ?>/documents/edit/<?php echo $document['id']; ?>" class="btn btn-outline-secondary w-100">Edit Draft</a>
                    <form action="<?php echo URLROOT; ?>/documents/release/<?php echo $document['id']; ?>" method="POST" class="m-0">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Release this document?');">Release Document</button>
                    </form>
                    <?php if ($isManager): ?>
                        <form action="<?php echo URLROOT; ?>/documents/cancel/<?php echo $document['id']; ?>" method="POST" class="m-0">
                            <?php echo csrfInput(); ?>
                            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Cancel this draft document? This action cannot be undone.');">Cancel Draft</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!$isManager && empty($internalAssignment) && empty($isDivisionManagerRouteForStaff) && in_array($document['status'], $receivableStatuses, true) && (($routeType && !$routeCleared && ($routeType === 'THRU' || $thruCleared)) || (!$routeType && $document['destination_department_id'] == $_SESSION['department_id']))): ?>
                    <form action="<?php echo URLROOT; ?>/documents/receive/<?php echo $document['id']; ?>" method="POST" class="m-0">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Receive this document?');">Receive Document</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canReturnDocument)): ?>
                    <form action="<?php echo URLROOT; ?>/documents/returnDocument/<?php echo $document['id']; ?>" method="POST" class="app-card p-3 mt-2" style="background:#fff7ed; border:1px solid #fed7aa; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-3">Return Document</div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="return_reason">Reason for return</label>
                            <input type="text" id="return_reason" name="return_reason" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="attachment_issue">Attachment issue</label>
                            <select id="attachment_issue" name="attachment_issue" class="form-select">
                                <option value="">Select issue</option>
                                <?php foreach ($returnIssueOptions as $issue): ?>
                                    <option value="<?php echo htmlspecialchars($issue); ?>"><?php echo htmlspecialchars($issue); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="return_remarks">Remarks/details</label>
                            <textarea id="return_remarks" name="return_remarks" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Return this document to the releasing department?');">Return Document</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canReplaceReturnedAttachment)): ?>
                    <form action="<?php echo URLROOT; ?>/documents/uploadCorrectedAttachment/<?php echo $document['id']; ?>" method="POST" enctype="multipart/form-data" class="app-card p-3 mt-2" style="background:#f8fafc; border:1px solid #dbeafe; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-3">Upload Corrected Attachment</div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="corrected_attachment">Corrected attachment</label>
                            <input type="file" id="corrected_attachment" name="corrected_attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/jpeg,image/png,image/gif,image/webp" required>
                            <div class="form-text">Maximum size: <?php echo (int) MAX_ATTACHMENT_SIZE_MB; ?> MB.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="replacement_reason">Reason for replacement</label>
                            <textarea id="replacement_reason" name="replacement_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Upload Corrected Attachment</button>
                    </form>

                    <?php if (!empty($canReReleaseReturnedDocument)): ?>
                        <form action="<?php echo URLROOT; ?>/documents/reRelease/<?php echo $document['id']; ?>" method="POST" class="m-0">
                            <?php echo csrfInput(); ?>
                            <button type="submit" class="btn btn-success w-100" onclick="return confirm('Re-release this document with the corrected attachment?');">Re-release Document</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary w-100" disabled>Re-release Document</button>
                        <div class="small text-muted">Upload a corrected attachment before re-release.</div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($isManager && $managerStaffHandled && ($document['status'] ?? '') !== 'Returned' && (!$managerAcknowledged || (in_array($routeType, ['TO', 'DELEGATE'], true) && !$routeCleared))): ?>
                    <form action="<?php echo URLROOT; ?>/documents/managerReceive/<?php echo $document['id']; ?>" method="POST" class="m-0">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Receive this document as manager?');">Manager Receive</button>
                    </form>
                <?php endif; ?>

                <?php if ($isManager && $managerAcknowledged && $routeType === 'THRU' && !$managerThruCleared): ?>
                    <form action="<?php echo URLROOT; ?>/documents/clearThru/<?php echo $document['id']; ?>" method="POST" class="m-0">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Clear this THRU document?');">Clear THRU</button>
                    </form>
                <?php endif; ?>

                <?php if ($isManager && $managerAcknowledged && $routeType === 'CC' && !$managerCcNoted): ?>
                    <form action="<?php echo URLROOT; ?>/documents/noteCc/<?php echo $document['id']; ?>" method="POST" class="m-0">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Note this CC document?');">Note CC</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canDelegateInternally)): ?>
                    <form action="<?php echo URLROOT; ?>/documents/delegateToStaff/<?php echo $document['id']; ?>" method="POST" class="app-card p-3 mt-2" style="background:#f8fafc; border:1px solid #dbeafe; box-shadow:none;" data-document-staff-delegate-form>
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-3">Delegate to Staff</div>
                        <div class="mb-2">
                            <label id="document_delegate_staff_label" class="form-label small fw-semibold">Staff member</label>
                            <div class="route-multiselect document-staff-multiselect" data-document-staff-multiselect data-route-required>
                                <button
                                    type="button"
                                    class="form-select route-multiselect-toggle text-start"
                                    aria-labelledby="document_delegate_staff_label"
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
                                    <div class="route-checkbox-group route-multiselect-options" role="group" aria-labelledby="document_delegate_staff_label">
                                        <?php foreach ($divisionStaff as $staff): ?>
                                            <?php
                                            $staffId = (int) $staff['id'];
                                            $staffName = trim(($staff['firstname'] ?? '') . ' ' . (!empty($staff['middle_initial']) ? $staff['middle_initial'] . '. ' : '') . ($staff['lastname'] ?? ''));
                                            ?>
                                            <label class="route-checkbox-item" for="document_delegate_staff_<?php echo $staffId; ?>" data-route-label="<?php echo htmlspecialchars(strtolower($staffName), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="document_delegate_staff_<?php echo $staffId; ?>"
                                                    name="assigned_to_user_ids[]"
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
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="internal_instruction">Instruction</label>
                            <textarea id="internal_instruction" name="internal_instruction" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-dark w-100">Delegate to Staff</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canManagerReturnDocument)): ?>
                    <form action="<?php echo URLROOT; ?>/documents/managerReturnDocument/<?php echo $document['id']; ?>" method="POST" class="app-card p-3 mt-2" style="background:#fff7ed; border:1px solid #fed7aa; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-3">Return Document</div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="manager_return_remarks">Remarks</label>
                            <textarea id="manager_return_remarks" name="manager_return_remarks" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Return this document with remarks?');">Return with Remarks</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canManagerCompleteDocument)): ?>
                    <form action="<?php echo URLROOT; ?>/documents/managerCompleteDocument/<?php echo $document['id']; ?>" method="POST" class="app-card p-3 mt-2" style="background:#f0fdf4; border:1px solid #bbf7d0; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-3">Complete Document</div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="manager_completion_remarks">Remarks</label>
                            <textarea id="manager_completion_remarks" name="manager_completion_remarks" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Mark this document completed?');">Mark Complete</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canReceiveInternalAssignment)): ?>
                    <form action="<?php echo URLROOT; ?>/documents/receiveInternalAssignment/<?php echo $document['id']; ?>" method="POST" class="m-0">
                        <?php echo csrfInput(); ?>
                        <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Receive this internal assignment?');">Receive Internal Assignment</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canCompleteInternalAssignment)): ?>
                    <form action="<?php echo URLROOT; ?>/documents/completeInternalAssignment/<?php echo $document['id']; ?>" method="POST" enctype="multipart/form-data" class="app-card p-3 mt-2" style="background:#f0fdf4; border:1px solid #bbf7d0; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-3">Complete Internal Assignment</div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="completion_attachment">Completion attachment</label>
                            <input type="file" id="completion_attachment" name="completion_attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Maximum size: <?php echo (int) MAX_ATTACHMENT_SIZE_MB; ?> MB.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="completion_remarks">Remarks</label>
                            <textarea id="completion_remarks" name="completion_remarks" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Mark this internal assignment completed?');">Mark Completed</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($canReturnInternalAssignment)): ?>
                    <?php if (!empty($canConfirmInternalAssignment)): ?>
                        <form action="<?php echo URLROOT; ?>/documents/confirmInternalAssignment/<?php echo $document['id']; ?>" method="POST" class="m-0">
                            <?php echo csrfInput(); ?>
                            <button type="submit" class="btn btn-success w-100" onclick="return confirm('Confirm this internal assignment completion?');">Confirm Completion</button>
                        </form>
                    <?php endif; ?>

                    <form action="<?php echo URLROOT; ?>/documents/returnInternalAssignment/<?php echo $document['id']; ?>" method="POST" class="app-card p-3 mt-2" style="background:#fff7ed; border:1px solid #fed7aa; box-shadow:none;">
                        <?php echo csrfInput(); ?>
                        <div class="fw-bold mb-3">Return Internal Assignment</div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="internal_return_remarks">Remarks for staff</label>
                            <textarea id="internal_return_remarks" name="internal_return_remarks" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Return this internal assignment to staff?');">Return to Staff</button>
                    </form>
                <?php endif; ?>

                <?php if ($isParentDepartment && $isManager && $managerAcknowledged && !$hasDelegatedChild && ((in_array($routeType, ['TO', 'DELEGATE'], true) && $routeCleared) || (!$routeType && $document['status'] === 'Received' && $document['destination_department_id'] == $_SESSION['department_id']))): ?>
                    <a href="<?php echo URLROOT; ?>/documents/forward/<?php echo $document['id']; ?>" class="btn btn-outline-dark">Forward Document for Action</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="app-card p-4 h-100">
            <h3 class="h5 fw-bold mb-3">Routing</h3>
            <div class="d-grid gap-3">
                <?php foreach (['THRU' => 'THRU', 'TO' => 'TO', 'CC' => 'CC', 'DELEGATE' => 'DELEGATE'] as $routingKey => $routingLabel): ?>
                    <div>
                        <div class="fw-semibold mb-2"><?php echo $routingLabel; ?></div>
                        <?php if (!empty($routing[$routingKey])): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($routing[$routingKey] as $route): ?>
                                    <div class="app-card p-3" style="background:#f8fafc; box-shadow:none;">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($route['division_name']); ?></div>
                                        <div class="small text-muted mt-1"><?php if (($route['status'] ?? '') === 'Returned') { echo 'Returned'; } elseif ($routingKey === 'THRU') { echo $route['is_cleared'] ? 'Cleared' : 'Pending'; } elseif ($routingKey === 'CC') { echo $route['is_cleared'] ? 'Noted' : 'Pending'; } else { echo $route['is_cleared'] ? 'Received' : 'Pending'; } ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small">-</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="app-card p-4 h-100">
            <h3 class="h5 fw-bold mb-3">Timeline</h3>
            <?php if (!empty($logs)): ?>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0" data-client-pagination="true">
                        <thead><tr><th>Action</th><th>User</th><th>Department</th><th>Date</th><th>Remarks</th></tr></thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($log['department_name']); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($log['timestamp']))); ?></td>
                                    <td class="text-muted"><?php echo $renderTimelineRemarks($log); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-muted">No activity.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($attachmentHistory)): ?>
    <div class="app-card p-4 mb-4">
        <h3 class="h5 fw-bold mb-3">Attachment History</h3>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0" data-client-pagination="true">
                <thead><tr><th>Old Filename</th><th>New Filename</th><th>Uploaded By</th><th>Date Uploaded</th><th>Reason</th><th>Return</th></tr></thead>
                <tbody>
                    <?php foreach ($attachmentHistory as $history): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($history['old_filename'] ?: '-'); ?></td>
                            <td class="fw-semibold">
                                <?php echo htmlspecialchars($history['new_filename']); ?>
                                <?php if (!empty($history['is_active'])): ?>
                                    <span class="badge-soft ms-1" style="background:#dcfce7; color:#166534;">Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($history['uploaded_by_name']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($history['uploaded_at']))); ?></td>
                            <td class="text-muted"><?php echo nl2br(htmlspecialchars($history['replacement_reason'])); ?></td>
                            <td>
                                <?php if (!empty($history['return_id'])): ?>
                                    #<?php echo (int) $history['return_id']; ?><?php echo !empty($history['return_reason']) ? ' - ' . htmlspecialchars($history['return_reason']) : ''; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<style>
    [data-document-staff-delegate-form] {
        position: relative;
        z-index: 30;
    }
    [data-document-staff-delegate-form]:has(.route-multiselect-menu:not(.d-none)) {
        z-index: 1400;
    }
    .route-multiselect {
        position: relative;
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
    .document-staff-multiselect .route-multiselect-options {
        max-height: 9.9rem;
        padding: 0.45rem;
        overflow-y: auto;
    }
</style>

<script>
(() => {
    const form = document.querySelector('[data-document-staff-delegate-form]');
    if (!form) {
        return;
    }

    const container = form.querySelector('[data-document-staff-multiselect]');
    const toggle = container ? container.querySelector('[data-route-multiselect-toggle]') : null;
    const menu = container ? container.querySelector('[data-route-multiselect-menu]') : null;
    const search = container ? container.querySelector('[data-route-multiselect-search]') : null;
    const summary = container ? container.querySelector('[data-route-multiselect-summary]') : null;
    const checks = container ? Array.prototype.slice.call(container.querySelectorAll('input[type="checkbox"]')) : [];
    const items = container ? Array.prototype.slice.call(container.querySelectorAll('.route-checkbox-item')) : [];
    const empty = container ? container.querySelector('[data-route-empty]') : null;
    const error = container ? container.querySelector('[data-route-required-error]') : null;

    function selectedLabels() {
        return checks
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => {
                const label = checkbox.closest('.route-checkbox-item');
                return label ? label.textContent.trim() : '';
            })
            .filter(Boolean);
    }

    function updateSummary() {
        if (!summary) {
            return;
        }

        const labels = selectedLabels();
        if (labels.length === 0) {
            summary.textContent = 'Select staff';
        } else if (labels.length === 1) {
            summary.textContent = labels[0];
        } else {
            summary.textContent = labels.length + ' staff selected';
        }
    }

    function filterOptions() {
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
    }

    function setOpen(isOpen) {
        if (!container || !menu || !toggle) {
            return;
        }

        container.classList.toggle('is-open', isOpen);
        menu.classList.toggle('d-none', !isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen && search) {
            search.focus();
            filterOptions();
        }
    }

    function showRequiredError() {
        if (container) {
            container.classList.add('is-invalid');
        }
        if (error) {
            error.classList.add('d-block');
        }
        setOpen(true);
    }

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
            if (container) {
                container.classList.remove('is-invalid');
            }
            if (error) {
                error.classList.remove('d-block');
            }
        });
    });

    form.addEventListener('submit', (event) => {
        if (!checks.some((checkbox) => checkbox.checked)) {
            event.preventDefault();
            showRequiredError();
            return;
        }

        if (!confirm('Delegate this document to the selected staff member(s)?')) {
            event.preventDefault();
        }
    });

    document.addEventListener('click', (event) => {
        if (container && !container.contains(event.target)) {
            setOpen(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
    filterOptions();
})();
</script>

<?php require_once APPROOT . '/views/layout/footer.php'; ?>
