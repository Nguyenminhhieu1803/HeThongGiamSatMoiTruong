<?php
// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once 'app_config.php';

session_start(); // Bắt đầu session để sử dụng thông tin phiên nếu có logic xác thực

// Đặt Content-Type cho phản hồi này là JSON
header("Content-Type: application/json");

// Bao gồm file kết nối cơ sở dữ liệu
require_once 'db_connect.php';

// Kiểm tra xem biến $conn đã được thiết lập từ db_connect.php chưa
// và kiểm tra kết nối có thành công không.
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection error in get_history_data.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . ($conn->connect_error ?? 'Không thể kết nối.')]);
    exit();
}

// Lấy deviceId từ request. Đặt mặc định là null.
$deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;

// Nếu deviceId không được cung cấp, trả về lỗi ngay lập tức
if (empty($deviceId)) {
    echo json_encode(["status" => "error", "message" => "Thiếu ID thiết bị."]);
    $conn->close(); // Đóng kết nối CSDL trước khi thoát
    exit(); // Dừng thực thi script
}

// Lấy khoảng thời gian từ request.
$period = isset($_GET['period']) ? $_GET['period'] : '24h';
// Lấy tham số interval mới (đơn vị phút) từ request. Null nếu không có.
$interval_minutes = isset($_GET['interval']) ? intval($_GET['interval']) : null;


$sql_condition = ""; // Điều kiện WHERE cho SQL query
$params = [$deviceId]; // Device ID luôn là tham số đầu tiên
$types = "s"; // Kiểu dữ liệu cho deviceId

// Các biến cho SELECT và GROUP BY
$effectiveInterval = 60; // Mặc định 1 giờ (60 phút)
// Thay đổi cách khởi tạo startDateTime và endDateTime ở đây
$startDateTime = null;   
$endDateTime = null;     

// Lấy thời gian hiện tại của server (đã được đặt múi giờ)
$currentTime = new DateTime();

// Xử lý logic khoảng thời gian và interval
// Xử lý logic khoảng thời gian và interval
switch ($period) {
    case '1h':
        // Làm tròn thời gian hiện tại xuống phút gần nhất
        $endDateTime = clone $currentTime; // Clone để không ảnh hưởng đến $currentTime gốc
        $endDateTime->setTime($endDateTime->format('H'), $endDateTime->format('i'), 0); 
        $startDateTime = (clone $endDateTime)->sub(new DateInterval('PT1H')); // Trừ 1 giờ từ thời điểm đã làm tròn
        $effectiveInterval = 1; // 1 phút
        break;
    case '6h':
        $endDateTime = clone $currentTime;
        $endDateTime->setTime($endDateTime->format('H'), (floor($endDateTime->format('i') / 15) * 15), 0);
        $startDateTime = (clone $endDateTime)->sub(new DateInterval('PT6H'));
        $effectiveInterval = 15; // 15 phút
        break;
    case '12h':
        $endDateTime = clone $currentTime;
        $endDateTime->setTime($endDateTime->format('H'), (floor($endDateTime->format('i') / 30) * 30), 0);
        $startDateTime = (clone $endDateTime)->sub(new DateInterval('PT12H'));
        $effectiveInterval = 30; // 30 phút
        break;
    case '24h':
        // Làm tròn thời gian hiện tại xuống giờ gần nhất
        $endDateTime = clone $currentTime;
        $endDateTime->setTime($endDateTime->format('H'), 0, 0); // Làm tròn phút và giây về 00
        $startDateTime = (clone $endDateTime)->sub(new DateInterval('PT24H')); // Trừ 24 giờ từ thời điểm đã làm tròn
        $effectiveInterval = 60; // 1 giờ
        break;
    case '7d':
        // Làm tròn thời gian hiện tại xuống mốc 6 giờ gần nhất
        $endDateTime = clone $currentTime;
        $endDateTime->setTime(floor($endDateTime->format('H') / 6) * 6, 0, 0);
        $startDateTime = (clone $endDateTime)->sub(new DateInterval('P7D')); // Trừ 7 ngày từ thời điểm đã làm tròn
        $effectiveInterval = 6 * 60; // 6 giờ
        break;
    case '30d':
        // Làm tròn thời gian hiện tại xuống đầu ngày gần nhất
        $endDateTime = clone $currentTime;
        $endDateTime->setTime(0, 0, 0);
        $startDateTime = (clone $endDateTime)->sub(new DateInterval('P30D')); // Trừ 30 ngày từ thời điểm đã làm tròn
        $effectiveInterval = 24 * 60; // 1 ngày
        break;
    case 'custom':
        $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
        $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

        if ($startDate && $endDate) {
            try {
                $startDateTime = new DateTime($startDate . " 00:00:00");
                $endDateTime = new DateTime($endDate . " 23:59:59");
                
                if ($startDateTime > $endDateTime) {
                    throw new Exception("Ngày bắt đầu không thể sau ngày kết thúc.");
                }

                $intervalDiff = $startDateTime->diff($endDateTime);
                $totalMinutes = $intervalDiff->days * 24 * 60 + $intervalDiff->h * 60 + $intervalDiff->i;

                if ($totalMinutes <= 60) { 
                    $effectiveInterval = 1; 
                } elseif ($totalMinutes <= 6 * 60) { 
                    $effectiveInterval = 15; 
                } elseif ($totalMinutes <= 24 * 60) { 
                    $effectiveInterval = 30; 
                } elseif ($totalMinutes <= 7 * 24 * 60) { 
                    $effectiveInterval = 60; 
                } elseif ($totalMinutes <= 30 * 24 * 60) { 
                    $effectiveInterval = 6 * 60; 
                } else { 
                    $effectiveInterval = 24 * 60; 
                }

                if ($interval_minutes !== null && $interval_minutes > 0) {
                    $effectiveInterval = $interval_minutes;
                }

                if ($effectiveInterval <= 0) $effectiveInterval = 1;

            } catch (Exception $e) {
                error_log("Invalid date format for custom period in get_history_data.php: " . $e->getMessage());
                echo json_encode(["status" => "error", "message" => "Định dạng ngày không hợp lệ: " . $e->getMessage()]);
                $conn->close();
                exit();
            }

        } else {
            echo json_encode(["status" => "error", "message" => "Khoảng thời gian tùy chỉnh yêu cầu ngày bắt đầu và kết thúc."]);
            $conn->close();
            exit();
        }
        break;
    default: // Trường hợp period không hợp lệ, mặc định 24h
        $endDateTime = clone $currentTime;
        $endDateTime->setTime($endDateTime->format('H'), 0, 0);
        $startDateTime = (clone $endDateTime)->sub(new DateInterval('PT24H'));
        $effectiveInterval = 60;
        break;
}

// Chắc chắn $startDateTime và $endDateTime đã được định nghĩa
if ($startDateTime === null || $endDateTime === null) {
    echo json_encode(["status" => "error", "message" => "Lỗi xác định khoảng thời gian."]);
    $conn->close();
    exit();
}

$startSQL = $startDateTime->format('Y-m-d H:i:s');
$endSQL = $endDateTime->format('Y-m-d H:i:s');

// SỬA ĐỔI: Tính toán totalSecondsInPeriod chính xác
$intervalDiff = $startDateTime->diff($endDateTime);
$totalSecondsInPeriod = $intervalDiff->days * 24 * 3600 + $intervalDiff->h * 3600 + $intervalDiff->i * 60 + $intervalDiff->s;

// SỬA ĐỔI: Tính toán max_intervals chính xác hơn và dùng cho LIMIT
$secondsPerInterval = $effectiveInterval * 60;
if ($secondsPerInterval == 0) $secondsPerInterval = 1; // Tránh chia cho 0
$num_intervals_to_generate = (int)ceil($totalSecondsInPeriod / $secondsPerInterval);

// Dùng một số hàng lớn để tạo số (ví dụ: 1000 hàng từ INFORMATION_SCHEMA.COLUMNS)
// hoặc bạn có thể tạo một bảng numbers nhỏ trong DB nếu cần nhiều hơn
$limit_numbers_series = $num_intervals_to_generate + 5; // Cộng thêm 5 làm khoảng đệm
if ($limit_numbers_series < 60 && $effectiveInterval === 1) { // Nếu là 1h, cần ít nhất 60 điểm
    $limit_numbers_series = 65; // 60 phút + đệm
} elseif ($limit_numbers_series < 24 && $effectiveInterval === 60) { // Nếu là 24h, cần ít nhất 24 điểm
    $limit_numbers_series = 29; // 24 giờ + đệm
}
// Giới hạn không quá lớn để tránh vấn đề hiệu suất với INFORMATION_SCHEMA
if ($limit_numbers_series > 2000) $limit_numbers_series = 2000; 

// === BẮT ĐẦU PHẦN SQL MỚI ĐỂ TẠO CHUỖI THỜI GIAN VÀ LEFT JOIN (Sử dụng INFORMATION_SCHEMA.COLUMNS) ===
$sql = "
SELECT
    -- Làm tròn timestamp để đảm bảo khớp với các mốc nhóm
    DATE_FORMAT(FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(time_series.interval_start) / ({$effectiveInterval} * 60)) * ({$effectiveInterval} * 60)), '%Y-%m-%d %H:%i:%s') AS timestamp,
    AVG(sr.temperature) AS temperature,
    AVG(sr.humidity) AS humidity
FROM (
    SELECT
        -- Lấy thời gian bắt đầu làm tròn xuống mốc interval gần nhất
        FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(?) / ({$effectiveInterval} * 60)) * ({$effectiveInterval} * 60)) 
        -- Cộng thêm số phút của từng interval (n.n là cách tham chiếu cột 'n' từ bảng ảo 'n')
        + INTERVAL (n.n * {$effectiveInterval}) MINUTE AS interval_start
    FROM 
        (SELECT @n := -1) AS init_n, -- Khởi tạo biến session @n
        (SELECT @n := @n + 1 AS n FROM INFORMATION_SCHEMA.COLUMNS LIMIT {$limit_numbers_series}) AS n 
        -- INFORMATION_SCHEMA.COLUMNS là một bảng hệ thống lớn để tạo số hàng
        -- Hoặc dùng bảng số của riêng bạn nếu có
) AS time_series
LEFT JOIN sensor_readings sr ON 
    sr.device_id = ? AND 
    FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(sr.reading_time) / ({$effectiveInterval} * 60)) * ({$effectiveInterval} * 60)) = time_series.interval_start
WHERE
    -- Đảm bảo thời gian tạo ra nằm trong khoảng truy vấn ban đầu
    time_series.interval_start BETWEEN ? AND ?
GROUP BY
    timestamp
ORDER BY
    timestamp ASC;
";

// Cập nhật tham số binding
// Tham số: $startSQL (cho FROM_UNIXTIME), $deviceId, $startSQL (cho BETWEEN), $endSQL (cho BETWEEN)
$params_sql_bind = [$startSQL, $deviceId, $startSQL, $endSQL];
$types_sql_bind = "ssss"; // string (startSQL), string (deviceId), string (startSQL), string (endSQL)

// --- DEBUG LOGS ---
error_log("DEBUG SQL: Period=" . $period . ", DeviceID=" . $deviceId . ", Interval=" . $effectiveInterval . " minutes");
error_log("DEBUG SQL: Start=" . $startSQL . ", End=" . $endSQL);
error_log("DEBUG SQL: totalSecondsInPeriod=" . $totalSecondsInPeriod . ", num_intervals_to_generate=" . $num_intervals_to_generate . ", limit_numbers_series=" . $limit_numbers_series);
error_log("DEBUG SQL: Query: " . $sql);
error_log("DEBUG SQL: Bind Params: " . json_encode($params_sql_bind));
// --- END DEBUG LOGS ---

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("Prepare failed in get_history_data.php: " . $conn->error . " SQL: " . $sql);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống. Vui lòng thử lại sau. (Prepare Failed)"]);
    $conn->close();
    exit();
}

// Binding các tham số
// Bắt buộc sử dụng call_user_func_array cho số lượng tham số động
$bind_names = [$types_sql_bind];
for ($i = 0; $i < count($params_sql_bind); $i++) {
    $bind_names[] = &$params_sql_bind[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);


$stmt->execute();
$result = $stmt->get_result();

$data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = [
            "timestamp" => $row["timestamp"], // Sử dụng timestamp từ LEFT JOIN
            "temperature" => ($row["temperature"] !== null) ? (float) $row["temperature"] : null, 
            "humidity" => ($row["humidity"] !== null) ? (float) $row["humidity"] : null     
        ];
    }
    echo json_encode(["status" => "success", "data" => $data, "interval_minutes" => $effectiveInterval]); // Trả về interval
    exit(); 
} else {
    // Trả về status success nhưng data rỗng nếu không tìm thấy bản ghi
    echo json_encode(["status" => "success", "message" => "Không tìm thấy dữ liệu lịch sử cho thiết bị: " . htmlspecialchars($deviceId) . " trong khoảng thời gian đã chọn.", "data" => [], "interval_minutes" => $effectiveInterval]);
    exit(); 
}

$stmt->close();
$conn->close();
?>