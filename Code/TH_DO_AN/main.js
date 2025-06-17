// --- Biến toàn cục để lưu trữ dữ liệu gốc ---
let currentRawCelsiusTemperature = null;
let currentHumidity = null;
let lastUpdatedTimestamp = null;
let updateIntervalId = null; // Biến để lưu ID của setInterval

// Biến toàn cục cho biểu đồ
let temperatureHumidityChart; // Đối tượng Chart.js

let deviceSelector; // Khai báo deviceSelector ở phạm vi toàn cục

// Biến trạng thái DHT (giữ nguyên)
let isDhtEnabled = true; 

// Biến cho ngưỡng cảnh báo
let tempThreshold = null;
let humidityThreshold = null;

// main.js

// ... (các hàm hiện có: updateToggleButtonUI, getInitialDhtStatus, convertCelsiusToFahrenheit, updateAuthUI, loadDeviceList, v.v.) ...

// Hàm tải ngưỡng cảnh báo cho thiết bị đã chọn
async function loadAlertThresholds(deviceId) {
    const tempThresholdInput = document.getElementById('temp-threshold');
    const humidityThresholdInput = document.getElementById('humidity-threshold');
    
    if (!deviceId) {
        tempThreshold = null;
        humidityThreshold = null;
        if (tempThresholdInput) tempThresholdInput.value = '';
        if (humidityThresholdInput) humidityThresholdInput.value = '';
        console.warn("Không có Device ID để tải ngưỡng cảnh báo.");
        return;
    }

    try {
        const response = await fetch(`api/get_alert_thresholds.php?device_id=${deviceId}`, { credentials: 'include' });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const data = await response.json();

        if (data.status === 'success' && data.thresholds) {
            tempThreshold = parseFloat(data.thresholds.temp_threshold) || null;
            humidityThreshold = parseFloat(data.thresholds.humidity_threshold) || null;

            if (tempThresholdInput) tempThresholdInput.value = tempThreshold !== null ? tempThreshold : '';
            if (humidityThresholdInput) humidityThresholdInput.value = humidityThreshold !== null ? humidityThreshold : '';
            console.log(`Ngưỡng cảnh báo cho ${deviceId} đã tải: Nhiệt độ=${tempThreshold}, Độ ẩm=${humidityThreshold}`);
        } else {
            tempThreshold = null;
            humidityThreshold = null;
            if (tempThresholdInput) tempThresholdInput.value = '';
            if (humidityThresholdInput) humidityThresholdInput.value = '';
            console.warn(`Không tìm thấy ngưỡng cảnh báo cho ${deviceId} hoặc có lỗi: ${data.message || ''}`);
        }
    } catch (error) {
        console.error('Lỗi khi tải ngưỡng cảnh báo:', error);
        tempThreshold = null;
        humidityThreshold = null;
        if (tempThresholdInput) tempThresholdInput.value = '';
        if (humidityThresholdInput) humidityThresholdInput.value = '';
    }
}

// Hàm kiểm tra và áp dụng hiệu ứng cảnh báo
function checkAndApplyAlerts(temperature, humidity) {
    const tempWidget = document.querySelector('.temperature-widget');
    const humWidget = document.querySelector('.humidity-widget');

    if (!tempWidget || !humWidget) return;

    // Kiểm tra ngưỡng nhiệt độ
    if (tempThreshold !== null && typeof temperature === 'number' && !isNaN(temperature)) {
        // Nếu nhiệt độ vượt ngưỡng HOẶC nhiệt độ thấp hơn ngưỡng nếu bạn muốn cảnh báo cả 2 chiều
        if (temperature > tempThreshold) { // Ví dụ: chỉ cảnh báo khi vượt quá
            tempWidget.classList.add('warning-active');
        } else {
            tempWidget.classList.remove('warning-active');
        }
    } else {
        tempWidget.classList.remove('warning-active'); // Gỡ bỏ nếu không có ngưỡng hoặc dữ liệu không hợp lệ
    }

    // Kiểm tra ngưỡng độ ẩm
    if (humidityThreshold !== null && typeof humidity === 'number' && !isNaN(humidity)) {
        if (humidity > humidityThreshold) { // Ví dụ: chỉ cảnh báo khi vượt quá
            humWidget.classList.add('warning-active');
        } else {
            humWidget.classList.remove('warning-active');
        }
    } else {
        humWidget.classList.remove('warning-active'); // Gỡ bỏ nếu không có ngưỡng hoặc dữ liệu không hợp lệ
    }
}

// THÊM HÀM NÀY: Hàm để cập nhật UI của nút bật/tắt DHT
function updateToggleButtonUI() {
    const toggleDhtButton = document.getElementById('toggleDhtButton');
    if (!toggleDhtButton) return; // Đảm bảo nút tồn tại trước khi cố gắng cập nhật

    if (isDhtEnabled) {
        toggleDhtButton.classList.remove('dht-off'); // Xóa class tắt
        toggleDhtButton.classList.add('dht-on');    // Thêm class bật
        toggleDhtButton.textContent = 'Đang đo';
    } else {
        toggleDhtButton.classList.remove('dht-on'); // Xóa class bật
        toggleDhtButton.classList.add('dht-off');   // Thêm class tắt
        toggleDhtButton.textContent = 'Đã tắt';
    }
}

// Hàm để lấy trạng thái DHT ban đầu từ server khi tải trang
async function getInitialDhtStatus(deviceId) { // deviceId sẽ được truyền từ nơi gọi
    if (!deviceId) {
        console.warn("getInitialDhtStatus: Device ID is empty. Cannot fetch initial status.");
        isDhtEnabled = true; // Mặc định là bật nếu không có ID để tránh lỗi.
        updateToggleButtonUI();
        return;
    }
    try {
        const response = await fetch(`api/get_dht_status.php?device_id=${deviceId}`, {credentials: 'include'});
        const statusText = await response.text(); 
        isDhtEnabled = (statusText.trim() === 'true');
        updateToggleButtonUI();
        console.log(`Initial DHT status for ${deviceId}: ${isDhtEnabled ? 'Enabled' : 'Disabled'}`);
    } catch (error) {
        console.error('Error getting initial DHT status:', error);
        isDhtEnabled = true;
        updateToggleButtonUI();
    }
}
// --- Hàm chuyển đổi từ Celsius sang Fahrenheit ---
function convertCelsiusToFahrenheit(celsius) {
    if (typeof celsius !== 'number' || isNaN(celsius)) {
        return null; // Trả về null nếu đầu vào không hợp lệ
    }
    return (celsius * 1.8) + 32;
}

// --- Hàm xử lý trạng thái đăng nhập/đăng xuất (ĐÃ SỬA ĐỔI THEO YÊU CẦU) ---
function updateAuthUI(isLoggedIn, username = '', role = '') { // THÊM THAM SỐ `role`
    const logoutButtonHeader = document.getElementById('logout-button');
    const welcomeMessageEl = document.getElementById('welcome-message');
    const usernameDisplayEl = document.getElementById('username-display');
    const userManagementTab = document.querySelector('li[data-section="user-management"]');
    // const deviceManagementTab = document.querySelector('li[data-section="device-management"]'); // Nếu có

    // CHỈ KIỂM TRA CÁC PHẦN TỬ THỰC SỰ TỒN TẠI TRÊN INDEX.HTML
    if (logoutButtonHeader && welcomeMessageEl && usernameDisplayEl) {
        if (isLoggedIn) {
            logoutButtonHeader.classList.remove('hidden');   // Hiện nút Đăng Xuất
            welcomeMessageEl.classList.remove('hidden');     // Hiện dòng Chào mừng
            usernameDisplayEl.textContent = username;
        } else {
            logoutButtonHeader.classList.add('hidden');     // Ẩn nút Đăng Xuất
            welcomeMessageEl.classList.add('hidden');       // Ẩn dòng Chào mừng
            usernameDisplayEl.textContent = '';
        }
    }

    // Hiển thị/ẩn tab quản lý người dùng dựa trên vai trò 'admin'
    if (userManagementTab) {
        if (isLoggedIn && role === 'admin') { // Chỉ hiện khi đã đăng nhập và là admin
            userManagementTab.classList.remove('hidden');
        } else {
            userManagementTab.classList.add('hidden');
        }
    }
}

// Hàm tải danh sách thiết bị từ backend và điền vào select
async function loadDeviceList() {
    const deviceSelector = document.getElementById('device-selector');
    if (!deviceSelector) {
        console.error("Không tìm thấy phần tử #device-selector.");
        return;
    }

    // Xóa tất cả các option cũ, trừ option mặc định
    deviceSelector.innerHTML = '<option value="">-- Chọn thiết bị --</option>';

    try {
        const response = await fetch('api/get_devices.php', {credentials: 'include'});
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        if (data.success && data.devices && data.devices.length > 0) {
            data.devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.device_id;
                option.textContent = device.device_name || device.device_id;
                deviceSelector.appendChild(option);
            });

            // Sau khi tải danh sách thiết bị, chọn thiết bị cuối cùng đã chọn
            const lastSelectedDeviceId = localStorage.getItem('lastSelectedDeviceId');
            if (lastSelectedDeviceId && Array.from(deviceSelector.options).some(option => option.value === lastSelectedDeviceId)) {
                deviceSelector.value = lastSelectedDeviceId;
            } else {
                // Nếu không có lastSelectedDeviceId hoặc nó không hợp lệ,
                // chọn ID của thiết bị đầu tiên từ dữ liệu nhận được
                deviceSelector.value = data.devices[0].device_id; 
            }
            
            // QUAN TRỌNG: KÍCH HOẠT SỰ KIỆN 'change' SAU KHI CHỌN THIẾT BỊ
            // Việc này sẽ làm cho deviceSelector.addEventListener('change') được chạy,
            // và đó là nơi updateCurrentData() và loadHistoryData() sẽ được gọi.
            if (deviceSelector.value) { // Chỉ kích hoạt nếu đã có giá trị được chọn
                console.log("loadDeviceList: Đã chọn thiết bị mặc định. Kích hoạt sự kiện change.");
                deviceSelector.dispatchEvent(new Event('change')); 
                
                // Lấy trạng thái DHT ban đầu cho thiết bị vừa được chọn
                // Hàm này sẽ tự gọi updateToggleButtonUI()
                getInitialDhtStatus(deviceSelector.value); 
            } else {
                // Trường hợp này xảy ra nếu deviceSelector.value bị đặt thành một giá trị không có trong danh sách
                console.warn("loadDeviceList: Không có thiết bị nào được chọn mặc định sau khi tải.");
                clearCurrentDataDisplay();
                clearHistoryDataDisplay('Không có thiết bị nào được cấu hình. Vui lòng thêm thiết bị.');
                // ĐÃ SỬA: Khi không có thiết bị, nút phải ở trạng thái "Đã tắt"
                isDhtEnabled = false; 
                updateToggleButtonUI();
            }

        } else { // Trường hợp API trả về success nhưng danh sách devices rỗng
            console.warn("Không có thiết bị nào được tìm thấy.");
            const currentDataMessageEl = document.getElementById('current-data-message');
            if (currentDataMessageEl) {
                currentDataMessageEl.textContent = 'Không có thiết bị nào được cấu hình. Vui lòng thêm thiết bị.';
                currentDataMessageEl.classList.add('no-data-message');
            }
            clearCurrentDataDisplay();
            clearHistoryDataDisplay('Không có thiết bị nào để hiển thị lịch sử.');
            // ĐÃ SỬA: Khi không có thiết bị, nút phải ở trạng thái "Đã tắt"
            isDhtEnabled = false; 
            updateToggleButtonUI();
        }
    } catch (error) { // Xử lý lỗi khi fetch hoặc parse JSON
        console.error('Lỗi tải danh sách thiết bị:', error);
        deviceSelector.innerHTML = '<option value="">Lỗi tải thiết bị</option>'; // Hiển thị lỗi trong dropdown
        const currentDataMessageEl = document.getElementById('current-data-message');
        if (currentDataMessageEl) {
            currentDataMessageEl.textContent = `Lỗi khi tải danh sách thiết bị: ${error.message}.`;
            currentDataMessageEl.classList.add('error-message');
        }
        clearCurrentDataDisplay();
        clearHistoryDataDisplay(`Lỗi khi tải lịch sử thiết bị: ${error.message}.`);
        // ĐÃ SỬA: Khi có lỗi, nút phải ở trạng thái "Đã tắt"
        isDhtEnabled = false; 
        updateToggleButtonUI();
    }
}

// Hàm cập nhật dữ liệu hiện tại
async function updateCurrentData(deviceId) {
    const currentTemperatureValueEl = document.getElementById('currentTemperatureValue');
    const currentTemperatureUnitEl = document.getElementById('currentTemperatureUnit');
    const currentHumidityValueEl = document.getElementById('currentHumidityValue');
    const lastUpdatedTimeEl = document.getElementById('lastUpdatedTime');
    const currentDataMessageEl = document.getElementById('current-data-message');
    const temperatureUnitSelectEl = document.getElementById('temperatureUnitSelect');

    // Nếu không có deviceId, xóa hiển thị và dừng cập nhật
    if (!deviceId) {
        clearCurrentDataDisplay();
        if (currentDataMessageEl) {
            currentDataMessageEl.textContent = 'Vui lòng chọn một thiết bị.';
            currentDataMessageEl.classList.add('no-data-message');
            currentDataMessageEl.classList.remove('info-message', 'error-message');
        }
        if (updateIntervalId) {
            clearInterval(updateIntervalId);
            updateIntervalId = null;
        }
        checkAndApplyAlerts(null, null); // Gỡ bỏ mọi cảnh báo khi không có thiết bị
        return;
    }

    // Hiển thị thông báo đang tải
    if (currentDataMessageEl) {
        currentDataMessageEl.textContent = 'Đang tải dữ liệu hiện tại...';
        currentDataMessageEl.classList.remove('error-message', 'no-data-message');
        currentDataMessageEl.classList.add('info-message');
    }

    try {
        const response = await fetch(`get_current_data.php?device_id=${deviceId}`, {credentials: 'include'});
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        // Xử lý khi dữ liệu thành công
        if (data.status === "success" && data.reading) {
            if (currentDataMessageEl) {
                currentDataMessageEl.textContent = '';
                currentDataMessageEl.classList.remove('info-message', 'error-message', 'no-data-message');
            }

            currentRawCelsiusTemperature = parseFloat(data.reading.temperature);
            currentHumidity = parseFloat(data.reading.humidity);
            lastUpdatedTimestamp = data.reading.reading_time;

            const selectedUnit = localStorage.getItem('selectedTemperatureUnit') || 'celsius';
            if (temperatureUnitSelectEl) {
                // Đảm bảo giá trị của select khớp với localStorage
                temperatureUnitSelectEl.value = (selectedUnit === 'fahrenheit') ? 'F' : 'C';
            }

            // Gọi hàm displayTemperature để cập nhật nhiệt độ và đơn vị
            displayTemperature(currentRawCelsiusTemperature, selectedUnit, currentTemperatureValueEl, currentTemperatureUnitEl);

            if (currentHumidityValueEl) currentHumidityValueEl.textContent = currentHumidity.toFixed(1);
            if (lastUpdatedTimeEl) {
                const dateTime = luxon.DateTime.fromSQL(lastUpdatedTimestamp);

                if (dateTime.isValid) {
                    lastUpdatedTimeEl.textContent = dateTime.toFormat('HH:mm:ss dd/MM/yyyy');
                } else {
                    console.error("Lỗi phân tích thời gian Luxon:", dateTime.invalidReason, dateTime.invalidExplanation);
                    lastUpdatedTimeEl.textContent = 'Lỗi Định Dạng Thời Gian';
                }
            }
            
            // THÊM: Kiểm tra và áp dụng hiệu ứng cảnh báo với dữ liệu mới nhất
            checkAndApplyAlerts(currentRawCelsiusTemperature, currentHumidity);

            // Khởi động lại interval cập nhật tự động với tần suất đã lưu
            const savedFrequency = localStorage.getItem('savedUpdateFrequency');
            const defaultFrequency = 5;
            const initialFrequency = Math.max(1, parseInt(savedFrequency) || defaultFrequency);
            startAutoUpdate(initialFrequency, deviceId); 

        } else { // Xử lý khi API thành công nhưng không có dữ liệu hoặc có lỗi logic
            currentRawCelsiusTemperature = null;
            currentHumidity = null;
            lastUpdatedTimestamp = null;

            if (currentDataMessageEl) {
                currentDataMessageEl.textContent = data.message || 'Không có dữ liệu hiện tại cho thiết bị này.';
                currentDataMessageEl.classList.remove('info-message', 'error-message');
                currentDataMessageEl.classList.add('no-data-message');
            }
            clearCurrentDataDisplay();
            if (updateIntervalId) {
                clearInterval(updateIntervalId);
                updateIntervalId = null;
            }
            checkAndApplyAlerts(null, null); // Gỡ bỏ mọi cảnh báo khi không có dữ liệu
        }
    } catch (error) { // Xử lý khi có lỗi mạng hoặc lỗi server
        console.error('Lỗi khi tải dữ liệu hiện tại:', error);
        currentRawCelsiusTemperature = null;
        currentHumidity = null;
        lastUpdatedTimestamp = null;

        if (currentDataMessageEl) {
            currentDataMessageEl.textContent = `Lỗi khi tải dữ liệu hiện tại: ${error.message}. Vui lòng thử lại.`;
            currentDataMessageEl.classList.remove('info-message', 'no-data-message');
            currentDataMessageEl.classList.add('error-message');
        }
        clearCurrentDataDisplay();
        if (updateIntervalId) {
            clearInterval(updateIntervalId);
            updateIntervalId = null;
        }
        checkAndApplyAlerts(null, null); // Gỡ bỏ mọi cảnh báo khi có lỗi
    }
}

// Hàm hiển thị nhiệt độ dựa trên đơn vị đã chọn (thêm element hiển thị đơn vị)
function displayTemperature(celsius, unit, displayElement, unitDisplayElement) {
    if (!displayElement) return;

    if (celsius === null || isNaN(celsius)) {
        displayElement.textContent = 'N/A';
        if (unitDisplayElement) unitDisplayElement.textContent = ''; // Ẩn đơn vị
        return;
    }

    if (unit === 'fahrenheit') {
        const fahrenheit = convertCelsiusToFahrenheit(celsius);
        displayElement.textContent = fahrenheit !== null ? fahrenheit.toFixed(1) : 'N/A';
        if (unitDisplayElement) unitDisplayElement.textContent = '°F';
    } else { // mặc định là celsius
        displayElement.textContent = celsius.toFixed(1);
        if (unitDisplayElement) unitDisplayElement.textContent = '°C';
    }
}

// Hàm để khởi tạo/tắt/bật cập nhật dữ liệu định kỳ (ĐÃ SỬA ĐỔI)
function startAutoUpdate(frequency, deviceId) { // THÊM THAM SỐ deviceId
    if (updateIntervalId) {
        clearInterval(updateIntervalId);
    }
    // Chỉ khởi tạo interval nếu có deviceId hợp lệ
    if (deviceId) { // Bây giờ deviceId đã được truyền vào
        updateIntervalId = setInterval(() => {
            updateCurrentData(deviceId); // Gọi với deviceId
        }, frequency * 1000); // Chuyển giây sang mili giây
        console.log(`Bắt đầu cập nhật tự động cho thiết bị ${deviceId} với tần suất ${frequency} giây.`);
    } else {
        console.warn("Không có Device ID để cập nhật dữ liệu định kỳ. Đã dừng cập nhật tự động.");
        updateIntervalId = null; // Đảm bảo biến là null nếu không có interval
    }
}

// Hàm để khởi tạo hoặc cập nhật biểu đồ
function createOrUpdateChart(labels, temperatures, humidities, timeUnit, selectedTemperatureUnit) {
    const ctx = document.getElementById('temperatureHumidityChart');
    if (!ctx) {
        console.error("Không tìm thấy phần tử canvas biểu đồ với ID 'temperatureHumidityChart'.");
        return;
    }
    const chartContext = ctx.getContext('2d');

    // Xác định nhãn cho trục nhiệt độ và dataset
    const temperatureLabel = selectedTemperatureUnit === 'fahrenheit' ? 'Nhiệt độ (°F)' : 'Nhiệt độ (°C)';
    const temperatureUnitSymbol = selectedTemperatureUnit === 'fahrenheit' ? '°F' : '°C';

    if (temperatureHumidityChart) {
        // Cập nhật dữ liệu cho biểu đồ hiện có
        temperatureHumidityChart.data.labels = labels;
        temperatureHumidityChart.data.datasets[0].label = temperatureLabel; // Cập nhật label nhiệt độ
        temperatureHumidityChart.data.datasets[0].data = temperatures;
        temperatureHumidityChart.data.datasets[1].data = humidities;

        // Đảm bảo spanGaps là false khi cập nhật nếu muốn thay đổi động
        temperatureHumidityChart.data.datasets[0].spanGaps = false; // THÊM DÒNG NÀY
        temperatureHumidityChart.data.datasets[1].spanGaps = false; // THÊM DÒNG NÀY


        // Cập nhật đơn vị trục X
        temperatureHumidityChart.options.scales.x.time.unit = timeUnit;

        // Cập nhật displayFormats dựa trên timeUnit
        if (timeUnit === 'minute') {
            temperatureHumidityChart.options.scales.x.time.displayFormats = { minute: 'HH:mm' };
        } else if (timeUnit === 'hour') {
            temperatureHumidityChart.options.scales.x.time.displayFormats = { hour: 'HH:mm DD/MM' };
        } else if (timeUnit === 'day') {
            temperatureHumidityChart.options.scales.x.time.displayFormats = { day: 'DD/MM' };
        }
        
        // Cập nhật nhãn trục Y
        // Không cần cập nhật trực tiếp nhãn trục Y nữa vì đã có 2 trục riêng
        temperatureHumidityChart.options.scales.yTemperature.title.text = `Nhiệt độ (${temperatureUnitSymbol})`; // Cập nhật nhãn trục nhiệt độ
        temperatureHumidityChart.options.scales.yHumidity.title.text = `Độ ẩm (%)`; // Cập nhật nhãn trục độ ẩm
        
        temperatureHumidityChart.update();
    } else {
        // Tạo biểu đồ mới
        temperatureHumidityChart = new Chart(chartContext, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: temperatureLabel, // Sử dụng label động
                        data: temperatures,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        fill: false,
                        tension: 0.1,
                        yAxisID: 'yTemperature', // GÁN DATASET NÀY VỚI TRỤC Y NHIỆT ĐỘ
                        spanGaps: false // THÊM DÒNG NÀY: Để không nối các khoảng trống (NULL)
                    },
                    {
                        label: 'Độ ẩm (%)',
                        data: humidities,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: false,
                        tension: 0.1,
                        yAxisID: 'yHumidity', // GÁN DATASET NÀY VỚI TRỤC Y ĐỘ ẨM
                        spanGaps: false // THÊM DÒNG NÀY: Để không nối các khoảng trống (NULL)
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: timeUnit,
                            tooltipFormat: 'HH:mm:ss DD/MM/YYYY',
                            displayFormats: {
                                minute: 'HH:mm',
                                hour: 'HH:mm DD/MM',
                                day: 'DD/MM',
                            }
                        },
                        title: {
                            display: true,
                            text: 'Thời gian'
                        }
                    },
                    // ĐỊNH NGHĨA TRỤC Y CHO NHIỆT ĐỘ (Bên trái)
                    yTemperature: {
                        type: 'linear', // Loại trục là tuyến tính
                        position: 'left', // Đặt ở bên trái
                        beginAtZero: false, // Không bắt đầu từ 0
                        title: {
                            display: true,
                            text: `Nhiệt độ (${temperatureUnitSymbol})` // Nhãn trục nhiệt độ
                        },
                        min : 0,
                        max : 100,
                        grid: {
                            drawOnChartArea: false // Chỉ vẽ grid cho trục này, không vẽ qua toàn bộ biểu đồ
                        }
                    },
                    // ĐỊNH NGHĨA TRỤC Y CHO ĐỘ ẨM (Bên phải)
                    yHumidity: {
                        type: 'linear', // Loại trục là tuyến tính
                        position: 'right', // Đặt ở bên phải
                        beginAtZero: true, // Độ ẩm thường bắt đầu từ 0
                        title: {
                            display: true,
                            text: 'Độ ẩm (%)' // Nhãn trục độ ẩm
                        },
                        min : 0,
                        max : 100,
                        // Có thể thêm grid: { drawOnChartArea: false } nếu không muốn grid cho cả 2 trục
                        // Nhưng thường thì grid chỉ vẽ từ trục trái.
                        grid: {
                             drawOnChartArea: false // Không vẽ grid cho trục này để tránh trùng lặp
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const timestamp = luxon.DateTime.fromMillis(context[0].parsed.x);
                                if (timeUnit === 'minute') {
                                    return timestamp.toFormat('HH:mm:ss dd/MM');
                                } else if (timeUnit === 'hour') {
                                    return timestamp.toFormat('HH:mm dd/MM/yyyy');
                                } else if (timeUnit === 'day') {
                                    return timestamp.toFormat('dd/MM/yyyy');
                                }
                                return timestamp.toFormat('HH:mm:ss dd/MM/yyyy');
                            },
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.dataset.label.includes('Nhiệt độ')) {
                                        label += context.parsed.y.toFixed(1); // Làm tròn 1 chữ số thập phân
                                    } else {
                                        label += context.parsed.y.toFixed(1);
                                    }
                                } else {
                                    label += 'Không có dữ liệu';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
}

async function loadHistoryData(period, deviceId, startDate = null, endDate = null) {
    const historyTableBody = document.getElementById('history-table-body');
    const chartCanvas = document.getElementById('temperatureHumidityChart');
    const historyDataMessageEl = document.getElementById('history-data-message');
    const historyTableContainer = document.getElementById('history-table');
    const historyChartContainer = document.getElementById('history-chart');

    // Hiển thị thông báo đang tải
    if (historyDataMessageEl) {
        historyDataMessageEl.textContent = 'Đang tải dữ liệu...';
        historyDataMessageEl.classList.remove('error-message', 'no-data-message');
        historyDataMessageEl.classList.add('info-message');
        historyDataMessageEl.style.display = 'block';
    }
    // Ẩn bảng và biểu đồ trong khi tải
    if (historyTableContainer) historyTableContainer.style.display = 'none';
    if (historyChartContainer) historyChartContainer.style.display = 'none';


    if (!deviceId) {
        clearHistoryDataDisplay('Vui lòng chọn một thiết bị để xem lịch sử dữ liệu.');
        return;
    }

    try {
        let apiUrl = `get_history_data.php?device_id=${deviceId}&period=${period}`;
        if (period === 'custom' && startDate && endDate) {
            apiUrl += `&start_date=${startDate}&end_date=${endDate}`;
        }

        const response = await fetch(apiUrl, {credentials: 'include'}); // THÊM credentials: 'include'
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        const selectedTemperatureUnit = localStorage.getItem('selectedTemperatureUnit') || 'celsius';

        if (data.status === "success" && data.data && data.data.length > 0) {
            if (historyDataMessageEl) {
                historyDataMessageEl.textContent = '';
                historyDataMessageEl.classList.remove('info-message', 'error-message', 'no-data-message');
                historyDataMessageEl.style.display = 'none';
            }

            const readings = data.data;

            let unitDisplay;
            const startLuxon = startDate ? luxon.DateTime.fromISO(startDate) : null;
            const endLuxon = endDate ? luxon.DateTime.fromISO(endDate) : null;

            if (period === '1h' || (period === 'custom' && startLuxon && endLuxon && endLuxon.diff(startLuxon, 'hours').hours <= 2)) {
                unitDisplay = 'minute';
            } else if (period === '24h' || (period === 'custom' && startLuxon && endLuxon && endLuxon.diff(startLuxon, 'hours').hours <= 24)) {
                unitDisplay = 'hour';
            } else {
                unitDisplay = 'day';
            }
            
            // Sửa đổi DÒNG NÀY: Sử dụng fromSQL() để phân tích chuỗi thời gian
            const timeLabels = readings.map(r => luxon.DateTime.fromSQL(r.timestamp).toMillis());
            
            const chartTemperatures = readings.map(r => {
                const tempC = parseFloat(r.temperature);
                return selectedTemperatureUnit === 'fahrenheit' ? convertCelsiusToFahrenheit(tempC) : tempC;
            });
            const chartHumidities = readings.map(r => parseFloat(r.humidity));

            const tableReadings = readings.map(r => ({
                reading_time: r.timestamp, // Đây là chuỗi thời gian để hiển thị trong bảng
                temperature: parseFloat(r.temperature),
                humidity: parseFloat(r.humidity)
            }));

            createOrUpdateChart(timeLabels, chartTemperatures, chartHumidities, unitDisplay, selectedTemperatureUnit);
            populateHistoryTable(tableReadings);

            // Hiển thị lại bảng và biểu đồ
            if (historyTableContainer) historyTableContainer.style.display = 'block';
            if (historyChartContainer) historyChartContainer.style.display = 'block';

        } else {
            // Không có dữ liệu
            if (temperatureHumidityChart) {
                temperatureHumidityChart.destroy();
                temperatureHumidityChart = null;
            }
            clearHistoryDataDisplay(data.message || 'Không có dữ liệu lịch sử cho khoảng thời gian hoặc thiết bị này.');
        }
    } catch (error) {
        console.error('Lỗi khi tải lịch sử dữ liệu:', error);
        if (temperatureHumidityChart) {
            temperatureHumidityChart.destroy();
            temperatureHumidityChart = null;
        }
        clearHistoryDataDisplay(`Đã xảy ra lỗi khi tải dữ liệu lịch sử: ${error.message}.`);
    }
}

// --- HÀM POPULATE HISTORY TABLE (GIỮ NGUYÊN) ---
function populateHistoryTable(readingsForTable) {
    const historyTableBody = document.getElementById('history-table-body');
    const tableUnitHeaderEl = document.getElementById('table-temperature-unit-header');

    if (!historyTableBody) {
        console.error("Lỗi: Không tìm thấy phần tử tbody của bảng lịch sử với ID 'history-table-body'.");
        return;
    }

    historyTableBody.innerHTML = '';

    const selectedUnit = localStorage.getItem('selectedTemperatureUnit') || 'celsius';
    if (tableUnitHeaderEl) {
        tableUnitHeaderEl.textContent = selectedUnit === 'fahrenheit' ? '°F' : '°C';
    }

    if (!readingsForTable || readingsForTable.length === 0) {
        const row = historyTableBody.insertRow();
        const cell = row.insertCell();
        cell.colSpan = 3;
        cell.className = 'no-data-message';
        cell.textContent = 'Không có dữ liệu lịch sử để hiển thị trong bảng.';
        return;
    }

    readingsForTable.forEach(reading => {
        const row = historyTableBody.insertRow();

        const timeCell = row.insertCell();
        const tempCell = row.insertCell();
        const humidityCell = row.insertCell();

        timeCell.textContent = luxon.DateTime.fromSQL(reading.reading_time).toFormat('HH:mm:ss dd/MM/yyyy');

        const rawTemperature = parseFloat(reading.temperature);
        if (rawTemperature !== null && !isNaN(rawTemperature)) {
            let displayTemperature;
            if (selectedUnit === 'fahrenheit') {
                displayTemperature = convertCelsiusToFahrenheit(rawTemperature);
                tempCell.textContent = displayTemperature !== null ? displayTemperature.toFixed(1) : 'N/A';
            } else {
                tempCell.textContent = rawTemperature.toFixed(1);
            }
        } else {
            tempCell.textContent = 'N/A';
        }

        const rawHumidity = parseFloat(reading.humidity);
        if (rawHumidity !== null && !isNaN(rawHumidity)) {
            humidityCell.textContent = rawHumidity.toFixed(1);
        } else {
            humidityCell.textContent = 'N/A';
        }
    });
}

// --- Hàm trợ giúp để clear hiển thị dữ liệu hiện tại (GIỮ NGUYÊN) ---
function clearCurrentDataDisplay() {
    const currentTemperatureValueEl = document.getElementById('currentTemperatureValue');
    const currentHumidityValueEl = document.getElementById('currentHumidityValue');
    const lastUpdatedTimeEl = document.getElementById('lastUpdatedTime');
    const currentTemperatureUnitEl = document.getElementById('currentTemperatureUnit');

    if (currentTemperatureValueEl) currentTemperatureValueEl.textContent = 'N/A';
    if (currentHumidityValueEl) currentHumidityValueEl.textContent = 'N/A';
    if (lastUpdatedTimeEl) lastUpdatedTimeEl.textContent = 'N/A';
    if (currentTemperatureUnitEl) currentTemperatureUnitEl.textContent = ''; // Xóa đơn vị
}

// --- Hàm trợ giúp để clear hiển thị lịch sử dữ liệu (GIỮ NGUYÊN) ---
function clearHistoryDataDisplay(message = 'Vui lòng chọn thiết bị để xem lịch sử.') {
    const historyDataMessageEl = document.getElementById('history-data-message');
    const historyTableBody = document.getElementById('history-table-body');
    const historyChartContainer = document.getElementById('history-chart');
    const historyTableContainer = document.getElementById('history-table');

    if (historyDataMessageEl) {
        historyDataMessageEl.textContent = message;
        historyDataMessageEl.classList.remove('info-message', 'error-message');
        historyDataMessageEl.classList.add('no-data-message');
        historyDataMessageEl.style.display = 'block'; // Luôn hiển thị thông báo
    }
    if (historyTableBody) {
        historyTableBody.innerHTML = '<tr><td colspan="3" class="no-data-message">' + message + '</td></tr>';
    }
    // Hủy biểu đồ nếu nó đang tồn tại
    if (temperatureHumidityChart) {
        temperatureHumidityChart.destroy();
        temperatureHumidityChart = null;
    }
    // Ẩn chart và table containers
    if (historyChartContainer) historyChartContainer.style.display = 'none';
    if (historyTableContainer) historyTableContainer.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', async function() {
    // --- 1. Lấy tham chiếu đến các phần tử DOM ---
    const navLinks = document.querySelectorAll('#main-nav ul li');
    const sections = document.querySelectorAll('main section');
    const logoutButtonHeader = document.getElementById('logout-button');

    const historyPeriodSelect = document.getElementById('history-period');
    const customPeriodDiv = document.getElementById('custom-period');
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    const applyCustomPeriodButton = document.getElementById('apply-custom-period');
    const exportCsvButton = document.getElementById('export-csv');

    deviceSelector = document.getElementById('device-selector'); // Đảm bảo deviceSelector được gán ở đây

    // Các phần tử cho việc hiển thị dữ liệu hiện tại
    const updateFrequencyInput = document.getElementById('update-frequency');
    const saveFrequencyButton = document.getElementById('save-frequency');
    const temperatureUnitSelectEl = document.getElementById('temperatureUnitSelect');

    // --- 2. Khởi tạo trạng thái đơn vị nhiệt độ ---
    const savedUnit = localStorage.getItem('selectedTemperatureUnit');
    if (savedUnit && temperatureUnitSelectEl) {
        temperatureUnitSelectEl.value = (savedUnit === 'fahrenheit') ? 'F' : 'C';
    } else if (temperatureUnitSelectEl) {
        localStorage.setItem('selectedTemperatureUnit', 'celsius');
        temperatureUnitSelectEl.value = 'C';
    }

    // *** BẮT ĐẦU KHỐI KIỂM TRA ĐĂNG NHẬP ***
    try {
        const response = await fetch('../Login/api/users/check_login.php', {credentials: 'include'});
        if (!response.ok) {
            console.error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}`);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        if (data.loggedIn) {
            updateAuthUI(true, data.username, data.role);
            await loadDeviceList(); // Tải danh sách thiết bị chỉ khi đã đăng nhập

            // Khởi tạo tần suất cập nhật ban đầu (sau khi thiết bị đã được tải)
            const savedFrequency = localStorage.getItem('savedUpdateFrequency');
            const defaultFrequency = 5;
            const initialFrequency = Math.max(1, parseInt(savedFrequency) || defaultFrequency);
            if (updateFrequencyInput) {
                updateFrequencyInput.value = initialFrequency;
            }
            // Logic startAutoUpdate ban đầu đã được chuyển vào loadDeviceList()
            // thông qua event 'change' của deviceSelector.
            // KHÔNG CẦN startAutoUpdate ở đây để tránh trùng lặp.

        } else {
            updateAuthUI(false);
            clearCurrentDataDisplay();
            clearHistoryDataDisplay('Vui lòng đăng nhập để xem dữ liệu.');
            alert('Phiên đăng nhập đã hết hạn hoặc bạn chưa đăng nhập. Vui lòng đăng nhập lại.');
            window.location.href = '../Login/login_register.html';
        }
    } catch (error) {
        console.error('Lỗi khi kiểm tra trạng thái đăng nhập từ main.js:', error);
        alert('Lỗi kết nối để xác thực. Vui lòng thử lại.');
        updateAuthUI(false);
        clearCurrentDataDisplay();
        clearHistoryDataDisplay('Lỗi tải dữ liệu. Vui lòng thử lại.');
        window.location.href = '../Login/login_register.html';
    }
    // *** KẾT THÚC KHỐI KIỂM TRA ĐĂNG NHẬP ***

    // ----------------------------------------------------------------------
    // BẮT ĐẦU CÁC EVENT LISTENERS VÀ LOGIC KHÁC (SAU KHỐI ĐĂNG NHẬP)
    // ----------------------------------------------------------------------

    // --- LOGIC CHO NÚT BẬT/TẮT DHT ---
    const toggleDhtButton = document.getElementById('toggleDhtButton');
    if (toggleDhtButton) {
        toggleDhtButton.addEventListener('click', async () => {
            isDhtEnabled = !isDhtEnabled; // Đảo ngược trạng thái UI ngay lập tức
            updateToggleButtonUI(); // Cập nhật hiển thị nút

            const currentDeviceId = deviceSelector ? deviceSelector.value : ''; 

            if (!currentDeviceId) {
                alert('Vui lòng chọn một thiết bị trước khi bật/tắt cảm biến.');
                isDhtEnabled = !isDhtEnabled; // Revert lại trạng thái nếu không có thiết bị
                updateToggleButtonUI();
                return;
            }

            try {
                const response = await fetch('api/toggle_dht_status.php', { // DÙNG ĐƯỜNG DẪN TƯƠNG ĐỐI
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ status: isDhtEnabled, device_id: currentDeviceId }),
                    credentials: 'include'
                });

                const data = await response.json();
                if (data.status === 'success') {
                    console.log(data.message);
                } else {
                    console.error('API Error:', data.message);
                    isDhtEnabled = !isDhtEnabled;
                    updateToggleButtonUI();
                    alert('Có lỗi khi cập nhật trạng thái DHT: ' + data.message);
                }
            } catch (error) {
                console.error('Network error:', error);
                isDhtEnabled = !isDhtEnabled;
                updateToggleButtonUI();
                alert('Lỗi kết nối đến server. Vui lòng thử lại.');
            }
        });
    }
    // --- KẾT THÚC LOGIC CHO NÚT BẬT/TẮT DHT ---

    // --- Xử lý chuyển đổi giữa các tab điều hướng ---
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            navLinks.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');

            const targetSectionId = this.dataset.section;
            sections.forEach(section => {
                if (section.id === targetSectionId) {
                    section.classList.remove('hidden-section');
                    section.classList.add('active-section');
                } else {
                    section.classList.add('hidden-section');
                    section.classList.remove('active-section');
                }
            });

            const currentDeviceId = deviceSelector ? deviceSelector.value : ''; 
            const currentPeriod = historyPeriodSelect ? historyPeriodSelect.value : '24h';

            if (targetSectionId === 'history-data') {
                if (updateIntervalId) {
                    clearInterval(updateIntervalId);
                    updateIntervalId = null;
                }
                // ĐÃ SỬA: Đảm bảo nút bật/tắt DHT được đặt về trạng thái tắt khi không ở tab hiện tại
                isDhtEnabled = false;
                updateToggleButtonUI();

                if (currentDeviceId) {
                    loadHistoryData(currentPeriod, currentDeviceId, startDateInput.value, endDateInput.value);
                } else {
                    clearHistoryDataDisplay('Vui lòng chọn một thiết bị để xem lịch sử.');
                }
            } else if (targetSectionId === 'current-data') {
                if (currentDeviceId) {
                    const defaultFrequency = 5; 
                    startAutoUpdate(parseInt(updateFrequencyInput.value) || defaultFrequency, currentDeviceId);
                    updateCurrentData(currentDeviceId);
                    // ĐÃ SỬA: Lấy lại trạng thái DHT khi quay lại tab Current Data
                    getInitialDhtStatus(currentDeviceId); 
                } else {
                    clearCurrentDataDisplay();
                }
                clearHistoryDataDisplay('');
            } else { // Các tab khác không liên quan đến dữ liệu cảm biến
                if (updateIntervalId) {
                    clearInterval(updateIntervalId);
                    updateIntervalId = null;
                }
                clearCurrentDataDisplay();
                clearHistoryDataDisplay('');
                // ĐÃ SỬA: Cập nhật trạng thái nút khi rời khỏi tab dữ liệu
                isDhtEnabled = false;
                updateToggleButtonUI();
            }
        });
    });

    // --- Xử lý nút Đăng Xuất ---
    if (logoutButtonHeader) {
        logoutButtonHeader.addEventListener('click', async () => {
            try {
                const response = await fetch('../Login/api/users/logout.php', { method: 'POST', credentials: 'include' }); 
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                if (result.status === 'success') {
                    alert('Đăng xuất thành công!');
                    if (updateIntervalId) {
                        clearInterval(updateIntervalId);
                        updateIntervalId = null;
                    }
                    clearCurrentDataDisplay();
                    clearHistoryDataDisplay('Vui lòng đăng nhập để xem lịch sử.');
                    if (deviceSelector) {
                        deviceSelector.value = "";
                        deviceSelector.innerHTML = '<option value="">-- Chọn thiết bị --</option>';
                    }
                    updateAuthUI(false); 
                    // ĐÃ SỬA: Cập nhật trạng thái nút khi đăng xuất
                    isDhtEnabled = false;
                    updateToggleButtonUI();

                    window.location.href = '../Login/login_register.html';
                } else {
                    alert('Đăng xuất thất bại: ' + (result.message || 'Không xác định'));
                }
            } catch (error) {
                console.error('Lỗi khi đăng xuất:', error);
                alert('Lỗi kết nối khi đăng xuất. Vui lòng thử lại.');
            }
        });
    }

    // --- Xử lý thay đổi thiết bị ---
    if (deviceSelector) {
        deviceSelector.addEventListener('change', function() {
            const selectedDeviceId = this.value;
            localStorage.setItem('lastSelectedDeviceId', selectedDeviceId);

            if (selectedDeviceId) {
                updateCurrentData(selectedDeviceId);
                // ĐÃ SỬA: Tải ngưỡng cảnh báo cho thiết bị mới
                loadAlertThresholds(selectedDeviceId); 

                const currentPeriod = historyPeriodSelect ? historyPeriodSelect.value : '24h';
                const activeSectionId = document.querySelector('.active-section')?.id;
                if (activeSectionId === 'history-data') {
                    loadHistoryData(currentPeriod, selectedDeviceId, startDateInput.value, endDateInput.value);
                }
                const defaultFrequency = 5; 
                startAutoUpdate(parseInt(updateFrequencyInput.value) || defaultFrequency, selectedDeviceId);
                // ĐÃ SỬA: Lấy trạng thái DHT khi đổi thiết bị
                getInitialDhtStatus(selectedDeviceId); 
            } else {
                clearCurrentDataDisplay();
                clearHistoryDataDisplay('Vui lòng chọn thiết bị để xem lịch sử.');
                if (updateIntervalId) {
                    clearInterval(updateIntervalId);
                    updateIntervalId = null;
                }
                // ĐÃ SỬA: Cập nhật trạng thái nút khi không có thiết bị được chọn
                isDhtEnabled = false;
                updateToggleButtonUI();
            }
        });
    }

    // --- Xử lý thay đổi khoảng thời gian lịch sử ---
    if (historyPeriodSelect) {
        historyPeriodSelect.addEventListener('change', function() {
            const selectedPeriod = this.value;
            if (selectedPeriod === 'custom') {
                if (customPeriodDiv) customPeriodDiv.classList.remove('hidden');
            } else {
                if (customPeriodDiv) customPeriodDiv.classList.add('hidden');
                const currentDeviceId = deviceSelector ? deviceSelector.value : '';
                if (currentDeviceId) {
                    loadHistoryData(selectedPeriod, currentDeviceId, null, null);
                } else {
                    clearHistoryDataDisplay('Vui lòng chọn thiết bị để xem lịch sử.');
                }
            }
        });
    }

    // --- Xử lý nút "Áp dụng" cho khoảng thời gian tùy chỉnh ---
    if (applyCustomPeriodButton) {
        applyCustomPeriodButton.addEventListener('click', function() {
            const currentDeviceId = deviceSelector ? deviceSelector.value : '';
            if (startDateInput && endDateInput && currentDeviceId) {
                if (startDateInput.value && endDateInput.value && startDateInput.value <= endDateInput.value) {
                    loadHistoryData('custom', currentDeviceId, startDateInput.value, endDateInput.value);
                } else {
                    alert('Vui lòng chọn ngày bắt đầu và ngày kết thúc hợp lệ.');
                }
            } else {
                alert('Vui lòng chọn đầy đủ ngày bắt đầu, ngày kết thúc và thiết bị.');
            }
        });
    }

    // --- Xử lý nút "Xuất CSV" ---
    if (exportCsvButton) {
        exportCsvButton.addEventListener('click', async function() {
            const currentDeviceId = deviceSelector ? deviceSelector.value : '';
            const selectedPeriod = historyPeriodSelect ? historyPeriodSelect.value : '24h';
            let start = null;
            let end = null;

            if (selectedPeriod === 'custom' && startDateInput && endDateInput) {
                start = startDateInput.value;
                end = endDateInput.value;
                if (!start || !end || start > end) {
                    alert('Vui lòng chọn ngày bắt đầu và ngày kết thúc hợp lệ cho khoảng thời gian tùy chỉnh.');
                    return;
                }
            }

            if (!currentDeviceId) {
                alert('Vui lòng chọn một thiết bị để xuất dữ liệu.');
                return;
            }

            try {
                // ĐÃ SỬA: DÙNG ĐƯỜNG DẪN TƯƠNG ĐỐI
                let exportUrl = `api/export_data_csv.php?device_id=<span class="math-inline">\{currentDeviceId\}&period\=</span>{selectedPeriod}`;
                if (start && end) {
                    exportUrl += `&start_date=<span class="math-inline">\{start\}&end\_date\=</span>{end}`;
                }

                window.location.href = exportUrl;

            } catch (error) {
                console.error('Lỗi khi xuất CSV:', error);
                alert('Đã xảy ra lỗi khi xuất dữ liệu CSV.');
            }
        });
    }

    // --- Xử lý thay đổi đơn vị nhiệt độ (C/F) ---
    if (temperatureUnitSelectEl) {
        temperatureUnitSelectEl.addEventListener('change', function() {
            const selectedUnit = this.value === 'F' ? 'fahrenheit' : 'celsius';
            localStorage.setItem('selectedTemperatureUnit', selectedUnit);

            const currentDeviceId = deviceSelector ? deviceSelector.value : '';
            if (currentDeviceId) {
                updateCurrentData(currentDeviceId);
            }

            const activeSectionId = document.querySelector('.active-section')?.id;
            if (activeSectionId === 'history-data') {
                const currentPeriod = historyPeriodSelect ? historyPeriodSelect.value : '24h';
                if (currentDeviceId) {
                    loadHistoryData(currentPeriod, currentDeviceId, startDateInput.value, endDateInput.value);
                }
            }
        });
    }

    // --- Xử lý lưu tần suất cập nhật ---
    if (saveFrequencyButton) {
        saveFrequencyButton.addEventListener('click', function() {
            const newFrequency = parseInt(updateFrequencyInput.value);
            if (newFrequency && newFrequency >= 1) {
                localStorage.setItem('savedUpdateFrequency', newFrequency);
                alert('Tần suất cập nhật đã được lưu: ' + newFrequency + ' giây.');
                const currentDeviceId = deviceSelector ? deviceSelector.value : '';
                if (currentDeviceId) {
                    const defaultFrequency = 5; 
                    startAutoUpdate(newFrequency, currentDeviceId);
                }
            } else {
                alert('Tần suất cập nhật phải là một số nguyên dương.');
                updateFrequencyInput.value = initialFrequency;
            }
        });
    }

    // --- Xử lý lưu ngưỡng cảnh báo ---
    const saveThresholdsButton = document.getElementById('save-thresholds');
    const tempThresholdInput = document.getElementById('temp-threshold');
    const humidityThresholdInput = document.getElementById('humidity-threshold');

    if (saveThresholdsButton && tempThresholdInput && humidityThresholdInput) {
        saveThresholdsButton.addEventListener('click', async () => {
            const currentDeviceId = deviceSelector ? deviceSelector.value : '';
            if (!currentDeviceId) {
                alert('Vui lòng chọn một thiết bị để lưu ngưỡng.');
                return;
            }

            const newTempThreshold = parseFloat(tempThresholdInput.value);
            const newHumidityThreshold = parseFloat(humidityThresholdInput.value);

            // Kiểm tra tính hợp lệ của ngưỡng
            if (isNaN(newTempThreshold) && tempThresholdInput.value !== '') {
                alert('Ngưỡng nhiệt độ không hợp lệ.');
                return;
            }
            if (isNaN(newHumidityThreshold) && humidityThresholdInput.value !== '') {
                alert('Ngưỡng độ ẩm không hợp lệ.');
                return;
            }
            
            // Xử lý trường hợp người dùng xóa giá trị để gỡ bỏ ngưỡng
            const finalTempThreshold = tempThresholdInput.value === '' ? null : newTempThreshold;
            const finalHumidityThreshold = humidityThresholdInput.value === '' ? null : newHumidityThreshold;

            try {
                const response = await fetch('api/save_alert_thresholds.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        device_id: currentDeviceId,
                        temp_threshold: finalTempThreshold,
                        humidity_threshold: finalHumidityThreshold
                    }),
                    credentials: 'include'
                });

                const data = await response.json();
                if (data.status === 'success') {
                    alert('Ngưỡng cảnh báo đã được lưu thành công!');
                    tempThreshold = finalTempThreshold; // Cập nhật biến toàn cục
                    humidityThreshold = finalHumidityThreshold; // Cập nhật biến toàn cục
                    checkAndApplyAlerts(currentRawCelsiusTemperature, currentHumidity); // Kiểm tra lại cảnh báo
                } else {
                    alert('Lỗi khi lưu ngưỡng: ' + (data.message || 'Không xác định.'));
                }
            } catch (error) {
                console.error('Lỗi mạng khi lưu ngưỡng:', error);
                alert('Lỗi kết nối đến server khi lưu ngưỡng. Vui lòng thử lại.');
            }
        });
    }

    // Khởi tạo trạng thái ban đầu của custom-period div
    if (historyPeriodSelect && customPeriodDiv) {
        if (historyPeriodSelect.value === 'custom') {
            customPeriodDiv.classList.remove('hidden');
        } else {
            customPeriodDiv.classList.add('hidden');
        }
    }

}); // Kết thúc DOMContentLoaded đây là bản hoàn chỉnh của hàm DOM