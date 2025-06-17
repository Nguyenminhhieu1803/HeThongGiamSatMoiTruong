// auth_script.js

// Lấy tham chiếu đến các form và các phần tử thông báo
const loginForm = document.getElementById('login-form');
const registerForm = document.getElementById('register-form');
const showRegisterFormLink = document.getElementById('show-register-form-link');
const showLoginFormLink = document.getElementById('show-login-form-link');

const usernameLoginInput = document.getElementById('username-login');
const passwordLoginInput = document.getElementById('password-login');
const loginStatusMessage = document.getElementById('login-status-message');

const usernameRegisterInput = document.getElementById('username-register');
const passwordRegisterInput = document.getElementById('password-register');
const confirmPasswordRegisterInput = document.getElementById('confirm-password-register');
const registerStatusMessage = document.getElementById('register-status-message');

// Hàm hiển thị thông báo
function showStatusMessage(element, message, type = 'info') {
    element.textContent = message;
    element.className = 'status-message'; // Đặt lại các class về mặc định
    element.classList.add(type); // Thêm class loại thông báo (error, success, info)
    element.style.display = 'block'; // Hiển thị thông báo

    // Xóa thông báo sau 5 giây
    setTimeout(() => {
        element.style.display = 'none';
        element.textContent = '';
        element.classList.remove(type); // Xóa class loại thông báo
    }, 5000);
}

// Hàm để hiển thị form Đăng ký và ẩn form Đăng nhập
function displayRegisterForm() {
    loginForm.classList.add('hidden');
    registerForm.classList.remove('hidden');
    // Xóa thông báo cũ khi chuyển form
    showStatusMessage(loginStatusMessage, '', 'info');
    showStatusMessage(registerStatusMessage, '', 'info');
}

// Hàm để hiển thị form Đăng nhập và ẩn form Đăng ký
function displayLoginForm() {
    registerForm.classList.add('hidden');
    loginForm.classList.remove('hidden');
    // Xóa thông báo cũ khi chuyển form
    showStatusMessage(loginStatusMessage, '', 'info');
    showStatusMessage(registerStatusMessage, '', 'info');
}

// --- Xử lý sự kiện chuyển đổi form ---
if (showRegisterFormLink) {
    showRegisterFormLink.addEventListener('click', function(e) {
        e.preventDefault();
        displayRegisterForm();
    });
}

if (showLoginFormLink) {
    showLoginFormLink.addEventListener('click', function(e) {
        e.preventDefault();
        displayLoginForm();
    });
}
 
// --- Xử lý đăng nhập ---
if (loginForm) { // Kiểm tra nếu form tồn tại
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault(); // Ngăn form gửi đi theo cách truyền thống

        const username = usernameLoginInput.value.trim();
        const password = passwordLoginInput.value.trim();

        showStatusMessage(loginStatusMessage, 'Đang xử lý...', 'info');

        if (!username || !password) {
            showStatusMessage(loginStatusMessage, 'Vui lòng nhập tên đăng nhập và mật khẩu.', 'error');
            return;
        }

        try {
            // Đường dẫn từ Login/login_register.html đến Login/api/users/login.php
            const response = await fetch('api/users/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password }),
                credentials: 'include' // Để gửi cookie session
            });
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                console.log("auth_script.js: Đăng nhập thành công. Dữ liệu từ server:", data);
                // XÓA DÒNG NÀY: localStorage.setItem('loggedInUser', data.username);
                // XÓA DÒNG NÀY: console.log("auth_script.js: Đã lưu loggedInUser vào localStorage:", localStorage.getItem('loggedInUser'));
                showStatusMessage(loginStatusMessage, 'Đăng nhập thành công! Đang chuyển hướng...', 'success');
                // Chuyển hướng đến trang dashboard chính (index.php) sau khi đăng nhập thành công
                setTimeout(() => {
                    // Đảm bảo chuyển hướng đến PHP gateway để kiểm tra session
                    window.location.href = '../TH_DO_AN/index.php'; // CHUYỂN HƯỚNG TỚI TH_DO_AN/index.php
                }, 1500); // Chờ 1.5 giây để người dùng thấy thông báo
            } else {
                console.log("auth_script.js: Đăng nhập thất bại. Phản hồi:", data);
                showStatusMessage(loginStatusMessage, 'Lỗi đăng nhập: ' + (data.message || 'Tên đăng nhập hoặc mật khẩu không đúng.'), 'error');
            }
        } catch (error) {
            console.error('Lỗi network khi đăng nhập:', error);
            showStatusMessage(loginStatusMessage, 'Lỗi kết nối. Vui lòng thử lại.', 'error');
        }
    });
}


// --- Xử lý đăng ký ---
if (registerForm) { // Kiểm tra nếu form tồn tại
    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault(); // Ngăn form gửi đi theo cách truyền thống

        const username = usernameRegisterInput.value.trim();
        const password = passwordRegisterInput.value.trim();
        const confirmPassword = confirmPasswordRegisterInput.value.trim();

        showStatusMessage(registerStatusMessage, 'Đang xử lý...', 'info');

        if (!username || !password || !confirmPassword) {
            showStatusMessage(registerStatusMessage, 'Vui lòng điền đầy đủ các trường.', 'error');
            return;
        }
        if (password !== confirmPassword) {
            showStatusMessage(registerStatusMessage, 'Mật khẩu xác nhận không khớp!', 'error');
            return;
        }
        if (password.length < 6) { // Yêu cầu mật khẩu tối thiểu 6 ký tự
            showStatusMessage(registerStatusMessage, 'Mật khẩu phải có ít nhất 6 ký tự.', 'error');
            return;
        }

        try {
            // Đường dẫn từ Login/login_register.html đến Login/api/users/register.php
            const response = await fetch('api/users/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password }),
                credentials: 'include' // Quan trọng để gửi cookie session
            });
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                showStatusMessage(registerStatusMessage, 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.', 'success');
                // Sau khi đăng ký thành công, tự động chuyển sang form đăng nhập
                setTimeout(() => {
                    displayLoginForm();
                    // Xóa dữ liệu input sau khi đăng ký thành công
                    usernameRegisterInput.value = '';
                    passwordRegisterInput.value = '';
                    confirmPasswordRegisterInput.value = '';
                }, 1000); // Đợi 1 giây để người dùng thấy thông báo
            } else {
                showStatusMessage(registerStatusMessage, 'Lỗi đăng ký: ' + (data.message || 'Tên đăng nhập đã tồn tại hoặc có lỗi.'), 'error');
            }
        } catch (error) {
            console.error('Lỗi network khi đăng ký:', error);
            showStatusMessage(registerStatusMessage, 'Lỗi kết nối. Vui lòng thử lại.', 'error');
        }
    });
}

// Hàm kiểm tra trạng thái đăng nhập khi trang tải (Chỉ dùng nếu muốn tự động chuyển hướng khi đã đăng nhập)
// Nếu người dùng đã đăng nhập, tự động chuyển hướng đến dashboard.
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Đường dẫn từ Login/login_register.html đến Login/api/users/check_login.php
        const response = await fetch('api/users/check_login.php', {credentials: 'include'});
        const data = await response.json();
        if (data.loggedIn) {
            console.log("Người dùng đã đăng nhập. Chuyển hướng đến dashboard.");
            window.location.href = '../TH_DO_AN/index.php'; // Chuyển hướng đến TH_DO_AN/index.php
        } else {
            console.log("Người dùng chưa đăng nhập. Hiển thị form đăng nhập.");
            displayLoginForm(); // Hiển thị form đăng nhập khi tải trang
        }
    } catch (error) {
        console.error('Lỗi khi kiểm tra trạng thái đăng nhập ban đầu:', error);
        // Nếu có lỗi, vẫn hiển thị form đăng nhập để người dùng có thể thử lại
        displayLoginForm();
    }
});