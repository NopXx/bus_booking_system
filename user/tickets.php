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
               e.first_name as driver_first_name, e.last_name as driver_last_name,
               TIMEDIFF(s.arrival_time, s.departure_time) as travel_duration
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

// Handle ticket cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    
    // Verify ticket belongs to user
    $stmt = $pdo->prepare("
        SELECT t.*, s.date_travel
        FROM Ticket t
        JOIN Schedule s ON t.schedule_id = s.schedule_id
        WHERE t.ticket_id = ? AND t.user_id = ?
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $ticket = $stmt->fetch();
    
    if ($ticket && $ticket['status'] != 'cancelled') {
        // Check if departure date is at least 1 day away
        $departure_date = new DateTime($ticket['date_travel']);
        $today = new DateTime();
        $interval = $today->diff($departure_date);
        $days_until_departure = $interval->days;
        
        if ($days_until_departure < 1 && $departure_date > $today) {
            $error = "ไม่สามารถยกเลิกตั๋วได้เนื่องจากใกล้วันเดินทาง กรุณาติดต่อเจ้าหน้าที่";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE Ticket SET status = 'cancelled' WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);
                $success = "ยกเลิกตั๋วสำเร็จ";
                
                // If we were viewing the specific ticket, redirect to tickets list
                if ($specific_ticket) {
                    header('Location: /bus_booking_system/user/tickets.php?success=cancel');
                    exit();
                }
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาดในการยกเลิกตั๋ว";
            }
        }
    } else {
        $error = "ไม่พบตั๋วหรือไม่สามารถยกเลิกได้";
    }
}

// Get all tickets for the user with filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query with filter
$query = "
    SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
           r.source, r.destination, b.bus_name, b.bus_type
    FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    WHERE t.user_id = ?
";

// Add filter clause if a status is selected
if (!empty($status_filter)) {
    $query .= " AND t.status = ?";
}

// Add order clause
$query .= " ORDER BY s.date_travel DESC, t.booking_date DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
if (!empty($status_filter)) {
    $stmt->execute([$_SESSION['user_id'], $status_filter]);
} else {
    $stmt->execute([$_SESSION['user_id']]);
}
$tickets = $stmt->fetchAll();

// Get count by status for the filter badges
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM Ticket
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$ticket_counts = $stmt->fetch();

// Get success message from redirect
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 'cancel') {
    $success_message = "ยกเลิกตั๋วสำเร็จ";
}
?>

<div class="container mt-5">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success) || !empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success ?? $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($specific_ticket): ?>
        <!-- Ticket Detail View -->
        <div class="mb-4">
            <a href="/bus_booking_system/user/tickets.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> กลับไปรายการตั๋ว
            </a>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-ticket-alt me-2"></i> รายละเอียดตั๋ว #<?php echo $specific_ticket['ticket_id']; ?>
                </h4>
            </div>
            <div class="card-body">
                <!-- Ticket Status Banner -->
                <div class="alert 
                    <?php if ($specific_ticket['status'] == 'confirmed'): ?>
                        alert-success
                    <?php elseif ($specific_ticket['status'] == 'pending'): ?>
                        alert-warning
                    <?php elseif ($specific_ticket['status'] == 'issued'): ?>
                        alert-info
                    <?php else: ?>
                        alert-danger
                    <?php endif; ?>
                    mb-4">
                    <div class="d-flex align-items-center">
                        <div>
                            <h5 class="alert-heading">
                                <?php if ($specific_ticket['status'] == 'confirmed'): ?>
                                    <i class="fas fa-check-circle me-2"></i> ตั๋วได้รับการยืนยันแล้ว
                                <?php elseif ($specific_ticket['status'] == 'pending'): ?>
                                    <i class="fas fa-clock me-2"></i> ตั๋วรอการยืนยัน
                                <?php elseif ($specific_ticket['status'] == 'issued'): ?>
                                    <i class="fas fa-file-alt me-2"></i> ตั๋วออกแล้ว
                                <?php else: ?>
                                    <i class="fas fa-times-circle me-2"></i> ตั๋วถูกยกเลิก
                                <?php endif; ?>
                            </h5>
                            <p class="mb-0">
                                <?php if ($specific_ticket['status'] == 'confirmed'): ?>
                                    ตั๋วของคุณได้รับการยืนยันเรียบร้อยแล้ว กรุณาแสดงหมายเลขตั๋วหรือบัตรประชาชนที่จุดขึ้นรถ
                                <?php elseif ($specific_ticket['status'] == 'pending'): ?>
                                    ตั๋วของคุณกำลังรอการยืนยันจากพนักงาน กรุณาตรวจสอบอีกครั้งในภายหลัง
                                <?php elseif ($specific_ticket['status'] == 'issued'): ?>
                                    ตั๋วของคุณได้ออกแล้ว กรุณาแสดงหมายเลขตั๋วหรือบัตรประชาชนที่จุดขึ้นรถ
                                <?php else: ?>
                                    ตั๋วนี้ถูกยกเลิกแล้ว
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Main Ticket Info -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 bg-light">
                            <div class="card-body">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-route me-2"></i> ข้อมูลการเดินทาง
                                </h5>
                                <p><strong>เส้นทาง:</strong> <?php echo htmlspecialchars($specific_ticket['source'] . ' - ' . $specific_ticket['destination']); ?></p>
                                <p><strong>วันที่เดินทาง:</strong> <?php echo date('d/m/Y', strtotime($specific_ticket['date_travel'])); ?></p>
                                <p>
                                    <strong>เวลาออก-ถึง:</strong> 
                                    <?php echo date('H:i', strtotime($specific_ticket['departure_time'])); ?> - 
                                    <?php echo date('H:i', strtotime($specific_ticket['arrival_time'])); ?>
                                    <span class="text-muted">
                                        (<?php echo $specific_ticket['travel_duration']; ?>)
                                    </span>
                                </p>
                                <p><strong>หมายเลขจองที่นั่ง:</strong> <?php echo htmlspecialchars($specific_ticket['seat_number']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 bg-light">
                            <div class="card-body">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-bus me-2"></i> ข้อมูลรถและราคา
                                </h5>
                                <p><strong>รถ:</strong> <?php echo htmlspecialchars($specific_ticket['bus_name']); ?></p>
                                <p><strong>ประเภทรถ:</strong> <?php echo htmlspecialchars($specific_ticket['bus_type']); ?></p>
                                <p><strong>ราคา:</strong> <span class="fw-bold text-primary"><?php echo number_format($specific_ticket['priec'], 2); ?> บาท</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 bg-light">
                            <div class="card-body">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-file-alt me-2"></i> ข้อมูลการจอง
                                </h5>
                                <p><strong>หมายเลขตั๋ว:</strong> <?php echo $specific_ticket['ticket_id']; ?></p>
                                <p><strong>วันที่จอง:</strong> <?php echo date('d/m/Y H:i', strtotime($specific_ticket['booking_date'])); ?></p>
                                <?php if ($specific_ticket['ticket_date']): ?>
                                    <p><strong>วันที่ออกตั๋ว:</strong> <?php echo date('d/m/Y', strtotime($specific_ticket['ticket_date'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 bg-light">
                            <div class="card-body">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-info-circle me-2"></i> ข้อมูลเพิ่มเติม
                                </h5>
                                <ul class="mb-0">
                                    <li>กรุณามาถึงจุดออกรถก่อนเวลาอย่างน้อย 30 นาที</li>
                                    <li>เตรียมบัตรประชาชนหรือบัตรประจำตัวสำหรับการเดินทาง</li>
                                    <li>สามารถนำกระเป๋าขึ้นรถได้ไม่เกิน 20 กิโลกรัม</li>
                                    <li>หากมีข้อสงสัยโปรดติดต่อ 099-999-9999</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="/bus_booking_system/user/tickets.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> กลับไปรายการตั๋ว
                                </a>
                            </div>
                            
                            <?php if ($specific_ticket['status'] == 'pending'): ?>
                                <form method="POST" action="" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกตั๋วนี้?');">
                                    <input type="hidden" name="ticket_id" value="<?php echo $specific_ticket['ticket_id']; ?>">
                                    <button type="submit" name="cancel_ticket" class="btn btn-danger">
                                        <i class="fas fa-times"></i> ยกเลิกตั๋ว
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Tickets List View -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-ticket-alt me-2"></i> ตั๋วของฉัน
                </h4>
            </div>
            <div class="card-body">
                <!-- Filter Tabs -->
                <div class="mb-4">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?php echo empty($status_filter) ? 'active' : ''; ?>" href="?">
                                ทั้งหมด <span class="badge bg-secondary ms-1"><?php echo $ticket_counts['total']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="?status=pending">
                                รอการยืนยัน <span class="badge bg-warning ms-1"><?php echo $ticket_counts['pending']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>" href="?status=confirmed">
                                ยืนยันแล้ว <span class="badge bg-success ms-1"><?php echo $ticket_counts['confirmed']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'issued' ? 'active' : ''; ?>" href="?status=issued">
                                ออกตั๋วแล้ว <span class="badge bg-info ms-1"><?php echo $ticket_counts['issued']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>" href="?status=cancelled">
                                ยกเลิกแล้ว <span class="badge bg-danger ms-1"><?php echo $ticket_counts['cancelled']; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
                        <?php if (!empty($status_filter)): ?>
                            <h4 class="text-muted">ไม่พบตั๋ว</h4>
                        <?php else: ?>
                            <h4 class="text-muted">คุณยังไม่มีตั๋ว</h4>
                        <?php endif; ?>
                        <p class="lead">ต้องการจองตั๋ว?</p>
                        <a href="/bus_booking_system/search.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> ค้นหาเส้นทาง
                        </a>
                    </div>
                <?php else: ?>
                    <!-- List of tickets -->
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="card mb-3 border
                            <?php if ($ticket['status'] == 'confirmed'): ?>
                                border-success
                            <?php elseif ($ticket['status'] == 'pending'): ?>
                                border-warning
                            <?php elseif ($ticket['status'] == 'issued'): ?>
                                border-info
                            <?php else: ?>
                                border-danger
                            <?php endif; ?>
                        ">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col-lg-2 mb-3 mb-lg-0 text-center">
                                        <h5 class="mb-0"><?php echo date('d', strtotime($ticket['date_travel'])); ?></h5>
                                        <p class="small text-muted mb-0"><?php echo date('M Y', strtotime($ticket['date_travel'])); ?></p>
                                        <span class="badge 
                                            <?php if ($ticket['status'] == 'confirmed'): ?>
                                                bg-success
                                            <?php elseif ($ticket['status'] == 'pending'): ?>
                                                bg-warning
                                            <?php elseif ($ticket['status'] == 'issued'): ?>
                                                bg-info
                                            <?php else: ?>
                                                bg-danger
                                            <?php endif; ?>
                                            mt-2">
                                            <?php 
                                                echo $ticket['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 
                                                ($ticket['status'] == 'pending' ? 'รอการยืนยัน' : 
                                                ($ticket['status'] == 'issued' ? 'ออกตั๋วแล้ว' : 'ยกเลิก')); 
                                            ?>
                                        </span>
                                    </div>
                                    <div class="col-lg-5 mb-3 mb-lg-0">
                                        <p class="fw-bold mb-1">
                                            <?php echo htmlspecialchars($ticket['source'] . ' - ' . $ticket['destination']); ?>
                                        </p>
                                        <p class="mb-0 small">
                                            <i class="fas fa-clock text-muted me-1"></i> 
                                            <?php echo date('H:i', strtotime($ticket['departure_time'])); ?> - 
                                            <?php echo date('H:i', strtotime($ticket['arrival_time'])); ?>
                                        </p>
                                        <p class="mb-0 small">
                                            <i class="fas fa-bus text-muted me-1"></i> 
                                            <?php echo htmlspecialchars($ticket['bus_name'] . ' (' . $ticket['bus_type'] . ')'); ?>
                                        </p>
                                    </div>
                                    <div class="col-lg-3 mb-3 mb-lg-0">
                                        <p class="mb-0 small">
                                            <i class="fas fa-tag text-muted me-1"></i> 
                                            <span class="fw-bold"><?php echo number_format($ticket['priec'], 2); ?> บาท</span>
                                        </p>
                                        <p class="mb-0 small">
                                            <i class="fas fa-chair text-muted me-1"></i> 
                                            ที่นั่ง <?php echo htmlspecialchars($ticket['seat_number']); ?>
                                        </p>
                                        <p class="mb-0 small">
                                            <i class="fas fa-calendar-alt text-muted me-1"></i> 
                                            จองเมื่อ <?php echo date('d/m/Y', strtotime($ticket['booking_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-lg-2 text-lg-end">
                                        <a href="/bus_booking_system/user/tickets.php?id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-sm btn-primary mb-2 w-100">
                                            <i class="fas fa-eye"></i> ดูรายละเอียด
                                        </a>
                                        
                                        <?php if ($ticket['status'] == 'pending'): ?>
                                            <form method="POST" action="" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกตั๋วนี้?');">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                                <button type="submit" name="cancel_ticket" class="btn btn-sm btn-outline-danger w-100">
                                                    <i class="fas fa-times"></i> ยกเลิก
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Print Styles -->
<style>
@media print {
    .no-print, .no-print * {
        display: none !important;
    }
    
    nav, footer, .alert, form, .btn-secondary, .btn-danger {
        display: none !important;
    }
    
    body {
        padding: 0;
        margin: 0;
    }
    
    .container {
        width: 100%;
        max-width: none;
        padding: 0;
        margin: 0;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #fff !important;
        color: #000 !important;
        border-bottom: 1px solid #ddd !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>