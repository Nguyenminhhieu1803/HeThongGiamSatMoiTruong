<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../app_config.php'; 

// Đặt Content-Type cho phản hồi này là JSON
header('Content-Type: application/json');

// Bao gồm file kết nối cơ sở dữ liệu
require_once '../db_connect.php'; 

// Kiểm tra xem biến $conn đã được thiết lập từ db_connect.php và có thành công không.
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in save_alert_thresholds.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Kiểm tra phương thức yêu cầu
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
if (!isset($data['device_id'], $data['temp_threshold'], $data['humidity_threshold'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields (device_id, temp_threshold, humidity_threshold).']);
    exit();
}

$deviceId = $data['device_id'];
$tempThreshold = floatval($data['temp_threshold']);
$humidityThreshold = floatval($data['humidity_threshold']);

// Kiểm tra giá trị hợp lệ
if (!is_numeric($tempThreshold) || !is_numeric($humidityThreshold)) {
    echo json_encode(['status' => 'error', 'message' => 'Threshold values must be numbers.']);
    exit();
}

$success_count = 0;
$error_messages = [];

// --- SQL để cập nhật hoặc chèn ngưỡng nhiệt độ ---
$setting_name_temp = 'temp_threshold';
$bind_temp_value = (string)$tempThreshold; // Chuyển đổi float sang string để lưu vào setting_value (varchar)

$stmt_temp = $conn->prepare("INSERT INTO device_settings (device_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
if ($stmt_temp === false) {
    error_log("Prepare failed for temp_threshold in save_alert_thresholds.php: " . $conn->error);
    $error_messages[] = 'Lỗi hệ thống khi chuẩn bị truy vấn nhiệt độ.';
} else {
    $stmt_temp->bind_param("ssss", $deviceId, $setting_name_temp, $bind_temp_value, $bind_temp_value);
    if ($stmt_temp->execute()) {
        $success_count++;
    } else {
        error_log("Execute failed for temp_threshold in save_alert_thresholds.php: " . $stmt_temp->error);
        $error_messages[] = 'Lỗi khi lưu ngưỡng nhiệt độ: ' . $stmt_temp->error;
    }
    $stmt_temp->close();
}


// --- SQL để cập nhật hoặc chèn ngưỡng độ ẩm ---
$setting_name_humidity = 'humidity_threshold';
$bind_humidity_value = (string)$humidityThreshold; // Chuyển đổi float sang string để lưu vào setting_value (varchar)

$stmt_humidity = $conn->prepare("INSERT INTO device_settings (device_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
if ($stmt_humidity === false) {
    error_log("Prepare failed for humidity_threshold in save_alert_thresholds.php: " . $conn->error);
    $error_messages[] = 'Lỗi hệ thống khi chuẩn bị truy vấn độ ẩm.';
} else {
    $stmt_humidity->bind_param("ssss", $deviceId, $setting_name_humidity, $bind_humidity_value, $bind_humidity_value);
    if ($stmt_humidity->execute()) {
        $success_count++;
    } else {
        error_log("Execute failed for humidity_threshold in save_alert_thresholds.php: " . $stmt_humidity->error);
        $error_messages[] = 'Lỗi khi lưu ngưỡng độ ẩm: ' . $stmt_humidity->error;
    }
    $stmt_humidity->close();
}

$conn->close();

// --- Trả về phản hồi cuối cùng ---
if ($success_count === 2) {
    echo json_encode(['status' => 'success', 'message' => 'Các ngưỡng cảnh báo đã được cập nhật thành công.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Có lỗi khi cập nhật ngưỡng cảnh báo: ' . implode('; ', $error_messages)]);
}
exit(); // Đảm bảo exit() để dừng script
?>