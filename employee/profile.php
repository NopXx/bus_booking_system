<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Check if user is logged in as employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// Get employee information
$stmt = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $tel = $_POST['tel'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate current password if trying to change password
    if (!empty($current_password)) {
        if (!password_verify($current_password, $employee['password'])) {
            $errors[] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
        } elseif (empty($new_password)) {
            $errors[] = "กรุณากรอกรหัสผ่านใหม่";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "รหัสผ่านใหม่ไม่ตรงกัน";
        }
    }
    
    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                // Update with new password
                $stmt = $pdo->prepare("UPDATE employee SET first_name = ?, last_name = ?, tel = ?, password = ? WHERE employee_id = ?");
                $stmt->execute([$first_name, $last_name, $tel, password_hash($new_password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("UPDATE employee SET first_name = ?, last_name = ?, tel = ? WHERE employee_id = ?");
                $stmt->execute([$first_name, $last_name, $tel, $_SESSION['user_id']]);
            }
            
            $success = "อัปเดตข้อมูลสำเร็จ";
            
            // Refresh employee data
            $stmt = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $employee = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">โปรไฟล์ของฉัน (พนักงาน)</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($employee['username']); ?>" disabled>
                            <small class="text-muted">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="first_name" class="form-label">ชื่อ</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="last_name" class="form-label">นามสกุล</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tel" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" class="form-control" id="tel" name="tel" value="<?php echo htmlspecialchars($employee['tel']); ?>" required>
                        </div>
                        
                        <hr>
                        <h5>เปลี่ยนรหัสผ่าน</h5>
                        <small class="text-muted">กรอกข้อมูลด้านล่างเฉพาะเมื่อต้องการเปลี่ยนรหัสผ่าน</small>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                            <a href="/bus_booking_system/employee/index.php" class="btn btn-outline-secondary">กลับไปหน้าแดชบอร์ด</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>