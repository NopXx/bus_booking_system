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

// Handle ticket status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ticket_id']) && isset($_POST['status'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE Ticket SET status = ? WHERE ticket_id = ?");
        $stmt->execute([$status, $ticket_id]);
        $success = "อัปเดตสถานะตั๋วสำเร็จ";
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการอัปเดตสถานะตั๋ว";
    }
}

// Handle ticket deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM Ticket WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        $success = "ลบตั๋วสำเร็จ";
        
        // Redirect to tickets list if we were on a specific ticket page
        if (isset($_GET['id']) && $_GET['id'] == $ticket_id) {
            header('Location: /bus_booking_system/employee/tickets.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการลบตั๋ว: " . $e->getMessage();
    }
}

// Get specific ticket details if ID is provided
$ticket = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $ticket_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.tel, s.date_travel, s.departure_time, s.arrival_time, s.priec,
               b.bus_name, b.bus_type, r.source, r.destination, e.first_name as emp_first_name, e.last_name as emp_last_name
        FROM Ticket t
        JOIN User u ON t.user_id = u.user_id
        JOIN Schedule s ON t.schedule_id = s.schedule_id
        JOIN Bus b ON s.bus_id = b.bus_id
        JOIN Route r ON s.route_id = r.route_id
        JOIN employee e ON t.employee_id = e.employee_id
        WHERE t.ticket_id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
}

// Get all tickets with related information
$stmt = $pdo->query("
    SELECT t.*, u.first_name, u.last_name, s.date_travel, s.departure_time, s.arrival_time, s.priec,
           b.bus_name, b.bus_type, r.source, r.destination
    FROM Ticket t
    JOIN User u ON t.user_id = u.user_id
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    JOIN Bus b ON s.bus_id = b.bus_id
    JOIN Route r ON s.route_id = r.route_id
    ORDER BY t.booking_date DESC, t.ticket_date DESC
");
$tickets = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">
    <?php if ($ticket): ?>
        <!-- Display specific ticket details -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">รายละเอียดตั๋ว #<?php echo $ticket['ticket_id']; ?></h5>
                <a href="tickets.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> กลับ
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">ข้อมูลผู้โดยสาร</h6>
                        <p><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></p>
                        <p><strong>เบอร์โทรศัพท์:</strong> <?php echo htmlspecialchars($ticket['tel']); ?></p>
                        <p><strong>เบอร์ที่นั่ง:</strong> <?php echo htmlspecialchars($ticket['seat_number']); ?></p>
                        <p><strong>สถานะ:</strong> 
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
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">ข้อมูลการเดินทาง</h6>
                        <p><strong>วันที่เดินทาง:</strong> <?php echo date('d/m/Y', strtotime($ticket['date_travel'])); ?></p>
                        <p><strong>เวลา:</strong> <?php echo date('H:i', strtotime($ticket['departure_time'])); ?> - <?php echo date('H:i', strtotime($ticket['arrival_time'])); ?></p>
                        <p><strong>เส้นทาง:</strong> <?php echo htmlspecialchars($ticket['source'] . ' - ' . $ticket['destination']); ?></p>
                        <p><strong>รถ:</strong> <?php echo htmlspecialchars($ticket['bus_name'] . ' (' . $ticket['bus_type'] . ')'); ?></p>
                        <p><strong>ราคา:</strong> <?php echo number_format($ticket['priec'], 2); ?> บาท</p>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">ข้อมูลการจอง</h6>
                        <p><strong>วันที่จอง:</strong> <?php echo $ticket['booking_date'] ? date('d/m/Y', strtotime($ticket['booking_date'])) : '-'; ?></p>
                        <p><strong>วันที่ออกตั๋ว:</strong> <?php echo $ticket['ticket_date'] ? date('d/m/Y', strtotime($ticket['ticket_date'])) : '-'; ?></p>
                        <p><strong>พนักงานที่ออกตั๋ว:</strong> <?php echo htmlspecialchars($ticket['emp_first_name'] . ' ' . $ticket['emp_last_name']); ?></p>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">การดำเนินการ</h6>
                        <div class="d-flex gap-2">
                            <?php if ($ticket['status'] == 'pending'): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> ยืนยันการจอง
                                    </button>
                                </form>
                                
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-times"></i> ยกเลิกการจอง
                                    </button>
                                </form>
                            <?php elseif ($ticket['status'] == 'confirmed'): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                    <input type="hidden" name="status" value="issued">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-ticket-alt"></i> ออกตั๋ว
                                    </button>
                                </form>
                                
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-times"></i> ยกเลิกการจอง
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Delete ticket button (for all statuses) -->
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบตั๋วนี้? การดำเนินการนี้ไม่สามารถยกเลิกได้');">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                <input type="hidden" name="delete_ticket" value="1">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> ลบตั๋ว
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Display all tickets -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">รายการตั๋วทั้งหมด</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (empty($tickets)): ?>
                    <div class="alert alert-info">ไม่มีข้อมูลตั๋ว</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>รหัสตั๋ว</th>
                                    <th>ผู้โดยสาร</th>
                                    <th>วันที่เดินทาง</th>
                                    <th>เวลา</th>
                                    <th>เส้นทาง</th>
                                    <th>รถ</th>
                                    <th>ที่นั่ง</th>
                                    <th>สถานะ</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $t): ?>
                                    <tr>
                                        <td><?php echo $t['ticket_id']; ?></td>
                                        <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($t['date_travel'])); ?></td>
                                        <td>
                                            <?php echo date('H:i', strtotime($t['departure_time'])); ?> - 
                                            <?php echo date('H:i', strtotime($t['arrival_time'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($t['source'] . ' - ' . $t['destination']); ?></td>
                                        <td><?php echo htmlspecialchars($t['bus_name'] . ' (' . $t['bus_type'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($t['seat_number']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $t['status'] == 'pending' ? 'bg-warning' : 
                                                    ($t['status'] == 'confirmed' ? 'bg-success' : 
                                                    ($t['status'] == 'cancelled' ? 'bg-danger' : 'bg-secondary')); 
                                            ?>">
                                                <?php 
                                                    echo $t['status'] == 'pending' ? 'รอการยืนยัน' : 
                                                        ($t['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 
                                                        ($t['status'] == 'cancelled' ? 'ยกเลิกแล้ว' : 'ออกตั๋วแล้ว')); 
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="tickets.php?id=<?php echo $t['ticket_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> ดู
                                                </a>
                                                
                                                <?php if ($t['status'] == 'pending'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="ticket_id" value="<?php echo $t['ticket_id']; ?>">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> ยืนยัน
                                                        </button>
                                                    </form>
                                                <?php elseif ($t['status'] == 'confirmed'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="ticket_id" value="<?php echo $t['ticket_id']; ?>">
                                                        <input type="hidden" name="status" value="issued">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-ticket-alt"></i> ออกตั๋ว
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <!-- Delete button for all tickets -->
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบตั๋วนี้? การดำเนินการนี้ไม่สามารถยกเลิกได้');">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $t['ticket_id']; ?>">
                                                    <input type="hidden" name="delete_ticket" value="1">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> ลบ
                                                    </button>
                                                </form>
                                            </div>
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