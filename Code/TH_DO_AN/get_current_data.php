<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once 'app_config.php'; // Đảm bảo đường dẫn này đúng với vị trí của app_config.php

// Đặt Content-Type cho phản hồi này là JSON
// Đảm bảo dòng này nằm sau require_once 'app_config.php' để tránh "Headers already sent"
header("Content-Type: application/json"); 

// Bao gồm file kết nối cơ sở dữ liệu
require_once 'db_connect.php'; 

// Kiểm tra xem biến $conn đã được thiết lập từ db_connect.php chưa
// và kiểm tra kết nối có thành công không
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection error in get_current_data.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Lấy device_id từ tham số GET, tên phải khớp với main.js
$device_id_filter = isset($_GET['device_id']) ? $_GET['device_id'] : null;

// Xây dựng truy vấn SQL
// Lấy nhiệt độ, độ ẩm và thời gian đọc từ bảng sensor_readings
$sql = "SELECT temperature, humidity, reading_time FROM sensor_readings ";

// Nếu có device_id_filter, thêm điều kiện WHERE
if ($device_id_filter) {
    $sql .= "WHERE device_id = ? ";
}

// Sắp xếp theo ID giảm dần (để lấy bản ghi mới nhất) và giới hạn 1 bản ghi
$sql .= "ORDER BY id DESC LIMIT 1";

// Chuẩn bị câu lệnh SQL
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Ghi log lỗi thay vì hiển thị trực tiếp trong môi trường sản phẩm
    error_log("Prepare failed in get_current_data.php: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống. Vui lòng thử lại sau."]);
    $conn->close();
    exit();
}

// Nếu có device_id_filter, gắn tham số vào câu lệnh
if ($device_id_filter) {
    $stmt->bind_param("s", $device_id_filter); // "s" cho kiểu string
}

// Thực thi câu lệnh
$stmt->execute();
$result = $stmt->get_result();

// Kiểm tra và trả về kết quả
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "status" => "success",
        // Trả về một đối tượng 'reading' để khớp với main.js
        "reading" => [
            "temperature" => floatval($row["temperature"]),
            "humidity" => floatval($row["humidity"]),
            "reading_time" => $row["reading_time"] // Đảm bảo tên trường khớp với JS
        ]
    ]);
    exit(); // Thêm exit() sau khi gửi JSON
} else {
    // Trả về thông báo lỗi nếu không tìm thấy dữ liệu
    echo json_encode([
        "status" => "error",
        "message" => "Không tìm thấy dữ liệu." . ($device_id_filter ? " cho thiết bị " . htmlspecialchars($device_id_filter) : "")
    ]);
    exit(); // Thêm exit() sau khi gửi JSON
}

// Đóng câu lệnh và kết nối database (dòng này sẽ không được thực thi nếu exit() đã được gọi trước đó)
$stmt->close();
$conn->close();
?>