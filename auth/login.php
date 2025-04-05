<?php
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // ตรวจสอบว่าเป็นพนักงานหรือไม่
    $stmt = $conn->prepare("SELECT * FROM employee WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    
    if ($employee && password_verify($password, $employee['password'])) {
        $_SESSION['user_id'] = $employee['employee_id'];
        $_SESSION['role'] = 'employee';
        $_SESSION['username'] = $employee['username'];
        header('Location: /bus_booking_system/employee/');
        exit();
    }
    
    // ตรวจสอบว่าเป็นผู้ใช้ทั่วไปหรือไม่
    $stmt = $conn->prepare("SELECT * FROM User WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = 'user';
        $_SESSION['username'] = $user['username'];
        header('Location: /bus_booking_system/user/');
        exit();
    }
    
    $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
        <a href="/bus_booking_system/" class="display-6 fw-bold mb-5 link-offset-2 link-underline link-underline-opacity-0">จองตั๋วรถโดยสารออนไลน์</a>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">เข้าสู่ระบบ</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">รหัสผ่าน</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">จดจำฉัน</label>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p class="mt-2">ยังไม่มีบัญชี? <a href="/bus_booking_system/auth/register.php">สมัครสมาชิก</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>