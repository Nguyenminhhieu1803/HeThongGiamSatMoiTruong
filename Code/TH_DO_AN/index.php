<?php
// TH_DO_AN/index.php - Trang chủ ứng dụng sau khi đăng nhập

// Bao gồm file cấu hình chung của ứng dụng (bao gồm cài đặt lỗi và CORS)
require_once 'app_config.php'; // Đảm bảo app_config.php nằm cùng thư mục

session_start(); // Bắt đầu session để truy cập thông tin đăng nhập

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Nếu chưa đăng nhập hoặc session không hợp lệ, chuyển hướng về trang đăng nhập
    header('Location: ../Login/login_register.html');
    exit();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Giám Sát Nhiệt Độ và Độ Ẩm</title>
    <link rel="stylesheet" href="main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luxon@3.x.x/build/global/luxon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.x.x"></script>

    </head>
<body>
    <header>
    <div class="header-content-wrapper">
        <div class="header-left-empty">
            </div>
        
        <div class="header-right-section">
            <div class="auth-buttons">
                <button id="logout-button">Đăng Xuất</button>
                <span id="welcome-message" style="color: black; margin-right: 10px;">Xin chào, <span id="username-display"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!</span>
            </div>

            <nav id="main-nav">
                <ul>
                    <li data-section="current-data" class="active"><a href="#">Dữ liệu Hiện Tại</a></li>
                    <li data-section="history-data"><a href="#">Lịch Sử Dữ Liệu</a></li>
                    <li data-section="alerts"><a href="#">Cảnh Báo</a></li>
                    <li data-section="user-management" class="<?php echo ($_SESSION['role'] === 'admin') ? '' : 'hidden'; ?>"><a href="#">Quản Lý Người Dùng</a></li>
                    <li data-section="settings"><a href="#">Thông tin thiết bị</a></li>
                </ul>
            </nav>
        </div>
    </div>
</header>

    <main>
        <section id="current-data" class="active-section">
            <div class="current-data-header-controls">
                <div class="device-selection-main">
                    <label for="device-selector" class="block text-gray-700 text-sm font-bold mb-2">Chọn thiết bị:</label> 
                    <select id="device-selector" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">-- Chọn thiết bị --</option>
                        </select>
                </div>
                
                <div class="dht-toggle-control">
                    <h3>Điều khiển Cảm biến DHT</h3>
                    <button id="toggleDhtButton" class="dht-on">Đang đo</button>
                </div>
            </div>
            <h2>Dữ liệu Hiện Tại</h2>
            <div class="current-data-grid">
                <div class="data-widget temperature-widget">
                    <div class="widget-header">Nhiệt độ</div>
                    <div class="widget-content">
                        <span id="currentTemperatureValue" class="data-value">N/A</span>
                        <span id="currentTemperatureUnit" class="data-unit"></span>
                    </div>
                </div>
                <div class="data-widget humidity-widget">
                    <div class="widget-header">Độ ẩm</div>
                    <div class="widget-content">
                        <span id="currentHumidityValue" class="data-value">N/A</span>
                        <span id="currentHumidityUnit" class="data-unit">%</span>
                    </div>
                </div>
            </div>
            <div class="data-item timestamp-item">
                <span class="label">Cập nhật lúc:</span>
                <span id="lastUpdatedTime" class="value">N/A</span>
            </div>

            <div class="settings-grid">
    <div class="setting-group">
        <h3>Đơn Vị Đo</h3>
        <label for="temperatureUnitSelect">Đơn vị nhiệt độ:</label>
        <select id="temperatureUnitSelect">
            <option value="C">°C</option>
            <option value="F">°F</option>
        </select>
    </div>

    <div class="setting-group">
        <h3>Tần Suất Cập Nhật</h3>
        <label for="update-frequency">Tần suất (giây):</label>
        <input type="number" id="update-frequency" value="5">
        <button id="save-frequency">Save</button>
    </div>
</div>
<div id="current-data-message" class="info-message"></div>
</section>

        <section id="history-data" class="hidden-section">
            <h2>Lịch Sử Dữ Liệu</h2>
            <div class="controls">
                <label for="history-period">Chọn khoảng thời gian:</label>
                <select id="history-period">
                    <option value="1h">1 giờ qua</option>
                    <option value="24h">24 giờ qua</option>
                    <option value="7d">7 ngày qua</option>
                    <option value="30d">30 ngày qua</option>
                    <option value="custom">Tùy chỉnh</option>
                </select>
                <div id="custom-period" class="hidden">
                    <label for="start-date">Từ ngày:</label>
                    <input type="date" id="start-date">
                    <label for="end-date">Đến ngày:</label>
                    <input type="date" id="end-date">
                    <button id="apply-custom-period">Áp dụng</button>
                </div>
                <button id="export-csv">Xuất CSV</button>
            </div>
            <div id="history-chart">
                <canvas id="temperatureHumidityChart"></canvas>
            </div>
            <div id="history-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Nhiệt độ (<span id="table-temperature-unit-header">°C</span>)</th>
                            <th>Độ ẩm (%)</th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body">
                        </tbody>
                </table>    
            </div>
            <div id="history-data-message" class="info-message"></div>
        </section>

        <section id="alerts" class="hidden-section">
            <h2>Cảnh Báo</h2>
            <div class="alert-settings">
                <h3>Thiết lập ngưỡng</h3>
                <div class="setting-item">
                    <label for="temp-threshold">Ngưỡng nhiệt độ (°C):</label>
                    <input type="number" id="temp-threshold">
                </div>
                <div class="setting-item">
                    <label for="humidity-threshold">Ngưỡng độ ẩm (%):</label>
                    <input type="number" id="humidity-threshold">
                </div>
                <button id="save-thresholds">Lưu Ngưỡng</button>
            </div>
            <div class="alert-status">
                <h3>Trạng thái cảnh báo</h3>
                <div id="temperature-alert" class="alert">Nhiệt độ: <span id="temp-alert-status">Bình thường</span></div>
                <div id="humidity-alert" class="alert">Độ ẩm: <span id="humidity-alert-status">Bình thường</span></div>
            </div>
            <div class="notification-settings">
                <h3>Thông báo</h3>
                <label>
                    <input type="checkbox" id="enable-email-alerts"> Gửi thông báo qua Email
                </label>
                <div id="email-settings" class="hidden">
                    <label for="email-address">Địa chỉ Email:</label>
                    <input type="email" id="email-address">
                    <button id="save-email-settings">Lưu Email</button>
                </div>
            </div>
        </section>

        <section id="settings" class="hidden-section">
            <h2>Thông Tin Kỹ Thuật Sản Phẩm</h2>
            <div class="component-info">
                <h3>DHT11 - Cảm biến nhiệt độ và độ ẩm</h3>
                <p>DHT11 là một cảm biến nhiệt độ và độ ẩm thường được sử dụng đi kèm với một NTC chuyên dụng để đo nhiệt độ và một bộ vi điều khiển 8 bit để xuất ra các giá trị nhiệt độ và độ ẩm dưới dạng dữ liệu nối tiếp.</p>
                <div class="image-container">
                    <img src="Images/Picture1.png" alt="Sơ đồ chân và cấu hình DHT11">
                </div>
                <h4>Định dạng sơ đồ chân DHT11 và cấu hình:</h4>
                <ul>
                    <li><strong>VCC:</strong> Nguồn điện 3.5V đến 5.5V</li>
                    <li><strong>Data:</strong> Đầu ra cả Nhiệt độ và Độ ẩm thông qua Dữ liệu nối tiếp</li>
                    <li><strong>Ground:</strong> Kết nối với mặt đất của mạch</li>
                </ul>
                <h4>Thông số kỹ thuật DHT11:</h4>
                <ul>
                    <li><strong>Điện áp hoạt động:</strong> 3.5V đến 5.5V</li>
                    <li><strong>Dòng hoạt động:</strong> 0,3mA (đo) 60uA (chế độ chờ)</li>
                    <li><strong>Đầu ra:</strong> Dữ liệu nối tiếp</li>
                    <li><strong>Phạm vi nhiệt độ:</strong> 0 ° C đến 50 ° C</li>
                    <li><strong>Phạm vi độ ẩm:</strong> 20% đến 90%</li>
                    <li><strong>Độ phân giải:</strong> Nhiệt độ và Độ ẩm đều là 16-bit</li>
                    <li><strong>Độ chính xác:</strong> ± 1 ° C và ± 1%</li>
                </ul>
            </div>

            <div class="component-info">
                <h3>Giới thiệu Vi điều khiển ESP32</h3>
                <p>ESP32 là một bộ vi điều khiển thuộc danh mục vi điều khiển trên chip công suất thấp và tiết kiệm chi phí. Hầu hết tất cả các biến thể ESP32 đều tích hợp Bluetooth và Wi-Fi chế độ kép, làm cho nó có tính linh hoạt cao, mạnh mẽ và đáng tin cậy cho nhiều ứng dụng. Nó là sự kế thừa của vi điều khiển NodeMCU ESP8266 phổ biến và cung cấp hiệu suất và tính năng tốt hơn. Bộ vi điều khiển ESP32 được sản xuất bởi Espressif Systems và được sử dụng rộng rãi trong nhiều ứng dụng khác nhau như IoT, robot và tự động hóa.</p>
                <p>ESP32 cũng được thiết kế để tiêu thụ điện năng thấp, lý tưởng cho các ứng dụng chạy bằng pin. Nó có hệ thống quản lý năng lượng cho phép nó hoạt động ở chế độ ngủ và chỉ thức dậy khi cần thiết, điều này có thể kéo dài tuổi thọ pin rất nhiều.</p>
                <div class="image-container">
                    <img src="Images/Picture2.png" alt="Sơ đồ chân ESP32">
                </div>
                <h4>Sơ đồ mạch:</h4>
                <div class="image-container">
                    <img src="Images/Picture3.png" alt="Sơ đồ mạch kết nối">
                </div>
            </div>
        </section>

        <section id="user-management" class="hidden-section">
            <h2>Quản Lý Người Dùng</h2>
            </section>

    </main>

    <footer id="bottom-header"> <div class="bottom-header-content">
            <p>&copy; 2025 Hệ Thống Giám Sát Nhiệt Độ và Độ Ẩm</p>
        </div>
    </footer>

    <script src="main.js"></script>
</body>
</html>