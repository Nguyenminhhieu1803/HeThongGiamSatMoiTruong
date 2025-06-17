<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
// Đường dẫn này giả định logout.php nằm trong Login/api/users/
// và app_config.php nằm trong TH_DO_AN/
// Cần đi lên 3 cấp (../../../) để đến htdocs/ sau đó đi xuống TH_DO_AN/
require_once '../../../TH_DO_AN/app_config.php'; 

// Đặt header Content-Type cho phản hồi JSON
header('Content-Type: application/json');

// Xử lý preflight OPTIONS request (phải ĐẶT TRƯỚC mọi logic khác và session_start())
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Chỉ chấp nhận phương thức POST cho đăng xuất
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ. Chỉ chấp nhận phương thức POST cho đăng xuất.']);
    exit();
}

session_start(); // Bắt đầu session sau khi kiểm tra method (để tránh tạo session không cần thiết cho OPTIONS request)

// Xóa tất cả các biến session
$_SESSION = array();

// Xóa cookie session khỏi trình duyệt
// Điều này rất quan trọng để đảm bảo cookie phiên không còn tồn tại
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Đặt thời gian hết hạn trong quá khứ
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy session trên máy chủ
session_destroy();

echo json_encode(['status' => 'success', 'message' => 'Đăng xuất thành công.']);
exit(); // Đảm bảo thoát sau khi gửi phản hồi
?>