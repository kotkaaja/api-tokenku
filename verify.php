<?php
// Atur header output sebagai JSON
header('Content-Type: application/json');

// --- 1. KONEKSI DATABASE DARI ENVIRONMENT VARIABLES RAILWAY ---
// Railway menyediakan semua info koneksi dalam satu URL bernama DATABASE_URL
$db_url = getenv('DATABASE_URL');

if ($db_url === false) {
    echo json_encode(['status' => 'error', 'message' => 'Variabel koneksi database tidak ditemukan.']);
    exit();
}

// Parse URL database untuk mendapatkan detail koneksi
$db_parts = parse_url($db_url);

$db_host = $db_parts['host'];
$db_user = $db_parts['user'];
$db_pass = $db_parts['pass'];
$db_name = ltrim($db_parts['path'], '/');
$db_port = $db_parts['port'];

// Buat koneksi baru
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Cek koneksi
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit();
}

// --- 2. AMBIL INPUT DARI LUA ---
$token = $_POST['token'] ?? '';
$hwid = $_POST['hwid'] ?? '';

if (empty($token) || empty($hwid)) {
    echo json_encode(['status' => 'error', 'message' => 'Token atau HWID tidak boleh kosong']);
    exit();
}

// --- 3. PROSES VALIDASI ---
$sql = "SELECT * FROM tokens WHERE token_value = ? AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

$response = [];

if ($result->num_rows > 0) {
    $token_data = $result->fetch_assoc();
    $expiry_date = new DateTime($token_data['expiry_date']);
    $current_date = new DateTime();

    if ($current_date > $expiry_date) {
        $response = ['status' => 'error', 'message' => 'Token sudah kedaluwarsa'];
    } else {
        $assigned_hwid = $token_data['assigned_hwid'];
        if ($assigned_hwid === null) {
            $update_sql = "UPDATE tokens SET assigned_hwid = ? WHERE token_value = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $hwid, $token);
            $update_stmt->execute();
            $response = ['status' => 'success', 'message' => 'Token berhasil diaktifkan untuk perangkat ini'];
        } elseif ($assigned_hwid == $hwid) {
            $response = ['status' => 'success', 'message' => 'Selamat datang kembali!'];
        } else {
            $response = ['status' => 'error', 'message' => 'Token ini sudah terikat ke perangkat lain'];
        }
    }
} else {
    $response = ['status' => 'error', 'message' => 'Token tidak valid'];
}

// --- 4. KIRIM RESPON & TUTUP KONEKSI ---
echo json_encode($response);
$stmt->close();
$conn->close();
?>