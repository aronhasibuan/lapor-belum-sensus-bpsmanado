<?php
// HANYA untuk admin yang sudah login. Tanpa penjagaan ini siapa pun bisa
// memanggil endpoint ini berulang kali dan menghabiskan kuota Fonnte.
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Akses ditolak. Silakan login sebagai admin terlebih dahulu.');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/fontte.php';

if (!isset($_GET['send'])) {
    echo "Gunakan: test_fontte.php?send=1\n";
    echo "Pastikan FONTTE_API_KEY sudah diatur di environment atau di config/fontte.php.\n";
    exit;
}

$numbers = [
    'PPL' => ['name' => 'PPL Test', 'phone' => '6281234567890'],
    'PML' => ['name' => 'PML Test', 'phone' => '6281234567891'],
    'KOSEKA' => ['name' => 'KOSEKA Test', 'phone' => '6281234567892'],
];

foreach ($numbers as $role => $contact) {
    $result = fonnte_send_message($contact['phone'], "Tes Fonnte | Role: {$role}\nHalo {$contact['name']}\nIni adalah tes pengiriman dari sistem laporan sensus.");
    echo "[$role] " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
}
