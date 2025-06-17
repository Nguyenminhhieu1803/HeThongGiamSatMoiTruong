<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once 'app_config.php'; 

// Đặt Content-Type cho phản hồi này là JSON.
// Đảm bảo dòng này nằm sau require_once 'app_config.php' để tránh lỗi "Headers already sent"
header("Content-Type: application/json"); 

// Bao gồm file kết nối cơ sở dữ liệu
// Đảm bảo file 'db_connect.php' tồn tại trong cùng thư mục (TH_DO_AN/)
// và thiết lập biến $conn (kết nối mysqli)
require_once 'db_connect.php'; 

// Kiểm tra xem biến $conn đã được thiết lập từ db_connect.php chưa
// và kiểm tra kết nối có thành công không
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection error in post_data.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Thiết lập charset cho kết nối để hỗ trợ tiếng Việt
// Dòng này đã có trong db_connect.php, nên có thể bỏ qua ở đây để tránh dư thừa.
// $conn->set_charset("utf8mb4"); 

// Nhận dữ liệu POST thô (raw POST data)
$json = file_get_contents('php://input');
$data = json_decode($json);

// Kiểm tra xem dữ liệu JSON có hợp lệ không
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    error_log("Invalid JSON received in post_data.php: " . json_last_error_msg() . ". Raw data: '" . $json . "'");
    echo json_encode(["status" => "error", "message" => "Dữ liệu JSON không hợp lệ."]);
    $conn->close();
    exit();
}

// Lấy dữ liệu từ JSON payload
// Sử dụng null coalescing operator (??) để tránh lỗi nếu trường không tồn tại
$device_id = $data->device_id ?? '';
$temperature = $data->temperature ?? '';
$humidity = $data->humidity ?? '';
$timestamp = $data->timestamp ?? null; // Lấy timestamp từ ESP, có thể là null

// Kiểm tra các trường dữ liệu cần thiết
if (empty($device_id) || empty($temperature) || empty($humidity)) {
    error_log("Missing required data in post_data.php. Device ID: '$device_id', Temp: '$temperature', Humidity: '$humidity'");
    echo json_encode(["status" => "error", "message" => "Thiếu dữ liệu bắt buộc: device_id, temperature, hoặc humidity."]);
    $conn->close();
    exit();
}

// Chuyển đổi nhiệt độ và độ ẩm sang kiểu số thực
$temperature = floatval($temperature);
$humidity = floatval($humidity);

// Nếu timestamp không được cung cấp hoặc rỗng, sử dụng thời gian hiện tại của máy chủ
if (empty($timestamp)) {
    $timestamp = date('Y-m-d H:i:s');
}

// Chuẩn bị câu lệnh SQL để chèn dữ liệu
// Sử dụng Prepared Statements để tránh SQL Injection
$stmt = $conn->prepare("INSERT INTO sensor_readings (device_id, temperature, humidity, reading_time) VALUES (?, ?, ?, ?)");
if ($stmt === false) {
    error_log("Prepare failed in post_data.php: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống. Vui lòng thử lại sau."]);
    $conn->close();
    exit();
}

// Bind các tham số và thực thi câu lệnh
// "sdds" nghĩa là: s=string (device_id), d=double (temperature), d=double (humidity), s=string (reading_time)
$stmt->bind_param("sdds", $device_id, $temperature, $humidity, $timestamp);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Dữ liệu cảm biến đã được lưu thành công."]);
} else {
    error_log("Execute failed in post_data.php: " . $stmt->error);
    echo json_encode(["status" => "error", "message" => "Lỗi khi lưu dữ liệu cảm biến."]);
}

$stmt->close();
$conn->close();
?>