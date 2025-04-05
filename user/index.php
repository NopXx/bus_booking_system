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
           r.source, r.destination, b.bus_name
    FROM Ticket t
    JOIN Schedule s ON t.schedule_id = s.schedule_id
    JOIN Route r ON s.route_id = r.route_id
    JOIN Bus b ON s.bus_id = b.bus_id
    WHERE t.user_id = ? AND s.date_travel >= CURDATE()
    ORDER BY s.date_travel, s.departure_time
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_trips = $stmt->fetchAll();
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="/assets/img/user-avatar.png" alt="User Avatar" class="rounded-circle mb-3" style="width: 100px; height: 100px;">
                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($user['username']); ?></p>
                    <a href="/bus_booking_system/user/profile.php" class="btn btn-primary">แก้ไขโปรไฟล์</a>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">เมนูด่วน</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/bus_booking_system/user/booking.php" class="btn btn-outline-primary">
                            <i class="fas fa-ticket-alt"></i> จองตั๋วใหม่
                        </a>
                        <a href="/bus_booking_system/user/tickets.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> ดูตั๋วทั้งหมด
                        </a>
                        <a href="/bus_booking_system/search.php" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> ค้นหาเส้นทาง
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">การเดินทางที่กำลังจะมาถึง</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_trips)): ?>
                        <p class="text-center">ไม่มีการเดินทางที่กำลังจะมาถึง</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>เส้นทาง</th>
                                        <th>เวลา</th>
                                        <th>สถานะ</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_trips as $trip): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($trip['date_travel'])); ?></td>
                                            <td><?php echo htmlspecialchars($trip['source'] . ' - ' . $trip['destination']); ?></td>
                                            <td><?php echo date('H:i', strtotime($trip['departure_time'])); ?></td>
                                            <td>
                                                <?php if ($trip['status'] == 'confirmed'): ?>
                                                    <span class="badge bg-success">ยืนยันแล้ว</span>
                                                <?php elseif ($trip['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">รอการยืนยัน</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">ยกเลิก</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="/bus_booking_system/user/tickets.php?id=<?php echo $trip['ticket_id']; ?>" class="btn btn-sm btn-info">
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
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">ประวัติการจองล่าสุด</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_bookings)): ?>
                        <p class="text-center">ไม่มีประวัติการจอง</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>วันที่จอง</th>
                                        <th>เส้นทาง</th>
                                        <th>วันที่เดินทาง</th>
                                        <th>ราคา</th>
                                        <th>สถานะ</th>
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
                                                <?php if ($booking['status'] == 'confirmed'): ?>
                                                    <span class="badge bg-success">ยืนยันแล้ว</span>
                                                <?php elseif ($booking['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">รอการยืนยัน</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">ยกเลิก</span>
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
</div>

<?php require_once '../includes/footer.php'; ?>