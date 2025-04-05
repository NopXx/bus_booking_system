<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Get featured routes
$stmt = $pdo->query("SELECT * FROM Route ORDER BY route_id DESC LIMIT 6");
$featured_routes = $stmt->fetchAll();

// Get popular routes (based on tickets)
$stmt = $pdo->query("
    SELECT r.*, COUNT(t.ticket_id) as ticket_count
    FROM Route r
    LEFT JOIN Schedule s ON r.route_id = s.route_id
    LEFT JOIN Ticket t ON s.schedule_id = t.schedule_id
    GROUP BY r.route_id
    ORDER BY ticket_count DESC
    LIMIT 6
");
$popular_routes = $stmt->fetchAll();

// Get next departure schedules
$stmt = $pdo->prepare("
    SELECT s.*, r.source, r.destination, b.bus_name, b.bus_type, e.first_name, e.last_name
    FROM Schedule s
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    JOIN employee e ON s.employee_id = e.employee_id
    WHERE s.date_travel >= CURDATE()
    ORDER BY s.date_travel, s.departure_time
    LIMIT 5
");
$stmt->execute();
$upcoming_departures = $stmt->fetchAll();

// Get count of total buses, routes, and schedules
$stmt = $pdo->query("SELECT COUNT(*) FROM Bus");
$total_buses = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM Route");
$total_routes = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM Schedule");
$total_schedules = $stmt->fetchColumn();

// Fetch all unique sources and destinations from Route table for dropdowns
$routeStmt = $pdo->query("SELECT DISTINCT source FROM Route ORDER BY source");
$sources = $routeStmt->fetchAll(PDO::FETCH_COLUMN);

$routeStmt = $pdo->query("SELECT DISTINCT destination FROM Route ORDER BY destination");
$destinations = $routeStmt->fetchAll(PDO::FETCH_COLUMN);

// Set minimum date to today for the date picker
$min_date = date('Y-m-d');
?>

<!-- Add CSS for the hero section with search -->
<style>
.hero-section {
    background: linear-gradient(135deg, #0062cc 0%, #0096ff 100%);
    padding: 50px 0;
}
.card {
    transition: transform 0.3s;
}
.card:hover {
    transform: translateY(-5px);
}
</style>

<!-- Hero Section with Search -->
<div class="hero-section bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">จองตั๋วรถโดยสารออนไลน์</h1>
                <p class="lead mb-4">บริการจองตั๋วรถโดยสารที่สะดวก รวดเร็ว และปลอดภัย ครอบคลุมเส้นทางทั่วประเทศ</p>
                <div class="d-flex gap-2">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="/bus_booking_system/auth/register.php" class="btn btn-light btn-lg">สมัครสมาชิก</a>
                        <a href="/bus_booking_system/auth/login.php" class="btn btn-outline-light btn-lg">เข้าสู่ระบบ</a>
                    <?php else: ?>
                        <a href="<?php echo $_SESSION['role'] === 'user' ? '/bus_booking_system/user/tickets.php' : '/bus_booking_system/employee/index.php'; ?>" class="btn btn-light btn-lg">
                            <?php echo $_SESSION['role'] === 'user' ? 'ตั๋วของฉัน' : 'แดชบอร์ด'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 mt-4 mt-lg-0">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center text-dark mb-4">ค้นหาเส้นทาง</h3>
                        <form action="/bus_booking_system/search.php" method="GET">
                            <div class="mb-3">
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
                            <div class="mb-3">
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
                            <div class="mb-3">
                                <label for="date" class="form-label">วันที่เดินทาง</label>
                                <input type="date" class="form-control" id="date" name="date" min="<?php echo $min_date; ?>" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">ค้นหาเส้นทาง</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Section -->
<div class="container mt-5">
    <div class="row justify-content-center text-center">
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-5">
                    <i class="fas fa-bus fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold"><?php echo $total_buses; ?>+</h2>
                    <h5>รถโดยสาร</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-5">
                    <i class="fas fa-route fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold"><?php echo $total_routes; ?>+</h2>
                    <h5>เส้นทาง</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-5">
                    <i class="fas fa-calendar-alt fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold"><?php echo $total_schedules; ?>+</h2>
                    <h5>รอบเดินทาง</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Popular Routes Section -->
<div class="container mt-5">
    <h2 class="text-center mb-4">เส้นทางยอดนิยม</h2>
    <div class="row">
        <?php foreach ($popular_routes as $route): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($route['source']); ?> - 
                                <?php echo htmlspecialchars($route['destination']); ?>
                            </h5>
                            <span class="badge bg-primary rounded-pill">
                                <i class="fas fa-users"></i> <?php echo $route['ticket_count'] > 0 ? $route['ticket_count'] : '0'; ?> จอง
                            </span>
                        </div>
                        <p class="card-text">
                            <?php echo htmlspecialchars($route['detail'] ? $route['detail'] : 'เดินทางสะดวก รวดเร็ว ปลอดภัย'); ?>
                        </p>
                        <a href="/bus_booking_system/search.php?source=<?php echo urlencode($route['source']); ?>&destination=<?php echo urlencode($route['destination']); ?>" class="btn btn-outline-primary">
                            ดูรายละเอียด
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-3">
        <a href="/bus_booking_system/search.php" class="btn btn-primary">ดูเส้นทางทั้งหมด</a>
    </div>
</div>

<!-- Upcoming Departures -->
<div class="container mt-5">
    <h2 class="text-center mb-4">รอบเดินทางที่กำลังจะมาถึง</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th>เวลา</th>
                                    <th>เส้นทาง</th>
                                    <th>รถ</th>
                                    <th>ราคา</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($upcoming_departures)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">ไม่พบรอบการเดินทางในขณะนี้</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($upcoming_departures as $schedule): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($schedule['date_travel'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($schedule['departure_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['source'] . ' - ' . $schedule['destination']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['bus_name'] . ' (' . $schedule['bus_type'] . ')'); ?></td>
                                            <td><?php echo number_format($schedule['priec'], 2); ?> บาท</td>
                                            <td>
                                                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                                                    <a href="/bus_booking_system/user/booking.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-sm btn-primary">จองตั๋ว</a>
                                                <?php else: ?>
                                                    <a href="/bus_booking_system/auth/login.php" class="btn btn-sm btn-primary">เข้าสู่ระบบเพื่อจอง</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>