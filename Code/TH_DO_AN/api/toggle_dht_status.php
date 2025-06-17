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
    error_log("Database connection failed in toggle_dht_status.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true); // true để trả về mảng kết hợp

    // Kiểm tra lỗi JSON decode
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Dữ liệu JSON gửi từ client không hợp lệ: ' . json_last_error_msg();
        echo json_encode($response);
        exit();
    } 
    // Kiểm tra các trường bắt buộc
    elseif ($data && isset($data['status']) && isset($data['device_id'])) {
        $status = (bool)$data['status']; // Chuyển đổi về boolean true/false
        $device_id = $data['device_id']; // ID của thiết bị

        // Kiểm tra device_id không rỗng
        if (empty($device_id)) {
            $response['message'] = 'ID thiết bị không được để trống.';
            echo json_encode($response);
            exit();
        } else {
            // Lưu trạng thái vào CSDL trong bảng device_settings
            $setting_name = 'dht_enabled';
            $bind_status_value = $status ? 'true' : 'false'; // Lưu dưới dạng chuỗi 'true'/'false'

            $stmt = $conn->prepare("INSERT INTO device_settings (device_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            
            if ($stmt === false) { // Kiểm tra lỗi prepare statement
                 error_log("Prepare failed in toggle_dht_status.php: " . $conn->error);
                 $response['message'] = 'Lỗi hệ thống khi chuẩn bị truy vấn.';
                 echo json_encode($response);
                 $conn->close();
                 exit();
            }

            $stmt->bind_param("ssss", $device_id, $setting_name, $bind_status_value, $bind_status_value); 

            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Trạng thái DHT cho thiết bị ' . htmlspecialchars($device_id) . ' đã được cập nhật thành: ' . ($status ? 'Bật' : 'Tắt')];
            } else {
                error_log("Execute failed in toggle_dht_status.php: " . $stmt->error);
                $response = ['status' => 'error', 'message' => 'Lỗi khi lưu trạng thái DHT vào CSDL: ' . $stmt->error]; // Chi tiết lỗi từ statement
            }
            $stmt->close();
        }
    } else {
        $response['message'] = 'Dữ liệu trạng thái hoặc ID thiết bị không hợp lệ (thiếu trường "status" hoặc "device_id" hoặc định dạng sai).';
        echo json_encode($response);
        exit();
    }
} else {
    $response['message'] = 'Yêu cầu không hợp lệ (chỉ chấp nhận phương thức POST).';
    echo json_encode($response);
    exit();
}

$conn->close(); 
echo json_encode($response); 
exit(); 
?>