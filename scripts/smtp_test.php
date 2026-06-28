<?php

$host = 'smtp.gmail.com';
$port = 587;
$username = 'your_email@gmail.com';
$password = 'yourgoogleapppassword';
$from = 'your_email@gmail.com';
$to = 'your_test_receiver_email@gmail.com';

function smtp_read($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    echo $response;
    return $response;
}

function smtp_cmd($socket, $command) {
    echo "> " . preg_replace('/AUTH LOGIN|[A-Za-z0-9+\/=]{8,}/', '[hidden]', $command) . PHP_EOL;
    fwrite($socket, $command . "\r\n");
    return smtp_read($socket);
}

$socket = stream_socket_client($host . ':' . $port, $errno, $errstr, 20);

if (!$socket) {
    die("Connection failed: $errstr ($errno)" . PHP_EOL);
}

smtp_read($socket);
smtp_cmd($socket, 'EHLO localhost');
smtp_cmd($socket, 'STARTTLS');

if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    die("TLS failed" . PHP_EOL);
}

smtp_cmd($socket, 'EHLO localhost');
smtp_cmd($socket, 'AUTH LOGIN');
smtp_cmd($socket, base64_encode($username));
smtp_cmd($socket, base64_encode($password));
smtp_cmd($socket, 'MAIL FROM:<' . $from . '>');
smtp_cmd($socket, 'RCPT TO:<' . $to . '>');
smtp_cmd($socket, 'DATA');

$message = "Subject: DTS SMTP Test\r\n";
$message .= "From: $from\r\n";
$message .= "To: $to\r\n";
$message .= "\r\n";
$message .= "This is a test email from DTS SMTP settings.\r\n";
$message .= ".\r\n";

fwrite($socket, $message);
smtp_read($socket);

smtp_cmd($socket, 'QUIT');
fclose($socket);