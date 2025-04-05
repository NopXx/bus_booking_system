<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM User WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get recent bookings
$stmt = $pdo->prepare("
    SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
           r.source, r.destination, b.bus_name
    FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    WHERE t.user_id = ?
    ORDER BY t.booking_date DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_bookings = $stmt->fetchAll();

// Get upcoming trips
$stmt = $pdo->prepare("
    SELECT t.*, s.date_travel, s.departure_time, s.arrival_time, s.priec,
           r.source, r.destination, b.bus_name, b.bus_type
    FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    WHERE t.user_id = ? AND s.date_travel >= CURDATE() AND t.status != 'cancelled'
    ORDER BY s.date_travel, s.departure_time
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_trips = $stmt->fetchAll();

// Get ticket statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_tickets,
        SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_tickets,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tickets,
        SUM(CASE WHEN s.date_travel < CURDATE() AND status IN ('confirmed', 'issued') THEN 1 ELSE 0 END) as completed_trips
    FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    WHERE t.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$ticket_stats = $stmt->fetch();

// Get popular routes (that the user hasn't booked yet)
$stmt = $pdo->prepare("
    SELECT r.route_id, r.source, r.destination, COUNT(t.ticket_id) as booking_count
    FROM Route r
    JOIN Schedule s ON r.route_id = s.route_id
    JOIN Ticket t ON s.schedule_id = t.schedule_id
    WHERE r.route_id NOT IN (
        SELECT DISTINCT r2.route_id
        FROM Ticket t2
        JOIN Schedule s2 ON t2.schedule_id = s2.schedule_id
        JOIN Route r2 ON s2.route_id = r2.route_id
        WHERE t2.user_id = ?
    )
    GROUP BY r.route_id, r.source, r.destination
    ORDER BY booking_count DESC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$popular_routes = $stmt->fetchAll();

// Fetch all unique sources and destinations from Route table for search form
$routeStmt = $pdo->query("SELECT DISTINCT source FROM Route ORDER BY source");
$sources = $routeStmt->fetchAll(PDO::FETCH_COLUMN);

$routeStmt = $pdo->query("SELECT DISTINCT destination FROM Route ORDER BY destination");
$destinations = $routeStmt->fetchAll(PDO::FETCH_COLUMN);

// Set minimum date to today for the date picker
$min_date = date('Y-m-d');
?>

<div class="container mt-4">
    <div class="row">
        <!-- Left Column - User Info and Quick Menu -->
        <div class="col-lg-3">
            <!-- User Profile Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <img src="https://cdn-icons-png.flaticon.com/512/219/219969.png" alt="User Avatar" class="rounded-circle mb-3" style="width: 100px; height: 100px;">
                    <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p class="text-muted small">สมาชิกตั้งแต่: <?php echo date('d/m/Y', strtotime($user['join_date'] ?? date('Y-m-d'))); ?></p>
                    <div class="d-grid">
                        <a href="/bus_booking_system/user/profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-edit"></i> แก้ไขโปรไฟล์
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Ticket Stats Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">สถิติการจอง</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>การจองทั้งหมด</span>
                        <span class="badge bg-primary rounded-pill"><?php echo $ticket_stats['total_tickets']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>รอการยืนยัน</span>
                        <span class="badge bg-warning rounded-pill"><?php echo $ticket_stats['pending_tickets']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>ยืนยันแล้ว</span>
                        <span class="badge bg-success rounded-pill"><?php echo $ticket_stats['confirmed_tickets']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>ออกตั๋วแล้ว</span>
                        <span class="badge bg-info rounded-pill"><?php echo $ticket_stats['issued_tickets']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>ยกเลิกแล้ว</span>
                        <span class="badge bg-danger rounded-pill"><?php echo $ticket_stats['cancelled_tickets']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>เดินทางเสร็จสิ้น</span>
                        <span class="badge bg-secondary rounded-pill"><?php echo $ticket_stats['completed_trips']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Menu Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">เมนูด่วน</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/bus_booking_system/search.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> ค้นหาเส้นทาง
                        </a>
                        <a href="/bus_booking_system/user/tickets.php" class="btn btn-outline-primary">
                            <i class="fas fa-ticket-alt"></i> ตั๋วของฉัน
                        </a>
                        <a href="/bus_booking_system/user/booking.php" class="btn btn-outline-primary">
                            <i class="fas fa-clipboard-list"></i> จองตั๋วใหม่
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Main Content -->
        <div class="col-lg-9">
            <!-- Travel Notice Alert -->
            <div class="alert alert-info mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading">ข้อแนะนำในการเดินทาง</h5>
                        <p class="mb-0">กรุณาเตรียมบัตรประชาชนหรือบัตรประจำตัวสำหรับการเดินทาง และควรมาถึงจุดขึ้นรถก่อนเวลาอย่างน้อย 30 นาที</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Search Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">ค้นหาเส้นทางด่วน</h5>
                </div>
                <div class="card-body">
                    <form action="/bus_booking_system/search.php" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="source" class="form-label">ต้นทาง</label>
                            <select class="form-select" id="source" name="source" required>
                                <option value="">เลือกต้นทาง</option>
                                <?php foreach ($sources as $src): ?>
                                    <option value="<?php echo htmlspecialchars($src); ?>">
                                        <?php echo htmlspecialchars($src); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="destination" class="form-label">ปลายทาง</label>
                            <select class="form-select" id="destination" name="destination" required>
                                <option value="">เลือกปลายทาง</option>
                                <?php foreach ($destinations as $dest): ?>
                                    <option value="<?php echo htmlspecialchars($dest); ?>">
                                        <?php echo htmlspecialchars($dest); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="date" class="form-label">วันที่เดินทาง</label>
                            <input type="date" class="form-control" id="date" name="date" min="<?php echo $min_date; ?>" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Upcoming Trips Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">การเดินทางที่กำลังจะมาถึง</h5>
                    <a href="/bus_booking_system/user/tickets.php" class="btn btn-sm btn-light">ดูทั้งหมด</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_trips)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="lead">คุณยังไม่มีการเดินทางที่กำลังจะมาถึง</p>
                            <a href="/bus_booking_system/search.php" class="btn btn-primary mt-2">ค้นหาเส้นทาง</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_trips as $trip): ?>
                            <div class="card mb-3 border-0 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-2 text-center">
                                            <div class="p-2">
                                                <h5 class="mb-0"><?php echo date('d', strtotime($trip['date_travel'])); ?></h5>
                                                <p class="small text-muted mb-0"><?php echo date('M Y', strtotime($trip['date_travel'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($trip['source'] . ' - ' . $trip['destination']); ?></h5>
                                            <p class="mb-0 text-muted">
                                                <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($trip['departure_time'])); ?> - 
                                                <?php echo date('H:i', strtotime($trip['arrival_time'])); ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-bus"></i> <?php echo htmlspecialchars($trip['bus_name'] . ' (' . $trip['bus_type'] . ')'); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <span class="badge <?php 
                                                echo $trip['status'] == 'pending' ? 'bg-warning' : 
                                                ($trip['status'] == 'confirmed' ? 'bg-success' : 'bg-info'); 
                                            ?> p-2">
                                                <?php 
                                                    echo $trip['status'] == 'pending' ? 'รอการยืนยัน' : 
                                                    ($trip['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 'ออกตั๋วแล้ว'); 
                                                ?>
                                            </span>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <a href="/bus_booking_system/user/tickets.php?id=<?php echo $trip['ticket_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> ดูรายละเอียด
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Bookings Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">ประวัติการจองล่าสุด</h5>
                    <a href="/bus_booking_system/user/tickets.php" class="btn btn-sm btn-light">ดูทั้งหมด</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_bookings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="lead">คุณยังไม่มีประวัติการจอง</p>
                            <a href="/bus_booking_system/search.php" class="btn btn-primary mt-2">จองตั๋วเลย</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>วันที่จอง</th>
                                        <th>เส้นทาง</th>
                                        <th>วันที่เดินทาง</th>
                                        <th>ราคา</th>
                                        <th>สถานะ</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($booking['source'] . ' - ' . $booking['destination']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($booking['date_travel'])); ?></td>
                                            <td><?php echo number_format($booking['priec'], 2); ?> บาท</td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $booking['status'] == 'confirmed' ? 'bg-success' : 
                                                    ($booking['status'] == 'pending' ? 'bg-warning' : 
                                                    ($booking['status'] == 'cancelled' ? 'bg-danger' : 'bg-info')); 
                                                ?>">
                                                    <?php 
                                                        echo $booking['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 
                                                        ($booking['status'] == 'pending' ? 'รอการยืนยัน' : 
                                                        ($booking['status'] == 'cancelled' ? 'ยกเลิก' : 'ออกตั๋วแล้ว')); 
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="/bus_booking_system/user/tickets.php?id=<?php echo $booking['ticket_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Popular Routes Card -->
            <?php if (!empty($popular_routes)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">เส้นทางที่น่าสนใจสำหรับคุณ</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($popular_routes as $route): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($route['source'] . ' - ' . $route['destination']); ?></h5>
                                    <p class="text-muted small">ยอดนิยม: <?php echo $route['booking_count']; ?> คนจอง</p>
                                    <div class="d-grid">
                                        <a href="/bus_booking_system/search.php?source=<?php echo urlencode($route['source']); ?>&destination=<?php echo urlencode($route['destination']); ?>" class="btn btn-outline-primary btn-sm">
                                            ดูรายละเอียด
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>