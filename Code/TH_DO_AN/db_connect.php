<?php
// db_connect.php - File xử lý kết nối cơ sở dữ liệu

// Lưu ý: Các cấu hình hiển thị lỗi và CORS đã được di chuyển vào app_config.php

// Thông tin kết nối cơ sở dữ liệu
$servername = "localhost"; // Tên máy chủ 
$username = "root";        // Tên người dùng MySQL 
$password = "";            // Mật khẩu MySQL 
$dbname = "dht_sensor_db"; // Tên cơ sở dữ liệu 

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    // Không đặt header('Content-Type: application/json'); ở đây nữa.
    // Việc này sẽ được thực hiện trong từng file API sau khi app_config.php được include.
    
    // Ghi lỗi vào log của máy chủ
    error_log("Kết nối database thất bại: " . $conn->connect_error); // Ghi lỗi vào log của máy chủ
    
    // Đặt mã lỗi HTTP và trả về JSON cho phía client
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại sau."]);
    exit(); 
}

// Thiết lập bộ ký tự cho kết nối (để hỗ trợ tiếng Việt và Unicode)
$conn->set_charset("utf8mb4");

?>