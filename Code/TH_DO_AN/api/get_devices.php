<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../app_config.php'; 

// Đặt Content-Type cho phản hồi này là JSON
header('Content-Type: application/json');

// Bao gồm file kết nối cơ sở dữ liệu
// Vì get_devices.php nằm trong TH_DO_AN/api/ và db_connect.php nằm trong TH_DO_AN/
// nên đường dẫn cần đi ra ngoài một cấp để đến db_connect.php
require_once '../db_connect.php'; 

// Kiểm tra kết nối sau khi include db_connect.php
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection error in get_devices.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

$response = ['success' => false, 'devices' => [], 'message' => ''];

try {
    // Lựa chọn 1: KHUYÊN DÙNG - Sử dụng bảng 'devices' riêng với 'device_id' và 'device_name'
    // Đã COMMENT OUT toàn bộ phần này vì bảng 'devices' không tồn tại.
    /*
    $sql_devices = "SELECT device_id, device_name FROM devices ORDER BY device_name ASC";
    $stmt_devices = $conn->prepare($sql_devices);
    if ($stmt_devices === false) {
        throw new Exception("Lỗi prepare statement: " . $conn->error);
    }
    $stmt_devices->execute();
    $result_devices = $stmt_devices->get_result();

    $devices = [];
    while ($row = $result_devices->fetch_assoc()) {
        $devices[] = $row;
    }

    $response['success'] = true;
    $response['devices'] = $devices;
    $response['message'] = 'Tải danh sách thiết bị thành công.';

    $stmt_devices->close();
    */

    // Lựa chọn 2: SỬ DỤNG NẾU KHÔNG CÓ BẢNG 'devices' RIÊNG
    // Nếu bạn CHỈ lưu device_id trong bảng sensor_readings hoặc device_settings
    // Chúng ta sẽ lấy device_id từ bảng device_settings theo thông tin bạn đã cung cấp
    $sql_devices = "SELECT DISTINCT device_id FROM sensor_readings ORDER BY device_id ASC";
    $stmt_devices = $conn->prepare($sql_devices);
    if ($stmt_devices === false) { 
        throw new Exception("Lỗi prepare statement: " . $conn->error);
    }
    $stmt_devices->execute();
    $result_devices = $stmt_devices->get_result();

    $devices = [];
    while ($row = $result_devices->fetch_assoc()) {
        $devices[] = [
            'device_id' => $row['device_id'],
            'device_name' => 'Thiết bị: ' . $row['device_id'] // Tạo tên hiển thị từ ID
        ];
    }
    $response['success'] = true;
    $response['devices'] = $devices;
    $response['message'] = 'Tải danh sách thiết bị thành công.';
    $stmt_devices->close();

} catch (Exception $e) {
    $response['message'] = 'Lỗi truy vấn cơ sở dữ liệu: ' . $e->getMessage();
    error_log("Lỗi trong get_devices.php: " . $e->getMessage()); // Ghi log lỗi vào server log
} finally {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) { // Đảm bảo $conn là đối tượng MySQLi hợp lệ trước khi đóng
        $conn->close(); 
    }
}

echo json_encode($response);
exit(); // Đảm bảo exit() để dừng script sau khi gửi JSON
?>