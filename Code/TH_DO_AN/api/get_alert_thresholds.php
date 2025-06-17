<?php
require_once '../app_config.php';
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in get_alert_thresholds.php: " . ($conn->connect_error ?? 'Connection object not set.'));
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $deviceId = $_GET['device_id'] ?? null;

    if (empty($deviceId)) {
        echo json_encode(['status' => 'error', 'message' => 'Thiếu ID thiết bị.']);
        exit();
    }

    $thresholds = [
        'temp_threshold' => null,
        'humidity_threshold' => null
    ];

    try {
        $stmt = $conn->prepare("SELECT setting_name, setting_value FROM device_settings WHERE device_id = ? AND (setting_name = 'temp_threshold' OR setting_name = 'humidity_threshold')");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $deviceId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $thresholds[$row['setting_name']] = $row['setting_value'];
        }

        echo json_encode(['status' => 'success', 'thresholds' => $thresholds]);

        $stmt->close();
    } catch (Exception $e) {
        error_log("Error in get_alert_thresholds.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ.']);
}

$conn->close();
exit();
?>