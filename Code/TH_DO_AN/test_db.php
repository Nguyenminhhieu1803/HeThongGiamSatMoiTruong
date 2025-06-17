<?php
// test_db.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dht_sensor_db";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
echo "Kết nối database thành công!";
$conn->close();
?>