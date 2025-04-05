<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// Get specific ticket if ID is provided
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$specific_ticket = null;

if ($ticket_id > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
               r.source, r.destination, b.bus_name, b.bus_type,
               e.first_name as driver_first_name, e.last_name as driver_last_name
        FROM Ticket t
        JOIN Schedule s ON t.schedule_id = s.schedule_id
        JOIN Route r ON s.route_id = r.route_id
        JOIN Bus b ON s.bus_id = b.bus_id
        JOIN employee e ON s.employee_id = e.employee_id
        WHERE t.ticket_id = ? AND t.user_id = ?
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $specific_ticket = $stmt->fetch();
    
    if (!$specific_ticket) {
        header('Location: /bus_booking_system/user/tickets.php');
        exit();
    }
}

// Get all tickets for the user
$stmt = $pdo->prepare("
    SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
           r.source, r.destination, b.bus_name
    FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    WHERE t.user_id = ?
    ORDER BY s.date_travel DESC, t.booking_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll();

// Handle ticket cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    
    // Verify ticket belongs to user
    $stmt = $pdo->prepare("SELECT * FROM Ticket WHERE ticket_id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $ticket = $stmt->fetch();
    
    if ($ticket && $ticket['status'] != 'cancelled') {
        try {
            $stmt = $pdo->prepare("UPDATE Ticket SET status = 'cancelled' WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);
            $success = "ยกเลิกตั๋วสำเร็จ";
            
            // Refresh tickets list
            $stmt = $pdo->prepare("
                SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
                       r.source, r.destination, b.bus_name
                FROM Ticket t
                JOIN Schedule s ON t.schedule_id = s.schedule_id
                JOIN Route r ON s.route_id = r.route_id
                JOIN Bus b ON s.bus_id = b.bus_id
                WHERE t.user_id = ?
                ORDER BY s.date_travel DESC, t.booking_date DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $tickets = $stmt->fetchAll();
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาดในการยกเลิกตั๋ว";
        }
    } else {
        $error = "ไม่พบตั๋วหรือไม่สามารถยกเลิกได้";
    }
}
?>

<div class="container mt-5">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($specific_ticket): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">รายละเอียดตั๋ว</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>ข้อมูลการเดินทาง</h5>
                        <p><strong>เลขที่ตั๋ว:</strong> <?php echo $specific_ticket['ticket_id']; ?></p>
                        <p><strong>เส้นทาง:</strong> <?php echo htmlspecialchars($specific_ticket['source'] . ' - ' . $specific_ticket['destination']); ?></p>
                        <p><strong>วันที่เดินทาง:</strong> <?php echo date('d/m/Y', strtotime($specific_ticket['date_travel'])); ?></p>
                        <p><strong>เวลาออก:</strong> <?php echo date('H:i', strtotime($specific_ticket['departure_time'])); ?></p>
                        <p><strong>เวลาถึง:</strong> <?php echo date('H:i', strtotime($specific_ticket['arrival_time'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5>ข้อมูลรถและที่นั่ง</h5>
                        <p><strong>รถ:</strong> <?php echo htmlspecialchars($specific_ticket['bus_name']); ?></p>
                        <p><strong>ประเภท:</strong> <?php echo htmlspecialchars($specific_ticket['bus_type']); ?></p>
                        <p><strong>ที่นั่ง:</strong> <?php echo htmlspecialchars($specific_ticket['seat_number']); ?></p>
                        <p><strong>ราคา:</strong> <?php echo number_format($specific_ticket['priec'], 2); ?> บาท</p>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h5>สถานะการจอง</h5>
                        <p><strong>วันที่จอง:</strong> <?php echo date('d/m/Y', strtotime($specific_ticket['booking_date'])); ?></p>
                        <p><strong>สถานะ:</strong> 
                            <?php if ($specific_ticket['status'] == 'confirmed'): ?>
                                <span class="badge bg-success">ยืนยันแล้ว</span>
                            <?php elseif ($specific_ticket['status'] == 'pending'): ?>
                                <span class="badge bg-warning">รอการยืนยัน</span>
                            <?php elseif ($specific_ticket['status'] == 'issued'): ?>
                                <span class="badge bg-info">ออกตั๋วแล้ว</span>
                            <?php else: ?>
                                <span class="badge bg-danger">ยกเลิก</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="/bus_booking_system/user/tickets.php" class="btn btn-secondary">กลับไปรายการตั๋ว</a>
                    <?php if ($specific_ticket['status'] == 'pending'): ?>
                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกตั๋วนี้?');">
                            <input type="hidden" name="ticket_id" value="<?php echo $specific_ticket['ticket_id']; ?>">
                            <button type="submit" name="cancel_ticket" class="btn btn-danger">ยกเลิกตั๋ว</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">ตั๋วของฉัน</h4>
            </div>
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                    <div class="alert alert-info">
                        คุณยังไม่มีตั๋ว <a href="/bus_booking_system/search.php">จองตั๋วเลย</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>เลขที่ตั๋ว</th>
                                    <th>เส้นทาง</th>
                                    <th>วันที่เดินทาง</th>
                                    <th>ที่นั่ง</th>
                                    <th>ราคา</th>
                                    <th>สถานะ</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td><?php echo $ticket['ticket_id']; ?></td>
                                        <td><?php echo htmlspecialchars($ticket['source'] . ' - ' . $ticket['destination']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($ticket['date_travel'])); ?></td>
                                        <td><?php echo htmlspecialchars($ticket['seat_number']); ?></td>
                                        <td><?php echo number_format($ticket['priec'], 2); ?> บาท</td>
                                        <td>
                                            <?php if ($ticket['status'] == 'confirmed'): ?>
                                                <span class="badge bg-success">ยืนยันแล้ว</span>
                                            <?php elseif ($ticket['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">รอการยืนยัน</span>
                                            <?php elseif ($ticket['status'] == 'issued'): ?>
                                                <span class="badge bg-info">ออกตั๋วแล้ว</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">ยกเลิก</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/bus_booking_system/user/tickets.php?id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> ดูรายละเอียด
                                            </a>
                                            <?php if ($ticket['status'] == 'pending'): ?>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกตั๋วนี้?');">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                                    <button type="submit" name="cancel_ticket" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i> ยกเลิก
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