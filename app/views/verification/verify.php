<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Verification</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            color: #0f172a;
            font-family: Arial, sans-serif;
        }
        .verify-card {
            max-width: 760px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid #dbe3ee;
            border-radius: 24px;
            box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .verify-head {
            padding: 28px 28px 18px;
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.12), rgba(14, 116, 144, 0.08));
            border-bottom: 1px solid #dbe3ee;
        }
        .verify-head h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        .verify-head p {
            margin: 0;
            color: #475569;
            line-height: 1.6;
        }
        .verify-body {
            padding: 28px;
        }
        .verify-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 700;
            background: #dcfce7;
            color: #166534;
        }
        .verify-status.invalid {
            background: #fee2e2;
            color: #991b1b;
        }
        .detail-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .detail-card {
            padding: 16px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #f8fafc;
        }
        .detail-card .label {
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
        }
        .detail-card .value {
            font-size: 16px;
            font-weight: 700;
            line-height: 1.45;
        }
        .detail-card .value.muted {
            color: #475569;
            font-weight: 500;
        }
        .verify-note {
            margin-top: 22px;
            padding: 16px 18px;
            border-radius: 16px;
            background: #eff6ff;
            color: #1e3a8a;
            line-height: 1.6;
        }
        .verify-note.warning {
            background: #fff7ed;
            color: #9a3412;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="verify-head">
            <h1>Document Verification</h1>
            <p>This page confirms whether the scanned QR token matches a document issued by the DTS. Public users see basic verification details only.</p>
        </div>

        <div class="verify-body">
            <?php if (!empty($document)): ?>
                <div class="verify-status">Verified QR token</div>

                <div class="detail-grid">
                    <div class="detail-card">
                        <div class="label">Document No.</div>
                        <div class="value"><?php echo htmlspecialchars($document['prefix'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="label">Title</div>
                        <div class="value"><?php echo htmlspecialchars($document['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="label">Type</div>
                        <div class="value"><?php echo htmlspecialchars($document['type'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="label">Origin Department</div>
                        <div class="value"><?php echo htmlspecialchars($document['origin_division'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="label">Created</div>
                        <div class="value"><?php echo !empty($document['created_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime($document['created_at'])), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="label">Status</div>
                        <div class="value"><?php echo htmlspecialchars($document['status'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <?php if ($showFullDetails): ?>
                        <div class="detail-card">
                            <div class="label">Destination</div>
                            <div class="value"><?php echo htmlspecialchars($document['destination_division'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="detail-card">
                            <div class="label">Released</div>
                            <div class="value"><?php echo !empty($document['released_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime($document['released_at'])), ENT_QUOTES, 'UTF-8') : 'Not yet released'; ?></div>
                        </div>
                        <div class="detail-card" style="grid-column: 1 / -1;">
                            <div class="label">Particulars</div>
                            <div class="value muted"><?php echo !empty(trim((string) ($document['particulars'] ?? ''))) ? nl2br(htmlspecialchars($document['particulars'], ENT_QUOTES, 'UTF-8')) : 'No particulars provided.'; ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($showFullDetails): ?>
                    <div class="verify-note">
                        You are signed in with permission to this document, so DTS is showing the extended record details.
                    </div>
                <?php else: ?>
                    <div class="verify-note">
                        This QR code is valid. Sensitive document details are hidden unless the viewer is logged in with authorized DTS access.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="verify-status invalid">Invalid or unknown QR token</div>
                <div class="verify-note warning">
                    The scanned QR token did not match any DTS document record, or the token format is invalid.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
