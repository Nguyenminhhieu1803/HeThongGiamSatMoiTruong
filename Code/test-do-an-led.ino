#include <WiFi.h>            // Thư viện Wi-Fi cho ESP32
#include <HTTPClient.h>      // Thư viện HTTP client cho ESP32
#include "DHT.h"             // Thư viện DHT sensor
#include <Adafruit_Sensor.h> // Thư viện Adafruit Unified Sensor (dependency của DHT)
#include <WiFiUdp.h>         // Thư viện UDP cho NTP client
#include <NTPClient.h>       // Thư viện NTP client
#include <ArduinoJson.h>     // Thư viện ArduinoJson để tạo JSON payload

// --- CẤU HÌNH MẠNG VÀ SERVER CỦA BẠN ---
const char* ssid = "Tang 4";    // Tên mạng Wi-Fi của bạn
const char* password = "88888888";        // Mật khẩu Wi-Fi của bạn

// Thay thế bằng ĐỊA CHỈ IP CỦA MÁY TÍNH CHẠY XAMPP CỦA BẠN
const char* serverAddress = "192.168.4.106"; 

// Đường dẫn đến file PHP để POST dữ liệu cảm biến
const char* postDataApiUrl = "/TH_DO_AN/post_data.php"; 
// Đường dẫn đến API để lấy trạng thái bật/tắt DHT từ web
const char* getDhtStatusApiUrl = "http://192.168.4.106/TH_DO_AN/api/get_dht_status.php";

// --- CẤU HÌNH CẢM BIẾN VÀ LED ---
#define DHTPIN 4      // Chân GPIO4 của ESP32 kết nối với chân Data của DHT11 (tương ứng D4)
#define DHTTYPE DHT11 // Loại cảm biến DHT

#define LED_PIN 15    // Chân GPIO15 của ESP32 kết nối với đèn LED thông báo (tương ứng D15)

String deviceId = "ESP32_Sensor_02"; // ID duy nhất cho cảm biến này

// --- THỜI GIAN VÀ BIẾN TRẠNG THÁI ---
const long postingInterval = 1000;      // Tần suất gửi dữ liệu cảm biến (1 giây)
unsigned long lastDataPostTime = 0;    // Thời điểm lần cuối gửi dữ liệu

const long STATUS_CHECK_INTERVAL = 1000; // Tần suất kiểm tra trạng thái bật/tắt DHT từ server (1 giây)
unsigned long lastStatusCheckTime = 0;  // Thời điểm lần cuối kiểm tra trạng thái
bool dht_enabled = true;                // Biến lưu trạng thái bật/tắt của DHT (mặc định là bật)

// --- ĐỐI TƯỢNG ---
DHT dht(DHTPIN, DHTTYPE); // Khởi tạo đối tượng DHT

WiFiUDP ntpUDP;
// Khởi tạo NTPClient (server: vn.pool.ntp.org, múi giờ GMT+7, khoảng thời gian cập nhật 5 phút)
NTPClient timeClient(ntpUDP, "vn.pool.ntp.org", 7 * 3600, 300000); 

// --- SETUP ---
void setup() {
  Serial.begin(115200); // Khởi động Serial Monitor để debug
  dht.begin();          // Khởi động cảm biến DHT

  pinMode(LED_PIN, OUTPUT);     // Thiết lập chân LED là OUTPUT
  digitalWrite(LED_PIN, LOW);   // Đảm bảo LED tắt khi khởi động

  Serial.print("Connecting to WiFi ");
  Serial.println(ssid);

  WiFi.begin(ssid, password); // Bắt đầu kết nối Wi-Fi

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi connected.");
    Serial.print("Local IP: ");
    Serial.println(WiFi.localIP());
    timeClient.begin(); // Khởi động NTP client sau khi có WiFi
    // Lấy trạng thái DHT ban đầu ngay sau khi kết nối WiFi thành công
    getDhtStatus(); 
  } else {
    Serial.println("\nWiFi connection failed. Please check SSID and password.");
  }
}

// --- HÀM LẤY TIMESTAMP ---
String getTimeStamp() {
  // Cập nhật thời gian NTP
  timeClient.update();
  
  // Nếu chưa có thời gian NTP, thử lại vài lần
  if (!timeClient.isTimeSet()) {
    Serial.println("NTP time not yet set, attempting to sync...");
    for(int i = 0; i < 5 && !timeClient.isTimeSet(); ++i) {
        delay(1000);
        timeClient.update();
    }
    if (!timeClient.isTimeSet()) {
        Serial.println("Still failed to get NTP time. Using fallback timestamp (may be inaccurate).");
        return ""; // Trả về rỗng để bỏ qua việc gửi dữ liệu
    }
  }

  time_t rawtime = timeClient.getEpochTime();
  struct tm * ti;
  ti = localtime(&rawtime); // Chuyển đổi sang giờ địa phương
  char buffer[80];
  // Định dạng thời gian theo chuẩn MySQL TIMESTAMP: YYYY-MM-DD HH:MM:SS
  strftime(buffer, sizeof(buffer), "%Y-%m-%d %H:%M:%S", ti);
  return String(buffer);
}

// --- HÀM LẤY TRẠNG THÁI DHT TỪ SERVER ---
void getDhtStatus() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(getDhtStatusApiUrl); // Kết nối đến API lấy trạng thái
    int httpCode = http.GET();     // Gửi GET request

    if (httpCode > 0) {
      String payload = http.getString();
      Serial.print("DHT Status API Response: ");
      Serial.println(payload);
      
      payload.trim(); // Cắt khoảng trắng từ chuỗi payload TẠI CHỖ
      
      // API trả về "true" hoặc "false"
      if (payload.equals("true")) { // So sánh trực tiếp trên payload đã được cắt
        dht_enabled = true;
        Serial.println("DHT is ENABLED.");
      } else {
        dht_enabled = false;
        Serial.println("DHT is DISABLED.");
      }
    } else {
      Serial.printf("[HTTP GET] getDhtStatus failed, error: %s\n", http.errorToString(httpCode).c_str());
      Serial.println("Retaining previous DHT state due to API error.");
    }
    http.end(); // Đóng kết nối
  }
}

// --- LOOP ---
void loop() {
  if (WiFi.status() == WL_CONNECTED) {
    // Luôn cố gắng cập nhật thời gian NTP trong loop
    timeClient.update();

    // Kiểm tra trạng thái bật/tắt DHT từ server định kỳ
    if (millis() - lastStatusCheckTime > STATUS_CHECK_INTERVAL) {
      getDhtStatus(); // Gọi hàm để cập nhật trạng thái dht_enabled
      lastStatusCheckTime = millis();
    }

    // --- LOGIC ĐIỀU KHIỂN LED VÀ GỬI DỮ LIỆU ---
    if (dht_enabled) { // Nếu DHT được BẬT
      digitalWrite(LED_PIN, HIGH); // BẬT LED LIÊN TỤC KHI CHẾ ĐỘ ĐO ĐANG HOẠT ĐỘNG

      if (millis() - lastDataPostTime > postingInterval) { // Và đã đến thời gian gửi
        lastDataPostTime = millis();

        // KHÔNG CẦN digitalWrite(LED_PIN, HIGH) hay delay Ở ĐÂY NỮA, LED ĐÃ SÁNG LIÊN TỤC
        
        // Đọc nhiệt độ và độ ẩm
        float h = dht.readHumidity();
        float t = dht.readTemperature();

        // KHÔNG CẦN digitalWrite(LED_PIN, LOW) Ở ĐÂY NỮA, LED SẼ TẮT KHI dht_enabled = false
        
        // Kiểm tra xem việc đọc có thành công không
        if (isnan(h) || isnan(t)) {
          Serial.println("Failed to read from DHT sensor!");
          // Nếu đọc lỗi, LED vẫn sẽ sáng liên tục (nếu dht_enabled vẫn true)
          // hoặc tắt nếu dht_enabled chuyển false
          return; // Thoát khỏi vòng lặp nếu đọc lỗi
        }

        Serial.print("Humidity: ");
        Serial.print(h);
        Serial.print(" %\t");
        Serial.print("Temperature: ");
        Serial.print(t);
        Serial.println(" *C");

        String timestamp = getTimeStamp(); // Lấy timestamp
        if (timestamp != "") { // Chỉ gửi dữ liệu nếu có timestamp hợp lệ
          String serverPath = "http://" + String(serverAddress) + String(postDataApiUrl);

          HTTPClient http;
          http.begin(serverPath);
          http.addHeader("Content-Type", "application/json");

          StaticJsonDocument<256> jsonDocument;
          jsonDocument["device_id"] = deviceId;
          jsonDocument["temperature"] = t;
          jsonDocument["humidity"] = h;
          jsonDocument["timestamp"] = timestamp;

          String httpRequestData;
          serializeJson(jsonDocument, httpRequestData);

          Serial.print("Sending POST request to: ");
          Serial.println(serverPath);
          Serial.print("Payload: ");
          Serial.println(httpRequestData);

          int httpResponseCode = http.POST(httpRequestData);

          if (httpResponseCode > 0) {
            Serial.print("HTTP Response code: ");
            Serial.println(httpResponseCode);
            String response = http.getString();
            Serial.println(response);
          } else {
            Serial.print("Error code: ");
            Serial.println(httpResponseCode);
            Serial.print("HTTP Error: ");
            Serial.println(http.errorToString(httpResponseCode).c_str());
          }
          http.end();
        } else {
          Serial.println("Skipping data send due to invalid timestamp.");
        }
      } // Kết thúc if (millis() - lastDataPostTime > postingInterval)
      // Nếu dht_enabled là true nhưng chưa đến lúc gửi, LED vẫn sáng
    } else { // Nếu DHT bị TẮT (dht_enabled là false)
      Serial.println("DHT sensor is currently disabled. Not reading/sending data.");
      digitalWrite(LED_PIN, LOW); // ĐẢM BẢO LED TẮT KHI DHT KHÔNG HOẠT ĐỘNG
      delay(1000); // Thêm một delay ngắn để tránh spam Serial Monitor
    }
  } else { // Nếu Wi-Fi không kết nối
    Serial.println("WiFi not connected. Reconnecting...");
    WiFi.begin(ssid, password);
    delay(5000); // Đợi 5 giây trước khi thử lại
  }
}