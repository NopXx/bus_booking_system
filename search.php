<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

$source = $_GET['source'] ?? '';
$destination = $_GET['destination'] ?? '';
$date = $_GET['date'] ?? '';

// ดึงต้นทางและปลายทางที่ไม่ซ้ำกันจากตาราง Route
$result = $conn->query("SELECT DISTINCT source FROM Route ORDER BY source");
$sources = [];
while ($row = $result->fetch_assoc()) {
    $sources[] = $row['source'];
}

$result = $conn->query("SELECT DISTINCT destination FROM Route ORDER BY destination");
$destinations = [];
while ($row = $result->fetch_assoc()) {
    $destinations[] = $row['destination'];
}

$schedules = [];
if ($source && $destination && $date) {
    $stmt = $conn->prepare("
        SELECT s.*, r.source, r.destination, b.bus_name, b.bus_type, e.first_name, e.last_name
        FROM Schedule s
        JOIN Route r ON s.route_id = r.route_id
        JOIN Bus b ON s.bus_id = b.bus_id
        JOIN employee e ON s.employee_id = e.employee_id
        WHERE r.source = ? AND r.destination = ? AND s.date_travel = ?
        ORDER BY s.departure_time
    ");
    $stmt->bind_param("sss", $source, $destination, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
?>

<div class="container mt-5">
    <div class="card shadow mb-4">
        <div class="card-body">
            <h3 class="card-title text-center mb-4">ค้นหาเส้นทาง</h3>
            <form action="" method="GET">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="source" class="form-label">ต้นทาง</label>
                        <select class="form-control" id="source" name="source" required>
                            <option value="">เลือกต้นทาง</option>
                            <?php foreach ($sources as $src): ?>
                                <option value="<?php echo htmlspecialchars($src); ?>" 
                                    <?php echo $source === $src ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($src); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="destination" class="form-label">ปลายทาง</label>
                        <select class="form-control" id="destination" name="destination" required>
                            <option value="">เลือกปลายทาง</option>
                            <?php foreach ($destinations as $dest): ?>
                                <option value="<?php echo htmlspecialchars($dest); ?>" 
                                    <?php echo $destination === $dest ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dest); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date" class="form-label">วันที่เดินทาง</label>
                        <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>" required>
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">ค้นหา</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($source && $destination && $date): ?>
        <?php if (empty($schedules)): ?>
            <div class="alert alert-info">
                ไม่พบเส้นทางที่ต้องการในวันที่ระบุ
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($schedules as $schedule): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?php echo htmlspecialchars($schedule['source']); ?> - 
                                    <?php echo htmlspecialchars($schedule['destination']); ?>
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>รถ:</strong> <?php echo htmlspecialchars($schedule['bus_name']); ?></p>
                                        <p><strong>ประเภท:</strong> <?php echo htmlspecialchars($schedule['bus_type']); ?></p>
                                        <p><strong>เวลาออก:</strong> <?php echo date('H:i', strtotime($schedule['departure_time'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>เวลาถึง:</strong> <?php echo date('H:i', strtotime($schedule['arrival_time'])); ?></p>
                                        <p><strong>ราคา:</strong> <?php echo number_format($schedule['priec'], 2); ?> บาท</p>
                                    </div>
                                </div>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="/bus_booking_system/user/booking.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-primary">จองตั๋ว</a>
                                <?php else: ?>
                                    <a href="/bus_booking_system/auth/login.php" class="btn btn-primary">เข้าสู่ระบบเพื่อจองตั๋ว</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>