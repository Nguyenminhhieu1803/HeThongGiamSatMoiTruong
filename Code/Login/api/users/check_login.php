<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../../../TH_DO_AN/app_config.php'; // ĐIỀU CHỈNH ĐƯỜNG DẪN NÀY CHO CHÍNH XÁC

session_start(); // Session phải bắt đầu SAU KHI header đã được gửi bởi app_config.php nếu session ID là trong cookie
header('Content-Type: application/json'); // Đặt header phản hồi JSON

// Khởi tạo phản hồi mặc định là chưa đăng nhập
$response = ['status' => 'success', 'loggedIn' => false];

// Kiểm tra nếu các biến session của người dùng đã được thiết lập
if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role'])) {
    $response['loggedIn'] = true;
    $response['user_id'] = $_SESSION['user_id'];
    $response['username'] = $_SESSION['username'];
    $response['role'] = $_SESSION['role'];
    // Có thể thêm các thông tin khác nếu cần cho frontend
}

echo json_encode($response);
exit(); // Đảm bảo thoát sau khi gửi phản hồi
?>