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

// Get statistics
// Total buses
$stmt = $pdo->query("SELECT COUNT(*) FROM Bus");
$total_buses = $stmt->fetchColumn();

// Total routes
$stmt = $pdo->query("SELECT COUNT(*) FROM Route");
$total_routes = $stmt->fetchColumn();

// Today's schedules
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Schedule WHERE date_travel = CURDATE()");
$stmt->execute();
$today_schedules = $stmt->fetchColumn();

// Pending tickets
$stmt = $pdo->query("SELECT COUNT(*) FROM Ticket WHERE status = 'pending'");
$pending_tickets = $stmt->fetchColumn();

// Confirmed tickets
$stmt = $pdo->query("SELECT COUNT(*) FROM Ticket WHERE status = 'confirmed'");
$confirmed_tickets = $stmt->fetchColumn();

// Issued tickets
$stmt = $pdo->query("SELECT COUNT(*) FROM Ticket WHERE status = 'issued'");
$issued_tickets = $stmt->fetchColumn();

// Cancelled tickets
$stmt = $pdo->query("SELECT COUNT(*) FROM Ticket WHERE status = 'cancelled'");
$cancelled_tickets = $stmt->fetchColumn();

// Total users
$stmt = $pdo->query("SELECT COUNT(*) FROM User");
$total_users = $stmt->fetchColumn();

// Total tickets
$stmt = $pdo->query("SELECT COUNT(*) FROM Ticket");
$total_tickets = $stmt->fetchColumn();

// Today's tickets
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    WHERE s.date_travel = CURDATE()
");
$stmt->execute();
$today_tickets = $stmt->fetchColumn();

// Total revenue
$stmt = $pdo->prepare("
    SELECT SUM(s.priec) FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    WHERE t.status IN ('confirmed', 'issued')
");
$stmt->execute();
$total_revenue = $stmt->fetchColumn();

// This month's revenue
$stmt = $pdo->prepare("
    SELECT SUM(s.priec) FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    WHERE t.status IN ('confirmed', 'issued')
    AND MONTH(t.booking_date) = MONTH(CURDATE()) 
    AND YEAR(t.booking_date) = YEAR(CURDATE())
");
$stmt->execute();
$month_revenue = $stmt->fetchColumn();

// Recent tickets
$stmt = $pdo->prepare("
    SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
           r.source, r.destination, b.bus_name, u.first_name, u.last_name
    FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    JOIN User u ON t.user_id = u.user_id
    ORDER BY t.booking_date DESC
    LIMIT 5
");
$stmt->execute();
$recent_tickets = $stmt->fetchAll();

// Today's schedules
$stmt = $pdo->prepare("
    SELECT s.*, r.source, r.destination, b.bus_name
    FROM Schedule s
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    WHERE s.date_travel = CURDATE()
    ORDER BY s.departure_time
");
$stmt->execute();
$today_schedule_list = $stmt->fetchAll();

// Popular routes (routes with most tickets)
$stmt = $pdo->query("
    SELECT r.route_id, r.source, r.destination, COUNT(t.ticket_id) as ticket_count
    FROM Route r
    JOIN Schedule s ON r.route_id = s.route_id
    JOIN Ticket t ON s.schedule_id = t.schedule_id
    GROUP BY r.route_id, r.source, r.destination
    ORDER BY ticket_count DESC
    LIMIT 5
");
$popular_routes = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="https://cdn-icons-png.flaticon.com/512/2815/2815428.png" alt="Employee Avatar" class="rounded-circle mb-3" style="width: 100px; height: 100px;">
                    <h4><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h4>
                    <p class="text-muted">พนักงาน ID: <?php echo $employee['employee_id']; ?></p>
                    <a href="/bus_booking_system/employee/profile.php" class="btn btn-primary">แก้ไขโปรไฟล์</a>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">เมนูด่วน</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/bus_booking_system/employee/buses.php" class="btn btn-outline-primary">
                            <i class="fas fa-bus"></i> จัดการรถ
                        </a>
                        <a href="/bus_booking_system/employee/routes.php" class="btn btn-outline-primary">
                            <i class="fas fa-route"></i> จัดการเส้นทาง
                        </a>
                        <a href="/bus_booking_system/employee/schedules.php" class="btn btn-outline-primary">
                            <i class="fas fa-calendar-alt"></i> จัดการตารางเดินรถ
                        </a>
                        <a href="/bus_booking_system/employee/tickets.php" class="btn btn-outline-primary">
                            <i class="fas fa-ticket-alt"></i> จัดการตั๋ว
                        </a>
                        <a href="/bus_booking_system/employee/users.php" class="btn btn-outline-primary">
                            <i class="fas fa-users"></i> จัดการผู้ใช้
                        </a>
                        <a href="/bus_booking_system/employee/reports.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-bar"></i> รายงาน
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">สถิติโดยรวม</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">รถทั้งหมด</h6>
                                        <i class="fas fa-bus fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $total_buses; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">เส้นทางทั้งหมด</h6>
                                        <i class="fas fa-route fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $total_routes; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">ผู้ใช้ทั้งหมด</h6>
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $total_users; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-dark text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">ตั๋วทั้งหมด</h6>
                                        <i class="fas fa-ticket-alt fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $total_tickets; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tickets Stats -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">สถานะตั๋ว</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-warning text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">รอการยืนยัน</h6>
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $pending_tickets; ?></h2>
                                </div>
                                <div class="card-footer bg-transparent border-0">
                                    <a href="/bus_booking_system/employee/tickets.php" class="text-white small stretched-link">ดูทั้งหมด</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">ยืนยันแล้ว</h6>
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $confirmed_tickets; ?></h2>
                                </div>
                                <div class="card-footer bg-transparent border-0">
                                    <a href="/bus_booking_system/employee/tickets.php" class="text-white small stretched-link">ดูทั้งหมด</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-secondary text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">ออกตั๋วแล้ว</h6>
                                        <i class="fas fa-file-alt fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $issued_tickets; ?></h2>
                                </div>
                                <div class="card-footer bg-transparent border-0">
                                    <a href="/bus_booking_system/employee/tickets.php" class="text-white small stretched-link">ดูทั้งหมด</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-danger text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">ยกเลิกแล้ว</h6>
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo $cancelled_tickets; ?></h2>
                                </div>
                                <div class="card-footer bg-transparent border-0">
                                    <a href="/bus_booking_system/employee/tickets.php" class="text-white small stretched-link">ดูทั้งหมด</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Stats -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">รายได้</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">รายได้เดือนนี้</h6>
                                        <i class="fas fa-calendar-alt fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo number_format($month_revenue, 2); ?> บาท</h2>
                                </div>
                                <div class="card-footer bg-transparent border-0">
                                    <a href="/bus_booking_system/employee/reports.php" class="text-white small stretched-link">ดูรายงาน</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-title">รายได้ทั้งหมด</h6>
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                    <h2 class="mb-0 mt-auto"><?php echo number_format($total_revenue, 2); ?> บาท</h2>
                                </div>
                                <div class="card-footer bg-transparent border-0">
                                    <a href="/bus_booking_system/employee/reports.php" class="text-white small stretched-link">ดูรายงาน</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">ตารางเดินรถวันนี้</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($today_schedule_list)): ?>
                                <p class="text-center">ไม่มีตารางเดินรถวันนี้</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>เส้นทาง</th>
                                                <th>รถ</th>
                                                <th>เวลาออก</th>
                                                <th>เวลาถึง</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($today_schedule_list as $schedule): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($schedule['source'] . ' - ' . $schedule['destination']); ?></td>
                                                    <td><?php echo htmlspecialchars($schedule['bus_name']); ?></td>
                                                    <td><?php echo date('H:i', strtotime($schedule['departure_time'])); ?></td>
                                                    <td><?php echo date('H:i', strtotime($schedule['arrival_time'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-2">
                                    <a href="/bus_booking_system/employee/schedules.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">การจองล่าสุด</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_tickets)): ?>
                                <p class="text-center">ไม่มีประวัติการจอง</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ผู้จอง</th>
                                                <th>เส้นทาง</th>
                                                <th>วันที่เดินทาง</th>
                                                <th>สถานะ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_tickets as $ticket): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($ticket['source'] . ' - ' . $ticket['destination']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($ticket['date_travel'])); ?></td>
                                                    <td>
                                                        <?php if ($ticket['status'] == 'confirmed'): ?>
                                                            <span class="badge bg-success">ยืนยันแล้ว</span>
                                                        <?php elseif ($ticket['status'] == 'pending'): ?>
                                                            <span class="badge bg-warning">รอการยืนยัน</span>
                                                        <?php elseif ($ticket['status'] == 'issued'): ?>
                                                            <span class="badge bg-secondary">ออกตั๋วแล้ว</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">ยกเลิก</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-2">
                                    <a href="/bus_booking_system/employee/tickets.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">เส้นทางยอดนิยม</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($popular_routes)): ?>
                                <p class="text-center">ไม่มีข้อมูลเส้นทางยอดนิยม</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>เส้นทาง</th>
                                                <th>จำนวนตั๋ว</th>
                                                <th>การดำเนินการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($popular_routes as $route): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($route['source'] . ' - ' . $route['destination']); ?></td>
                                                    <td><?php echo $route['ticket_count']; ?></td>
                                                    <td>
                                                        <a href="/bus_booking_system/employee/routes.php?edit=<?php echo $route['route_id']; ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-2">
                                    <a href="/bus_booking_system/employee/reports.php?type=route_performance" class="btn btn-sm btn-outline-primary">ดูรายงานเส้นทาง</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>