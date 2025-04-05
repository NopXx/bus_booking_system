<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Check if user is logged in as employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// Get employee ID
$stmt = $pdo->prepare("SELECT employee_id FROM employee WHERE employee_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();
$employee_id = $employee['employee_id'];

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    try {
        if ($action == 'reset_password') {
            // Generate a random password
            $new_password = bin2hex(random_bytes(4)); // 8 characters
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE User SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success = "รีเซ็ตรหัสผ่านสำเร็จ: " . $new_password;
        } elseif ($action == 'delete') {
            // Check if user has any tickets
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Ticket WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $error = "ไม่สามารถลบผู้ใช้ได้เนื่องจากมีการจองตั๋วแล้ว";
            } else {
                $stmt = $pdo->prepare("DELETE FROM User WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $success = "ลบผู้ใช้สำเร็จ";
            }
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการดำเนินการ";
    }
}

// Get specific user details if ID is provided
$user = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id) as ticket_count,
               (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'pending') as pending_tickets,
               (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'confirmed') as confirmed_tickets,
               (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'issued') as issued_tickets,
               (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'cancelled') as cancelled_tickets
        FROM User u
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Get user's recent tickets
        $stmt = $pdo->prepare("
            SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
                   b.bus_name, b.bus_type, r.source, r.destination
            FROM Ticket t
            JOIN Schedule s ON t.schedule_id = s.schedule_id
            JOIN Bus b ON s.bus_id = b.bus_id
            JOIN Route r ON s.route_id = r.route_id
            WHERE t.user_id = ?
            ORDER BY t.booking_date DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_tickets = $stmt->fetchAll();
    }
}

// Get all users with ticket counts
$stmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id) as ticket_count,
           (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'pending') as pending_tickets,
           (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'confirmed') as confirmed_tickets,
           (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'issued') as issued_tickets,
           (SELECT COUNT(*) FROM Ticket WHERE user_id = u.user_id AND status = 'cancelled') as cancelled_tickets
    FROM User u
    ORDER BY u.user_id DESC
");
$users = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">
    <?php if ($user): ?>
        <!-- Display specific user details -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ข้อมูลผู้ใช้ #<?php echo $user['user_id']; ?></h5>
                <a href="users.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> กลับ
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">ข้อมูลส่วนตัว</h6>
                        <p><strong>ชื่อผู้ใช้:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                        <p><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                        <p><strong>เบอร์โทรศัพท์:</strong> <?php echo htmlspecialchars($user['tel']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">สถิติการจองตั๋ว</h6>
                        <p><strong>จำนวนตั๋วทั้งหมด:</strong> <?php echo $user['ticket_count']; ?></p>
                        <p><strong>ตั๋วที่รอการยืนยัน:</strong> <?php echo $user['pending_tickets']; ?></p>
                        <p><strong>ตั๋วที่ยืนยันแล้ว:</strong> <?php echo $user['confirmed_tickets']; ?></p>
                        <p><strong>ตั๋วที่ออกแล้ว:</strong> <?php echo $user['issued_tickets']; ?></p>
                        <p><strong>ตั๋วที่ยกเลิกแล้ว:</strong> <?php echo $user['cancelled_tickets']; ?></p>
                    </div>
                </div>
                
                <?php if (!empty($recent_tickets)): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h6 class="border-bottom pb-2">ประวัติการจองล่าสุด</h6>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>รหัสตั๋ว</th>
                                            <th>วันที่เดินทาง</th>
                                            <th>เวลา</th>
                                            <th>เส้นทาง</th>
                                            <th>รถ</th>
                                            <th>ที่นั่ง</th>
                                            <th>สถานะ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo $ticket['ticket_id']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($ticket['date_travel'])); ?></td>
                                                <td>
                                                    <?php echo date('H:i', strtotime($ticket['departure_time'])); ?> - 
                                                    <?php echo date('H:i', strtotime($ticket['arrival_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($ticket['source'] . ' - ' . $ticket['destination']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['bus_name'] . ' (' . $ticket['bus_type'] . ')'); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['seat_number']); ?></td>
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
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะรีเซ็ตรหัสผ่านผู้ใช้นี้?');">
                                    <i class="fas fa-key"></i> รีเซ็ตรหัสผ่าน
                                </button>
                            </form>
                            
                            <?php if ($user['ticket_count'] == 0): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้?');">
                                        <i class="fas fa-trash"></i> ลบผู้ใช้
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Display all users -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">รายชื่อผู้ใช้ทั้งหมด</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (empty($users)): ?>
                    <div class="alert alert-info">ไม่มีข้อมูลผู้ใช้</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>รหัสผู้ใช้</th>
                                    <th>ชื่อผู้ใช้</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>เบอร์โทรศัพท์</th>
                                    <th>จำนวนตั๋ว</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?php echo $u['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($u['tel']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $u['ticket_count']; ?></span>
                                            <?php if ($u['pending_tickets'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $u['pending_tickets']; ?> รอ</span>
                                            <?php endif; ?>
                                            <?php if ($u['confirmed_tickets'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $u['confirmed_tickets']; ?> ยืนยัน</span>
                                            <?php endif; ?>
                                            <?php if ($u['issued_tickets'] > 0): ?>
                                                <span class="badge bg-secondary"><?php echo $u['issued_tickets']; ?> ออกตั๋ว</span>
                                            <?php endif; ?>
                                            <?php if ($u['cancelled_tickets'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $u['cancelled_tickets']; ?> ยกเลิก</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="users.php?id=<?php echo $u['user_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> ดูรายละเอียด
                                            </a>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะรีเซ็ตรหัสผ่านผู้ใช้นี้?');">
                                                    <i class="fas fa-key"></i> รีเซ็ตรหัสผ่าน
                                                </button>
                                            </form>
                                            <?php if ($u['ticket_count'] == 0): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้?');">
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
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>