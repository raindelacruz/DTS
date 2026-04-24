<?php
$attachmentType = $attachmentType ?? 'unsupported';
$verificationUrl = $verificationUrl ?? '';
$qrCodeDataUri = $qrCodeDataUri ?? '';
$sourceUrl = $sourceUrl ?? '';
$previewUrl = $previewUrl ?? $sourceUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(($document['prefix'] ?? 'Document') . ' Attachment', ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --viewer-bg: #eef2f7;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border-soft: #dbe3ee;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: var(--viewer-bg); color: var(--text-main); font-family: Arial, sans-serif; }
        .viewer-shell { min-height: 100vh; padding: 20px; }
        .viewer-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            margin-bottom: 18px;
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.92);
        }
        .viewer-title { margin: 0; font-size: 20px; }
        .viewer-meta { margin-top: 4px; color: var(--text-muted); font-size: 14px; }
        .viewer-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .viewer-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 12px;
            border: 1px solid #0f766e;
            background: #0f766e;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .viewer-button.secondary {
            background: #fff;
            color: var(--text-main);
            border-color: var(--border-soft);
        }
        .viewer-stage {
            display: flex;
            justify-content: center;
        }
        .attachment-frame {
            position: relative;
            width: min(100%, 1120px);
            min-height: calc(100vh - 140px);
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 22px;
            box-shadow: 0 24px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .attachment-pdf-wrap {
            min-height: calc(100vh - 140px);
            background: #2f2f2f;
            overflow: auto;
            padding: 28px 0;
        }
        .attachment-pdf-pages {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            min-height: 100%;
        }
        .attachment-pdf-page {
            position: relative;
            width: fit-content;
            max-width: 100%;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
        }
        .attachment-pdf-canvas {
            display: block;
            max-width: 100%;
            height: auto;
            background: #fff;
        }
        .attachment-pdf-loading {
            padding: 48px 24px;
            text-align: center;
            color: #cbd5e1;
            font-weight: 700;
        }
        .attachment-pdf-error {
            padding: 48px 24px;
            text-align: center;
            color: #fecaca;
            font-weight: 700;
        }
        .attachment-image-wrap {
            position: relative;
            min-height: calc(100vh - 140px);
            background: #e2e8f0;
            overflow: auto;
        }
        .attachment-image {
            display: block;
            width: 100%;
            height: auto;
            background: #fff;
        }
        .qr-overlay {
            position: absolute;
            top: calc(31% - 96px);
            right: calc(12.5% + 48px);
            z-index: 20;
            width: 128px;
            padding: 9px;
            border: 1px solid rgba(15, 23, 42, 0.18);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
            text-align: center;
            pointer-events: none;
        }
        .qr-overlay img {
            display: block;
            width: 100%;
            height: auto;
        }
        .qr-overlay-label {
            margin-top: 8px;
            font-size: 10px;
            line-height: 1.35;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .attachment-fallback {
            padding: 48px 24px;
            text-align: center;
        }
        .attachment-fallback p {
            margin: 0 auto 16px;
            max-width: 640px;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .pdf-print-note {
            margin: 12px auto 0;
            width: min(100%, 1120px);
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.5;
            text-align: center;
        }
        .page-qr-overlay {
            position: absolute;
            top: calc(31% - 96px);
            right: calc(12.5% + 48px);
            z-index: 5;
            width: 128px;
            padding: 9px;
            border: 1px solid rgba(15, 23, 42, 0.18);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
            text-align: center;
            pointer-events: none;
        }
        .page-qr-overlay img {
            display: block;
            width: 100%;
            height: auto;
        }
        .page-qr-overlay-label {
            margin-top: 8px;
            font-size: 10px;
            line-height: 1.35;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        @media (max-width: 1100px) {
            .qr-overlay {
                top: calc(28% - 96px);
                right: calc(8% + 48px);
                width: 114px;
            }
            .page-qr-overlay {
                top: calc(28% - 96px);
                right: calc(8% + 48px);
                width: 114px;
            }
        }

        @media print {
            @page { margin: 12mm; }
            html, body { background: #fff; }
            .viewer-shell { padding: 0; }
            .viewer-topbar { display: none; }
            .viewer-stage { display: block; }
            .attachment-frame {
                width: 100%;
                min-height: auto;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                overflow: visible;
            }
            .attachment-pdf-wrap {
                min-height: auto;
                padding: 0;
                overflow: visible;
                background: #fff;
            }
            .attachment-pdf-pages {
                display: block;
            }
            .attachment-pdf-page {
                box-shadow: none;
                margin: 0 auto 8mm;
                page-break-after: always;
                break-after: page;
            }
            .attachment-pdf-page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .attachment-pdf-canvas {
                max-width: 100%;
            }
            .attachment-image-wrap {
                min-height: auto;
                overflow: visible;
                background: #fff;
            }
            .attachment-image {
                max-width: 100%;
                page-break-inside: avoid;
            }
            .qr-overlay {
                position: absolute;
                top: 56.6mm;
                right: 30.7mm;
                width: 33mm;
                padding: 2.5mm;
                border: 0.2mm solid #94a3b8;
                box-shadow: none;
                background: #fff;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .qr-overlay-label {
                font-size: 7pt;
            }
            .page-qr-overlay {
                position: absolute;
                top: 56.6mm;
                right: 30.7mm;
                width: 33mm;
                padding: 2.5mm;
                border: 0.2mm solid #94a3b8;
                box-shadow: none;
                background: #fff;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .page-qr-overlay-label {
                font-size: 7pt;
            }
            .pdf-print-note {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="viewer-shell">
        <div class="viewer-topbar">
            <div>
                <h1 class="viewer-title"><?php echo htmlspecialchars($document['title'] ?? 'Attachment', ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="viewer-meta">
                    <?php echo htmlspecialchars($document['prefix'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($verificationUrl !== ''): ?>
                        | Verify via QR
                    <?php endif; ?>
                </div>
            </div>
            <div class="viewer-actions">
                <a href="<?php echo htmlspecialchars(URLROOT . '/documents/show/' . (int) $document['id'], ENT_QUOTES, 'UTF-8'); ?>" class="viewer-button secondary">Back</a>
                <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="viewer-button secondary">Open Original</a>
                <?php if ($attachmentType === 'pdf'): ?>
                    <button type="button" class="viewer-button" onclick="window.print()">Print With QR</button>
                <?php else: ?>
                    <button type="button" class="viewer-button" onclick="window.print()">Print Attachment</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="viewer-stage">
            <div class="attachment-frame">
                <?php if ($attachmentType === 'image'): ?>
                    <div class="qr-overlay">
                        <img src="<?php echo htmlspecialchars($qrCodeDataUri, ENT_QUOTES, 'UTF-8'); ?>" alt="Document verification QR code">
                        <div class="qr-overlay-label">Scan to verify document</div>
                    </div>
                <?php endif; ?>

                <?php if ($attachmentType === 'pdf'): ?>
                    <div class="attachment-pdf-wrap">
                        <div id="pdf-pages" class="attachment-pdf-pages" data-source-url="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" data-qr-image="<?php echo htmlspecialchars($qrCodeDataUri, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="attachment-pdf-loading">Loading PDF preview...</div>
                        </div>
                    </div>
                <?php elseif ($attachmentType === 'image'): ?>
                    <div class="attachment-image-wrap">
                        <img class="attachment-image" src="<?php echo htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Document attachment image">
                    </div>
                <?php else: ?>
                    <div class="attachment-fallback">
                        <p>This attachment type cannot be rendered inside the printable viewer, but the original file remains unchanged and can still be opened separately.</p>
                        <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="viewer-button">Open Attachment</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($attachmentType === 'pdf'): ?>
            <div class="pdf-print-note">Use <strong>Print With QR</strong> to print the same viewer page with the verification overlay visible.</div>
        <?php endif; ?>
    </div>
    <?php if ($attachmentType === 'pdf'): ?>
        <script type="module">
            import * as pdfjsLib from '<?php echo htmlspecialchars(URLROOT . '/assets/pdfjs/pdf.min.mjs', ENT_QUOTES, 'UTF-8'); ?>';

            pdfjsLib.GlobalWorkerOptions.workerSrc = '<?php echo htmlspecialchars(URLROOT . '/assets/pdfjs/pdf.worker.min.mjs', ENT_QUOTES, 'UTF-8'); ?>';

            const pagesRoot = document.getElementById('pdf-pages');

            if (pagesRoot) {
                const sourceUrl = pagesRoot.dataset.sourceUrl || '';
                const qrImage = pagesRoot.dataset.qrImage || '';

                const createQrOverlay = () => {
                    const overlay = document.createElement('div');
                    overlay.className = 'page-qr-overlay';
                    overlay.innerHTML = `
                        <img src="${qrImage}" alt="Document verification QR code">
                        <div class="page-qr-overlay-label">Scan to verify document</div>
                    `;
                    return overlay;
                };

                const renderPdf = async () => {
                    try {
                        const loadingTask = pdfjsLib.getDocument({
                            url: sourceUrl,
                            cMapUrl: '<?php echo htmlspecialchars(URLROOT . '/assets/pdfjs/cmaps/', ENT_QUOTES, 'UTF-8'); ?>',
                            cMapPacked: true,
                            standardFontDataUrl: '<?php echo htmlspecialchars(URLROOT . '/assets/pdfjs/standard_fonts/', ENT_QUOTES, 'UTF-8'); ?>'
                        });
                        const pdf = await loadingTask.promise;
                        pagesRoot.innerHTML = '';

                        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                            const page = await pdf.getPage(pageNumber);
                            const viewport = page.getViewport({ scale: 1.6 });
                            const outputScale = window.devicePixelRatio || 1;
                            const canvas = document.createElement('canvas');
                            const context = canvas.getContext('2d', { alpha: false });
                            const pageWrap = document.createElement('div');

                            pageWrap.className = 'attachment-pdf-page';
                            canvas.className = 'attachment-pdf-canvas';
                            canvas.width = Math.floor(viewport.width * outputScale);
                            canvas.height = Math.floor(viewport.height * outputScale);
                            canvas.style.width = `${viewport.width}px`;
                            canvas.style.height = `${viewport.height}px`;

                            const transform = outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null;

                            await page.render({
                                canvasContext: context,
                                transform,
                                viewport
                            }).promise;

                            pageWrap.appendChild(canvas);

                            if (pageNumber === 1) {
                                pageWrap.appendChild(createQrOverlay());
                            }

                            pagesRoot.appendChild(pageWrap);
                        }
                    } catch (error) {
                        console.error(error);
                        pagesRoot.innerHTML = '<div class="attachment-pdf-error">Unable to render this PDF preview for printing.</div>';
                    }
                };

                renderPdf();
            }
        </script>
    <?php endif; ?>
</body>
</html>
