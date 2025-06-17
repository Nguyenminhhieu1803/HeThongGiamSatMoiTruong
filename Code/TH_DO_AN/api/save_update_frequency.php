<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
// Đường dẫn này giả định save_update_frequency.php nằm trong TH_DO_AN/api/
// và app_config.php nằm trong TH_DO_AN/ (thư mục cha của api/)
require_once '../app_config.php'; 

// Rất quan trọng: Đặt header để báo cho trình duyệt biết đây là JSON
// PHẢI ĐẢM BẢO DÒNG NÀY VÀ session_start() NẰM SAU require_once app_config.php
header('Content-Type: application/json');
session_start(); // Bắt đầu session nếu bạn sử dụng session cho việc xác thực hoặc lưu trữ

$response = ['status' => 'error', 'message' => 'Lỗi không xác định từ server.'];

// Bao gồm file kết nối cơ sở dữ liệu chung
// Đường dẫn này giả định db_connect.php nằm trong TH_DO_AN/ (thư mục cha của api/)
require_once '../db_connect.php'; 

// Kiểm tra kết nối sau khi include db_connect.php
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in save_update_frequency.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Xử lý preflight OPTIONS request (Nếu chưa được xử lý trong config_cors.php cho POST)
// (Thường thì config_cors.php đã xử lý cho tất cả methods, nên đoạn này có thể bỏ qua nếu bạn chắc chắn)
/*
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
*/

// Thiết lập charset cho kết nối (Dòng này đã có trong db_connect.php, có thể bỏ qua ở đây)
// $conn->set_charset("utf8mb4"); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true); // true để trả về mảng kết hợp

    // Kiểm tra lỗi JSON decode
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Dữ liệu JSON gửi từ client không hợp lệ: ' . json_last_error_msg();
        echo json_encode($response);
        exit();
    } 
    // KIỂM TRA CẢ 'frequency' VÀ 'device_id'
    elseif ($data && isset($data['frequency']) && isset($data['device_id'])) {
        $frequency = intval($data['frequency']); // Đảm bảo là số nguyên
        // KHÔNG CẦN real_escape_string vì đã dùng Prepared Statements
        $device_id = $data['device_id']; 

        // Kiểm tra tính hợp lệ của tần suất (ví dụ: phải là số nguyên dương)
        if ($frequency <= 0) {
            $response['message'] = 'Tần suất phải là một số nguyên dương.';
            echo json_encode($response);
            exit();
        } 
        // Kiểm tra device_id không rỗng
        elseif (empty($device_id)) {
            $response['message'] = 'ID thiết bị không được để trống.';
            echo json_encode($response);
            exit();
        }
        else {
            // Lưu tần suất vào CSDL
            $setting_name = 'update_frequency';
            // SỬ DỤNG BẢNG 'device_settings' VÀ THÊM CỘT 'device_id' VÀO TRUY VẤN
            $stmt = $conn->prepare("INSERT INTO device_settings (device_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            
            if ($stmt === false) { // Kiểm tra lỗi prepare statement
                 error_log("Prepare failed in save_update_frequency.php: " . $conn->error);
                 $response['message'] = 'Lỗi hệ thống khi chuẩn bị truy vấn.';
                 echo json_encode($response);
                 $conn->close();
                 exit();
            }

            $bind_frequency = (string)$frequency; // Ép kiểu rõ ràng thành string
            $stmt->bind_param("ssss", $device_id, $setting_name, $bind_frequency, $bind_frequency); 

            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Tần suất cập nhật cho thiết bị ' . htmlspecialchars($device_id) . ' đã được lưu: ' . $frequency . ' giây.'];
            } else {
                error_log("Execute failed in save_update_frequency.php: " . $stmt->error);
                $response = ['status' => 'error', 'message' => 'Lỗi khi lưu tần suất vào CSDL: ' . $stmt->error]; // Chi tiết lỗi từ statement
            }
            $stmt->close();
        }
    } else {
        $response['message'] = 'Dữ liệu tần suất hoặc ID thiết bị không hợp lệ (thiếu trường "frequency" hoặc "device_id" hoặc định dạng sai).';
        echo json_encode($response);
        exit();
    }
} else {
    $response['message'] = 'Yêu cầu không hợp lệ (chỉ chấp nhận phương thức POST).';
    echo json_encode($response);
    exit();
}

$conn->close(); // Đóng kết nối CSDL (dòng này sẽ được bỏ qua nếu exit() đã được gọi trước đó)
echo json_encode($response); // Trả về phản hồi JSON cuối cùng
exit(); // Đảm bảo exit() sau khi gửi phản hồi cuối cùng
?>