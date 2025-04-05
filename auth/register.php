<?php
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $tel = $_POST['tel'];
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM User WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $error = "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO User (username, password, first_name, last_name, tel) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $first_name, $last_name, $tel]);
            
            $_SESSION['success'] = "ลงทะเบียนสำเร็จ กรุณาเข้าสู่ระบบ";
            header('Location: /bus_booking_system/auth/login.php');
            exit();
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาดในการลงทะเบียน";
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
        <a href="/bus_booking_system/" class="display-6 fw-bold mb-5 link-offset-2 link-underline link-underline-opacity-0">จองตั๋วรถโดยสารออนไลน์</a>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">สมัครสมาชิก</h4>
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
                        <div class="mb-3">
                            <label for="first_name" class="form-label">ชื่อ</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">นามสกุล</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="tel" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" class="form-control" id="tel" name="tel" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">สมัครสมาชิก</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>มีบัญชีอยู่แล้ว? <a href="/bus_booking_system/auth/login.php">เข้าสู่ระบบ</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>