<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../../../TH_DO_AN/app_config.php'; 

// Đặt header để trình duyệt biết đây là phản hồi JSON
header('Content-Type: application/json');

// Bao gồm file kết nối database chung
require_once '../../../TH_DO_AN/db_connect.php';

// Kiểm tra kết nối sau khi include db_connect.php
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection error in register.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Xử lý preflight OPTIONS request (phải ĐẶT TRƯỚC mọi logic và session_start() nếu có)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Chỉ chấp nhận phương thức POST cho đăng ký
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ. Chỉ chấp nhận phương thức POST cho đăng ký.']);
    exit();
}

// Lấy dữ liệu JSON từ request body
$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Kiểm tra dữ liệu đầu vào (Validation mạnh mẽ)
if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập và mật khẩu không được để trống.']);
    exit();
}

// --- SERVER-SIDE VALIDATION THÊM CHO USERNAME ---
if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập phải từ 3-30 ký tự và chỉ chứa chữ cái, số, hoặc gạch dưới.']);
    exit();
}

// --- SERVER-SIDE VALIDATION THÊM CHO PASSWORD ---
if (strlen($password) < 6) { // Tối thiểu 6 ký tự (phải khớp với JS frontend)
    echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.']);
    exit();
}
// Có thể thêm kiểm tra độ phức tạp mật khẩu (nâng cao):
// if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
//     echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải chứa ít nhất một chữ hoa, một chữ thường, một số và một ký tự đặc biệt.']);
//     exit();
// }

// Hash mật khẩu trước khi lưu vào database để bảo mật
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Mặc định vai trò là 'user' cho người dùng đăng ký
$role = 'user';

// Chuẩn bị câu lệnh SQL để tránh SQL Injection
$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");

// Kiểm tra xem câu lệnh có được chuẩn bị thành công không
if ($stmt === false) {
    error_log("Lỗi chuẩn bị câu lệnh trong register.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau.']);
    exit();
}

// Gắn tham số vào câu lệnh đã chuẩn bị
$stmt->bind_param("sss", $username, $password_hash, $role);

// Thực thi câu lệnh
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công!']);
    exit();
} else {
    // Kiểm tra lỗi nếu username đã tồn tại (lỗi mã 1062)
    if ($conn->errno == 1062) {
        echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.']);
        exit();
    } else {
        error_log("Lỗi thực thi câu lệnh INSERT trong register.php: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Đăng ký thất bại: Đã xảy ra lỗi không xác định.']);
        exit();
    }
}

// Đóng câu lệnh và kết nối database (các dòng này sẽ không được thực thi nếu exit() đã được gọi ở trên)
$stmt->close();
$conn->close();
?>