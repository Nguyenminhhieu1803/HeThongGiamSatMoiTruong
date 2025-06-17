<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
// Đường dẫn này giả định export_data_csv.php nằm trong TH_DO_AN/api/
// và app_config.php nằm trong TH_DO_AN/ (thư mục cha của api/)
require_once __DIR__ . '/../app_config.php'; 

// Thiết lập header cho file CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sensor_data_export.csv"');

// Bao gồm file kết nối cơ sở dữ liệu
// Đường dẫn này giả định db_connect.php nằm trong TH_DO_AN/ (tức là một cấp trên TH_DO_AN/api/)
require_once __DIR__ . '/../db_connect.php'; 

// Kiểm tra xem biến $conn đã được thiết lập từ db_connect.php và có thành công không.
if (!isset($conn) || $conn->connect_error) {
    // Ghi lỗi vào log và dừng script với thông báo đơn giản.
    error_log("Database connection failed in export_data_csv.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    die("Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại sau.");
}

// Lấy period và deviceId từ tham số GET
$period = $_GET['period'] ?? '24h';
// SỬA ĐỔI DÒNG NÀY: Thay 'deviceId' bằng 'device_id' (chữ thường) để khớp với main.js
$deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null; 

$startDate = $_GET['startDate'] ?? null;
$endDate = $_GET['endDate'] ?? null;

// Xử lý deviceId rỗng (theo yêu cầu nhất quán với các API khác)
if (empty($deviceId)) {
    error_log("Missing device ID in export_data_csv.php.");
    die("Lỗi: Thiếu ID thiết bị để xuất dữ liệu."); // Dòng này sẽ không còn được kích hoạt nếu device_id được truyền đúng
}

// Logic cho truy vấn SQL
$sql = "SELECT reading_time, temperature, humidity FROM sensor_readings WHERE device_id = ?"; // Đổi tên bảng và cột
$params = [$deviceId];
$types = "s"; // s for string (device_id)

$currentTime = new DateTime(); // Khởi tạo DateTime để tính khoảng thời gian
$interval = '';

switch ($period) {
    case '1h':
        $interval = '1 HOUR';
        break;
    case '6h':
        $interval = '6 HOUR';
        break;
    case '12h':
        $interval = '12 HOUR';
        break;
    case '24h':
        $interval = '24 HOUR';
        break;
    case '7d':
        $interval = '7 DAY';
        break;
    case '30d':
        $interval = '30 DAY';
        break;
    case 'custom':
        if ($startDate && $endDate) {
            // Validate and sanitize dates
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            if ($start > $end) {
                die("Lỗi: Ngày bắt đầu không thể sau ngày kết thúc."); // Cập nhật thông báo lỗi
            }
            $sql .= " AND reading_time BETWEEN ? AND ?"; // Đổi tên cột
            $params[] = $start->format('Y-m-d H:i:s');
            $params[] = $end->format('Y-m-d H:i:s');
            $types .= "ss"; // Add two more 's' for string dates
        } else {
            die("Lỗi: Khoảng thời gian tùy chỉnh yêu cầu ngày bắt đầu và kết thúc."); // Cập nhật thông báo lỗi
        }
        break;
    default:
        $interval = '24 HOUR'; // Mặc định 24 giờ nếu không khớp
        break;
}

if ($interval !== '') {
    $sql .= " AND reading_time >= DATE_SUB(NOW(), INTERVAL $interval)"; // Đổi tên cột
}

$sql .= " ORDER BY reading_time ASC"; // Đổi tên cột

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Failed to prepare statement in export_data_csv.php: " . $conn->error . " SQL: " . $sql);
    die("Lỗi: Không thể chuẩn bị câu lệnh SQL. Vui lòng thử lại sau."); // Cập nhật thông báo lỗi
}

// Sử dụng call_user_func_array để bind_param với số lượng tham số động
if (!empty($params)) {
    $bind_names = [$types];
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$result = $stmt->get_result();

$output = fopen('php://output', 'w'); // Mở output stream

// Ghi tiêu đề CSV (cập nhật nhãn cho người dùng)
fputcsv($output, ['Thời gian đọc', 'Nhiệt độ (C)', 'Độ ẩm (%)']); // Hoặc giữ tiếng Anh nếu muốn

// Ghi dữ liệu
while ($row = $result->fetch_assoc()) {
    // Đảm bảo lấy đúng các cột theo tên mới
    fputcsv($output, [$row['reading_time'], $row['temperature'], $row['humidity']]); 
}

fclose($output); // Đóng output stream

$stmt->close();
$conn->close();
exit(); // Đảm bảo exit() để dừng script
?>