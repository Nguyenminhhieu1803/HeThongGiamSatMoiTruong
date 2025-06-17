<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../app_config.php'; 

// Rất quan trọng: Đặt header để báo cho trình duyệt biết đây là JSON
header('Content-Type: application/json');
session_start(); // Bắt đầu session nếu bạn sử dụng session cho việc xác thực hoặc lưu trữ

$response = ['status' => 'error', 'message' => 'Lỗi không xác định từ server.'];

// Bao gồm file kết nối cơ sở dữ liệu chung
require_once '../db_connect.php'; 

// Kiểm tra kết nối sau khi include db_connect.php
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Thiết lập charset cho kết nối (Đã có trong db_connect.php, có thể bỏ qua ở đây)
// $conn->set_charset("utf8mb4"); 

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Lấy device_id từ query parameter (ví dụ: ?device_id=ESP32_Sensor_01)
    // KHÔNG CẦN real_escape_string vì đã dùng Prepared Statements
    $device_id = isset($_GET['device_id']) ? $_GET['device_id'] : '';

    if (empty($device_id)) {
        $response['message'] = 'Thiếu ID thiết bị để lấy tần suất cập nhật.';
        echo json_encode($response); // Trả về phản hồi lỗi ngay
        exit(); 
    } else {
        try {
            $setting_name = 'update_frequency';
            // Truy vấn để lấy giá trị tần suất cho device_id cụ thể từ bảng 'device_settings'
            $stmt = $conn->prepare("SELECT setting_value FROM device_settings WHERE device_id = ? AND setting_name = ?");
            if ($stmt === false) { // Thêm kiểm tra lỗi prepare statement
                 error_log("Prepare failed in get_update_frequency.php: " . $conn->error);
                 $response['message'] = 'Lỗi hệ thống khi chuẩn bị truy vấn.';
                 echo json_encode($response);
                 $conn->close();
                 exit();
            }
            $stmt->bind_param("ss", $device_id, $setting_name);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row) {
                $response['status'] = 'success';
                $response['frequency'] = (int)$row['setting_value']; // Trả về dạng số nguyên
            } else {
                $response['message'] = 'Không tìm thấy tần suất cập nhật đã lưu cho thiết bị này. Sử dụng giá trị mặc định.';
                $response['frequency'] = 5; // Trả về giá trị mặc định nếu không tìm thấy
            }

        } catch (Exception $e) {
            error_log('Lỗi PHP khi lấy tần suất: ' . $e->getMessage());
            $response['message'] = 'Lỗi server khi lấy tần suất. Chi tiết lỗi: ' . $e->getMessage();
        }
    }
} else {
    $response['message'] = 'Yêu cầu không hợp lệ (chỉ chấp nhận phương thức GET).';
}

$conn->close(); // Đóng kết nối CSDL
echo json_encode($response); // Trả về phản hồi JSON cuối cùng
exit(); // Đảm bảo exit() sau khi gửi phản hồi cuối cùng
?>