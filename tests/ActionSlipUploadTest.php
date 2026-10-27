<?php

return [
    'completion forms submit real files and history links open saved output' => function ($root) {
        $temp = sys_get_temp_dir() . '/dts-upload-test-' . bin2hex(random_bytes(8));
        mkdir($temp);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        test_assert($socket !== false, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $token = bin2hex(random_bytes(16));
        $environment = getenv();
        $environment['ACTION_SLIP_UPLOAD_TEST_ROOT'] = $temp;
        $environment['ACTION_SLIP_UPLOAD_TEST_TOKEN'] = $token;
        $process = proc_open([PHP_BINARY, '-d', 'upload_tmp_dir=' . $temp, '-d', 'display_errors=0', '-d', 'display_startup_errors=0', '-S', $address, $root . '/tests/support/action_slip_upload_router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']], $pipes, $root, $environment);
        test_assert(is_resource($process), 'Could not start isolated upload server.');
        $url = 'http://' . $address . '/?token=' . $token;
        try {
            $html = false;
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $html = @file_get_contents($url);
                if ($html !== false) { break; }
                usleep(100000);
            }
            test_assert($html !== false, 'Upload server did not start.');
            preg_match_all('~<form\b([^>]+)>(.*?)</form>~s', $html, $forms, PREG_SET_ORDER);
            test_assert_same(7, count($forms), 'Every completion and confirmation form must render. Server output: ' . substr($html, -1200));
            $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
            foreach ($forms as $form) {
                test_assert(str_contains($form[1], 'enctype="multipart/form-data"'), 'File forms must submit multipart data.');
                test_assert(str_contains($form[2], 'name="completion_attachment"'), 'File picker is missing.');
                $boundary = 'DtsTest' . bin2hex(random_bytes(12));
                $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"completion_attachment\"; filename=\"output.pdf\"\r\nContent-Type: application/pdf\r\n\r\n" . $pdf . "\r\n--" . $boundary . "--\r\n";
                $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary, 'content' => $body]]);
                $response = file_get_contents($url, false, $context);
                // XAMPP may emit this startup notice when PHP uses its fallback upload temp directory.
                $response = preg_replace('~<br />\s*<b>Notice</b>:  PHP Request Startup: file created in the system\'s temporary directory in <b>Unknown</b> on line <b>0</b><br />\s*~', '', $response);
                $result = json_decode($response, true);
                test_assert(!empty($result['attachment']), 'A real submitted file must be saved. Server output: ' . $response);
                test_assert_same($result['attachment'], $result['assignment_attachment']);
                test_assert_same($result['attachment'], $result['event_attachment']);
                test_assert_same('For Action', $result['status']);
                preg_match('~href="([^"]+)"~', $result['history'], $link);
                test_assert(!empty($link[1]), 'History must render an attachment link.');
                test_assert_same($pdf, file_get_contents('http://' . $address . '/' . $link[1]), 'History link must open the saved bytes.');
            }
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => 'remarks=No+file']]);
            $result = json_decode(file_get_contents($url, false, $context), true);
            test_assert_same(null, $result['attachment'], 'Completion without a file must remain allowed.');
            test_assert_same(null, $result['event_attachment']);
            test_assert_same('', trim($result['history']), 'No attachment link should appear without a file.');
        } finally {
            proc_terminate($process);
            fclose($pipes[0]);
            proc_close($process);
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($temp);
        }
    }
];
