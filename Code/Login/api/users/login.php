<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../../../TH_DO_AN/app_config.php'; // ĐIỀU CHỈNH ĐƯỜNG DẪN NÀY CHO CHÍNH XÁC

session_start(); // Bắt đầu session để lưu trữ trạng thái đăng nhập. PHẢI SAU require_once app_config.php để tránh Headers already sent.
header('Content-Type: application/json'); // Đặt header phản hồi JSON. PHẢI SAU require_once app_config.php.

// Bao gồm file kết nối database chung
// Đường dẫn tương đối từ Login/api/users/ đến TH_DO_AN/db_connect.php
require_once '../../../TH_DO_AN/db_connect.php'; 

// Kiểm tra kết nối sau khi include db_connect.php (db_connect.php đã có exit() nếu lỗi)
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection error in login.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Lấy dữ liệu JSON từ request body
$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Kiểm tra dữ liệu đầu vào
if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập tên đăng nhập và mật khẩu.']);
    exit();
}

// Chuẩn bị câu lệnh để lấy thông tin người dùng theo username
$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");

if ($stmt === false) {
    error_log("Lỗi chuẩn bị câu lệnh trong login.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau.']);
    exit();
}

$stmt->bind_param("s", $username); // Gắn username vào câu lệnh
$stmt->execute(); // Thực thi câu lệnh
$result = $stmt->get_result(); // Lấy kết quả

if ($result->num_rows === 1) { // Nếu tìm thấy một người dùng
    $user = $result->fetch_assoc(); // Lấy thông tin người dùng

    // Xác minh mật khẩu đã hash với mật khẩu nhập vào
    if (password_verify($password, $user['password'])) {
        // Đăng nhập thành công, lưu thông tin người dùng vào session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['loggedin'] = true; // RẤT QUAN TRỌNG ĐỂ index.php CÓ THỂ KIỂM TRA

        echo json_encode([
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);
        exit(); // Thêm exit()
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
        exit(); // Thêm exit()
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
    exit(); // Thêm exit()
}

$stmt->close(); // Những dòng này sẽ không được thực thi nếu exit() đã được gọi ở trên.
$conn->close(); // Tuy nhiên, giữ lại cho trường hợp luồng code không gọi exit sớm.
?>