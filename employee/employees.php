<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบในฐานะพนักงานหรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// ดึงรหัสพนักงาน
$stmt = $conn->prepare("SELECT employee_id FROM employee WHERE employee_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$current_employee = $result->fetch_assoc();
$employee_id = $current_employee['employee_id'];

// จัดการการอัปเดตสถานะพนักงาน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['employee_id']) && isset($_POST['action'])) {
    $target_employee_id = (int)$_POST['employee_id'];
    $action = $_POST['action'];
    
    try {
        if ($action == 'reset_password') {
            // สร้างรหัสผ่านแบบสุ่ม
            $new_password = bin2hex(random_bytes(4)); // 8 ตัวอักษร
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE employee SET password = ? WHERE employee_id = ?");
            $stmt->bind_param("si", $hashed_password, $target_employee_id);
            $stmt->execute();
            
            $success = "รีเซ็ตรหัสผ่านสำเร็จ: " . $new_password;
        } elseif ($action == 'delete') {
            // ตรวจสอบว่าพนักงานมีตารางเดินทางหรือตั๋วหรือไม่
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Schedule WHERE employee_id = ?");
            $stmt->bind_param("i", $target_employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $count_schedules = $row['count'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Ticket WHERE employee_id = ?");
            $stmt->bind_param("i", $target_employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $count_tickets = $row['count'];
            
            if ($count_schedules > 0 || $count_tickets > 0) {
                $error = "ไม่สามารถลบพนักงานได้เนื่องจากมีการเชื่อมโยงกับตารางเดินทางหรือตั๋ว";
            } else {
                $stmt = $conn->prepare("DELETE FROM employee WHERE employee_id = ?");
                $stmt->bind_param("i", $target_employee_id);
                $stmt->execute();
                $success = "ลบพนักงานสำเร็จ";
            }
        }
    } catch (Exception $e) {
        $error = "เกิดข้อผิดพลาดในการดำเนินการ: " . $e->getMessage();
    }
}

// จัดการการเพิ่มพนักงานใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employee'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $tel = $_POST['tel'];
    
    // ตรวจสอบว่าชื่อผู้ใช้มีอยู่แล้วหรือไม่
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employee WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $error = "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO employee (username, password, first_name, last_name, tel) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $password, $first_name, $last_name, $tel);
            $stmt->execute();
            
            $success = "เพิ่มพนักงานใหม่สำเร็จ";
        } catch (Exception $e) {
            $error = "เกิดข้อผิดพลาดในการเพิ่มพนักงาน: " . $e->getMessage();
        }
    }
}

// จัดการการแก้ไขข้อมูลพนักงาน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_employee'])) {
    $target_employee_id = (int)$_POST['employee_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $tel = $_POST['tel'];
    $new_password = $_POST['new_password'];
    
    try {
        if (!empty($new_password)) {
            // อัปเดตพร้อมเปลี่ยนรหัสผ่านใหม่
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE employee SET first_name = ?, last_name = ?, tel = ?, password = ? WHERE employee_id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $tel, $hashed_password, $target_employee_id);
            $stmt->execute();
        } else {
            // อัปเดตโดยไม่เปลี่ยนรหัสผ่าน
            $stmt = $conn->prepare("UPDATE employee SET first_name = ?, last_name = ?, tel = ? WHERE employee_id = ?");
            $stmt->bind_param("sssi", $first_name, $last_name, $tel, $target_employee_id);
            $stmt->execute();
        }
        
        $success = "อัปเดตข้อมูลพนักงานสำเร็จ";
        
        // ถ้าอยู่ในโหมดแก้ไข ให้รีเฟรชข้อมูลพนักงาน
        if (isset($_GET['edit']) && $_GET['edit'] == $target_employee_id) {
            $stmt = $conn->prepare("
                SELECT e.*, 
                    (SELECT COUNT(*) FROM Schedule WHERE employee_id = e.employee_id) as schedule_count,
                    (SELECT COUNT(*) FROM Ticket WHERE employee_id = e.employee_id) as ticket_count
                FROM employee e
                WHERE e.employee_id = ?
            ");
            $stmt->bind_param("i", $target_employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $edit_employee = $result->fetch_assoc();
        }
    } catch (Exception $e) {
        $error = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลพนักงาน: " . $e->getMessage();
    }
}

// ดึงข้อมูลพนักงานเฉพาะรายสำหรับการดูหรือแก้ไข
$employee = null;
$edit_employee = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $target_employee_id = (int)$_GET['edit'];
    
    $stmt = $conn->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM Schedule WHERE employee_id = e.employee_id) as schedule_count,
               (SELECT COUNT(*) FROM Ticket WHERE employee_id = e.employee_id) as ticket_count
        FROM employee e
        WHERE e.employee_id = ?
    ");
    $stmt->bind_param("i", $target_employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_employee = $result->fetch_assoc();
} 
else if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $target_employee_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM Schedule WHERE employee_id = e.employee_id) as schedule_count,
               (SELECT COUNT(*) FROM Ticket WHERE employee_id = e.employee_id) as ticket_count
        FROM employee e
        WHERE e.employee_id = ?
    ");
    $stmt->bind_param("i", $target_employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    
    if ($employee) {
        // ดึงตารางเดินทางล่าสุดของพนักงาน
        $stmt = $conn->prepare("
            SELECT s.*, r.source, r.destination, b.bus_name, b.bus_type
            FROM Schedule s
            JOIN Route r ON s.route_id = r.route_id
            JOIN Bus b ON s.bus_id = b.bus_id
            WHERE s.employee_id = ?
            ORDER BY s.date_travel DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $target_employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $recent_schedules = [];
        while ($row = $result->fetch_assoc()) {
            $recent_schedules[] = $row;
        }
        
        // ดึงตั๋วล่าสุดที่พนักงานดูแล
        $stmt = $conn->prepare("
            SELECT t.*, u.first_name as user_first_name, u.last_name as user_last_name, 
                   s.date_travel, s.departure_time, r.source, r.destination
            FROM Ticket t
            JOIN User u ON t.user_id = u.user_id
            JOIN Schedule s ON t.schedule_id = s.schedule_id
            JOIN Route r ON s.route_id = r.route_id
            WHERE t.employee_id = ?
            ORDER BY t.booking_date DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $target_employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $recent_tickets = [];
        while ($row = $result->fetch_assoc()) {
            $recent_tickets[] = $row;
        }
    }
}

// ดึงข้อมูลพนักงานทั้งหมด
$result = $conn->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM Schedule WHERE employee_id = e.employee_id) as schedule_count,
           (SELECT COUNT(*) FROM Ticket WHERE employee_id = e.employee_id) as ticket_count
    FROM employee e
    ORDER BY e.employee_id
");
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
?>

<div class="container-fluid mt-4">
    <?php if ($employee): ?>
        <!-- Display specific employee details -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ข้อมูลพนักงาน #<?php echo $employee['employee_id']; ?></h5>
                <a href="employees.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> กลับ
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">ข้อมูลส่วนตัว</h6>
                        <p><strong>ชื่อผู้ใช้:</strong> <?php echo htmlspecialchars($employee['username']); ?></p>
                        <p><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                        <p><strong>เบอร์โทรศัพท์:</strong> <?php echo htmlspecialchars($employee['tel']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">สถิติการทำงาน</h6>
                        <p><strong>จำนวนตารางเดินทางที่รับผิดชอบ:</strong> <?php echo $employee['schedule_count']; ?></p>
                        <p><strong>จำนวนตั๋วที่ดูแล:</strong> <?php echo $employee['ticket_count']; ?></p>
                    </div>
                </div>
                
                <?php if (!empty($recent_schedules)): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h6 class="border-bottom pb-2">ตารางเดินทางล่าสุด</h6>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>วันที่</th>
                                            <th>เวลา</th>
                                            <th>เส้นทาง</th>
                                            <th>รถ</th>
                                            <th>ราคา</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_schedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($schedule['date_travel'])); ?></td>
                                                <td>
                                                    <?php echo date('H:i', strtotime($schedule['departure_time'])); ?> - 
                                                    <?php echo date('H:i', strtotime($schedule['arrival_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($schedule['source'] . ' - ' . $schedule['destination']); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['bus_name'] . ' (' . $schedule['bus_type'] . ')'); ?></td>
                                                <td><?php echo number_format($schedule['priec'], 2); ?> บาท</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recent_tickets)): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h6 class="border-bottom pb-2">ตั๋วล่าสุดที่ดูแล</h6>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>เลขตั๋ว</th>
                                            <th>ผู้โดยสาร</th>
                                            <th>วันที่เดินทาง</th>
                                            <th>เส้นทาง</th>
                                            <th>สถานะ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo $ticket['ticket_id']; ?></td>
                                                <td><?php echo htmlspecialchars($ticket['user_first_name'] . ' ' . $ticket['user_last_name']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($ticket['date_travel'])); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['source'] . ' - ' . $ticket['destination']); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $ticket['status'] == 'pending' ? 'bg-warning' : 
                                                            ($ticket['status'] == 'confirmed' ? 'bg-success' : 
                                                            ($ticket['status'] == 'cancelled' ? 'bg-danger' : 'bg-secondary')); 
                                                    ?>">
                                                        <?php 
                                                            echo $ticket['status'] == 'pending' ? 'รอการยืนยัน' : 
                                                                ($ticket['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 
                                                                ($ticket['status'] == 'cancelled' ? 'ยกเลิกแล้ว' : 'ออกตั๋วแล้ว')); 
                                                        ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">การดำเนินการ</h6>
                        <div class="d-flex gap-2">
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="employee_id" value="<?php echo $employee['employee_id']; ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะรีเซ็ตรหัสผ่านพนักงานนี้?');">
                                    <i class="fas fa-key"></i> รีเซ็ตรหัสผ่าน
                                </button>
                            </form>
                            
                            <?php if ($employee['schedule_count'] == 0 && $employee['ticket_count'] == 0): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="employee_id" value="<?php echo $employee['employee_id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบพนักงานนี้?');">
                                        <i class="fas fa-trash"></i> ลบพนักงาน
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Add/Edit Employee / Display All Employees -->
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?php echo $edit_employee ? 'แก้ไขข้อมูลพนักงาน' : 'เพิ่มพนักงานใหม่'; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($edit_employee): ?>
                            <!-- Edit Employee Form -->
                            <form method="POST" action="">
                                <input type="hidden" name="employee_id" value="<?php echo $edit_employee['employee_id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($edit_employee['username']); ?>" disabled>
                                    <small class="text-muted">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">ชื่อ</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($edit_employee['first_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">นามสกุล</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($edit_employee['last_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tel" class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" class="form-control" id="tel" name="tel" value="<?php echo htmlspecialchars($edit_employee['tel']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">รหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="edit_employee" class="btn btn-primary">
                                        <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                                    </button>
                                    <a href="employees.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> ยกเลิก
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Add New Employee Form -->
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
                                
                                <div class="d-grid">
                                    <button type="submit" name="add_employee" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> เพิ่มพนักงาน
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">รายชื่อพนักงานทั้งหมด</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employees)): ?>
                            <div class="alert alert-info">ไม่มีข้อมูลพนักงาน</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>รหัสพนักงาน</th>
                                            <th>ชื่อผู้ใช้</th>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>เบอร์โทรศัพท์</th>
                                            <th>ตารางเดินทาง</th>
                                            <th>ตั๋ว</th>
                                            <th>การดำเนินการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $emp): ?>
                                            <tr>
                                                <td><?php echo $emp['employee_id']; ?></td>
                                                <td><?php echo htmlspecialchars($emp['username']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['tel']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo $emp['schedule_count']; ?></span></td>
                                                <td><span class="badge bg-info"><?php echo $emp['ticket_count']; ?></span></td>
                                                <td>
                                                    <a href="employees.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> ดูรายละเอียด
                                                    </a>
                                                    <a href="employees.php?edit=<?php echo $emp['employee_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> แก้ไข
                                                    </a>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="employee_id" value="<?php echo $emp['employee_id']; ?>">
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะรีเซ็ตรหัสผ่านพนักงานนี้?');">
                                                            <i class="fas fa-key"></i> รีเซ็ตรหัสผ่าน
                                                        </button>
                                                    </form>
                                                    <?php if ($emp['schedule_count'] == 0 && $emp['ticket_count'] == 0): ?>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="employee_id" value="<?php echo $emp['employee_id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบพนักงานนี้?');">
                                                                <i class="fas fa-trash"></i> ลบ
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>