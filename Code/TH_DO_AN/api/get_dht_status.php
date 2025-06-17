<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once '../app_config.php'; 

// Đặt Content-Type cho phản hồi này là text/plain vì ESP32 đang mong đợi "true" hoặc "false"
// Nếu bạn muốn trả về JSON cho ESP32, hãy thay đổi thành 'application/json'
// và sửa code ESP32 để parse JSON. Hiện tại, tôi giữ nguyên như đã thảo luận.
header('Content-Type: text/plain'); 

// Bao gồm file kết nối cơ sở dữ liệu
require_once '../db_connect.php'; 

// Kiểm tra kết nối
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in get_dht_status.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    // Trả về 'true' để không làm gián đoạn phần cứng nếu có lỗi DB, hoặc một giá trị mặc định an toàn.
    echo 'true'; // Mặc định là bật nếu có lỗi kết nối CSDL
    exit();
}

// Lấy device_id từ tham số GET (để tương thích với ESP32)
// Nếu không có, bạn cần một device_id mặc định hoặc xử lý lỗi.
// Trong trường hợp này, ESP32 sẽ gửi request mà không có tham số GET,
// nên chúng ta sẽ lấy giá trị mặc định đã lưu.
// HOẶC: Nếu ESP32 GỬI device_id qua GET: $deviceId = $_GET['device_id'] ?? 'default_device_id';
// Hiện tại, ESP32 của bạn không gửi device_id, nên chúng ta sẽ lấy từ cài đặt chung.
// Nếu bạn có nhiều ESP32, bạn CẦN thiết bị gửi device_id trong request.

// Lấy trạng thái từ CSDL
$setting_name = 'dht_enabled';
$deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null; // Lấy device_id từ GET nếu có

$stmt = null;
$result = null;
$row = null;

try {
    if ($deviceId) {
        // Nếu có device_id được cung cấp, lấy cài đặt riêng cho thiết bị đó
        $stmt = $conn->prepare("SELECT setting_value FROM device_settings WHERE device_id = ? AND setting_name = ?");
        if ($stmt === false) {
             throw new Exception("Prepare failed for device_specific: " . $conn->error);
        }
        $stmt->bind_param("ss", $deviceId, $setting_name);
    } else {
        // Fallback: Lấy cài đặt chung nếu không có device_id hoặc bạn chỉ có một thiết bị
        // Trong trường hợp này, giả định ESP32_Sensor_02 là device_id mặc định.
        // Đây là cách hoạt động hiện tại của firmware ESP32 của bạn.
        $default_device_id = "ESP32_Sensor_02"; // Thay bằng ID mặc định của bạn nếu có
        $stmt = $conn->prepare("SELECT setting_value FROM device_settings WHERE device_id = ? AND setting_name = ?");
        if ($stmt === false) {
             throw new Exception("Prepare failed for default_device: " . $conn->error);
        }
        $stmt->bind_param("ss", $default_device_id, $setting_name);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        // Trả về 'true' hoặc 'false' (string) dựa trên giá trị từ DB
        echo $row['setting_value'];
    } else {
        // Nếu không tìm thấy cài đặt, mặc định là 'true'
        echo 'true'; 
        // Ghi log nếu cài đặt không tìm thấy
        error_log("DHT status setting not found for device_id: " . ($deviceId ?? "default_device_id") . ". Defaulting to 'true'.");
    }
} catch (Exception $e) {
    // Trả về 'true' để không làm gián đoạn phần cứng nếu có lỗi
    echo 'true';
    error_log("Error in get_dht_status.php: " . $e->getMessage()); // Ghi log lỗi vào server log
} finally {
    if ($stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close(); 
    }
}
exit();
?>