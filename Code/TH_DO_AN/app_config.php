<?php
// TH_DO_AN/app_config.php - Cấu hình chung cho ứng dụng

// --- Cấu hình hiển thị lỗi PHP ---
// Trong môi trường Production, nên tắt hiển thị lỗi trực tiếp và chỉ ghi vào log.
// Đối với môi trường phát triển (development), bạn có thể tạm thời bật lại (ini_set('display_errors', 1);)
ini_set('display_errors', 1); // Tắt hiển thị lỗi trên trình duyệt
ini_set('display_startup_errors', 1); // Tắt hiển thị lỗi khi khởi động
error_reporting(E_ALL); // Bật tất cả các loại lỗi để ghi vào log (ví dụ: php_error.log)

// --- Bao gồm cấu hình CORS ---
// Đường dẫn này giả định config_cors.php nằm ở thư mục cha của TH_DO_AN/ (tức là htdocs/)
require_once __DIR__ . '/../config_cors.php'; 

// --- Các cài đặt chung khác của ứng dụng (nếu có) ---
// Ví dụ: múi giờ mặc định cho PHP
date_default_timezone_set('Asia/Ho_Chi_Minh'); 
?>