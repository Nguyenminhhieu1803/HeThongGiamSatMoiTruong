<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../app_config.php'; 

// Đặt Content-Type cho phản hồi này là JSON
header('Content-Type: application/json');

// Bao gồm file kết nối cơ sở dữ liệu
require_once '../db_connect.php'; 

// Kiểm tra xem biến $conn đã được thiết lập từ db_connect.php và có thành công không.
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in save_email_settings.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Kiểm tra phương thức yêu cầu (chỉ chấp nhận POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Lấy dữ liệu JSON từ request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Kiểm tra dữ liệu đầu vào
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input.']);
    exit();
}

// Kiểm tra các trường bắt buộc
if (!isset($data['device_id'], $data['enable_email_alerts'], $data['email_address'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields (device_id, enable_email_alerts, email_address).']);
    exit();
}

$deviceId = $data['device_id'];
$enableEmailAlerts = (bool)$data['enable_email_alerts']; // Chuyển đổi về boolean
$emailAddress = $data['email_address'];

// Kiểm tra định dạng email nếu bật cảnh báo
if ($enableEmailAlerts && !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
    exit();
}

$success_count = 0;
$error_messages = [];

// --- SQL để cập nhật hoặc chèn địa chỉ email ---
$setting_name_email = 'email_address';
$bind_email_value = (string)$emailAddress; // Chuyển đổi sang string

$stmt_email = $conn->prepare("INSERT INTO device_settings (device_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
if ($stmt_email === false) {
    error_log("Prepare failed for email_address in save_email_settings.php: " . $conn->error);
    $error_messages[] = 'Lỗi hệ thống khi chuẩn bị truy vấn email.';
} else {
    $stmt_email->bind_param("ssss", $deviceId, $setting_name_email, $bind_email_value, $bind_email_value);
    if ($stmt_email->execute()) {
        $success_count++;
    } else {
        error_log("Execute failed for email_address in save_email_settings.php: " . $stmt_email->error);
        $error_messages[] = 'Lỗi khi lưu địa chỉ email: ' . $stmt_email->error;
    }
    $stmt_email->close();
}

// --- SQL để cập nhật hoặc chèn trạng thái bật/tắt cảnh báo email ---
$setting_name_enable_alerts = 'enable_email_alerts';
$bind_enable_alerts_value = (string)($enableEmailAlerts ? 1 : 0); // Chuyển boolean sang '1' hoặc '0' (string)

$stmt_enable_alerts = $conn->prepare("INSERT INTO device_settings (device_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
if ($stmt_enable_alerts === false) {
    error_log("Prepare failed for enable_email_alerts in save_email_settings.php: " . $conn->error);
    $error_messages[] = 'Lỗi hệ thống khi chuẩn bị truy vấn trạng thái cảnh báo email.';
} else {
    $stmt_enable_alerts->bind_param("ssss", $deviceId, $setting_name_enable_alerts, $bind_enable_alerts_value, $bind_enable_alerts_value);
    if ($stmt_enable_alerts->execute()) {
        $success_count++;
    } else {
        error_log("Execute failed for enable_email_alerts in save_email_settings.php: " . $stmt_enable_alerts->error);
        $error_messages[] = 'Lỗi khi lưu trạng thái cảnh báo email: ' . $stmt_enable_alerts->error;
    }
    $stmt_enable_alerts->close();
}

$conn->close();

// --- Trả về phản hồi cuối cùng ---
if ($success_count === 2) {
    echo json_encode(['status' => 'success', 'message' => 'Cài đặt email đã được cập nhật thành công.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Có lỗi khi cập nhật cài đặt email: ' . implode('; ', $error_messages)]);
}
exit(); // Đảm bảo exit() để dừng script
?>