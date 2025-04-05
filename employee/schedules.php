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

// Handle form submission for adding/editing schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $bus_id = (int)$_POST['bus_id'];
    $route_id = (int)$_POST['route_id'];
    $date_travel = $_POST['date_travel'];
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = (float)$_POST['price'];
    
    $errors = [];
    
    // Validate input
    if ($bus_id <= 0) {
        $errors[] = "กรุณาเลือกรถ";
    }
    if ($route_id <= 0) {
        $errors[] = "กรุณาเลือกเส้นทาง";
    }
    if (empty($date_travel)) {
        $errors[] = "กรุณาเลือกวันที่เดินทาง";
    }
    if (empty($departure_time)) {
        $errors[] = "กรุณาระบุเวลาออกเดินทาง";
    }
    if (empty($arrival_time)) {
        $errors[] = "กรุณาระบุเวลาที่มาถึง";
    }
    if ($price <= 0) {
        $errors[] = "กรุณาระบุราคาที่ถูกต้อง";
    }
    
    if (empty($errors)) {
        try {
            if ($schedule_id > 0) {
                // Update existing schedule
                $stmt = $pdo->prepare("UPDATE Schedule SET bus_id = ?, route_id = ?, date_travel = ?, departure_time = ?, arrival_time = ?, priec = ? WHERE schedule_id = ?");
                $stmt->execute([$bus_id, $route_id, $date_travel, $departure_time, $arrival_time, $price, $schedule_id]);
                $success = "อัปเดตข้อมูลตารางเดินทางสำเร็จ";
            } else {
                // Add new schedule
                $stmt = $pdo->prepare("INSERT INTO Schedule (bus_id, route_id, employee_id, date_travel, departure_time, arrival_time, priec) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$bus_id, $route_id, $employee_id, $date_travel, $departure_time, $arrival_time, $price]);
                $success = "เพิ่มตารางเดินทางใหม่สำเร็จ";
            }
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            echo "Error: " . $e->getMessage();
        }
    }
}

// Handle schedule deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];
    
    try {
        // Check if schedule has any tickets
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Ticket WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $error = "ไม่สามารถลบตารางเดินทางได้เนื่องจากมีการจองตั๋วแล้ว";
        } else {
            $stmt = $pdo->prepare("DELETE FROM Schedule WHERE schedule_id = ?");
            $stmt->execute([$schedule_id]);
            $success = "ลบตารางเดินทางสำเร็จ";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการลบตารางเดินทาง";
    }
}

// Get schedule for editing if ID is provided
$edit_schedule = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $schedule_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM Schedule WHERE schedule_id = ?");
    $stmt->execute([$schedule_id]);
    $edit_schedule = $stmt->fetch();
}

// Get all buses for dropdown
$stmt = $pdo->query("SELECT * FROM Bus ORDER BY bus_id");
$buses = $stmt->fetchAll();

// Get all routes for dropdown
$stmt = $pdo->query("SELECT * FROM Route ORDER BY route_id");
$routes = $stmt->fetchAll();

// Get all schedules with related information
$stmt = $pdo->query("
    SELECT s.*, b.bus_name, b.bus_type, r.source, r.destination, e.first_name, e.last_name
    FROM Schedule s
    JOIN Bus b ON s.bus_id = b.bus_id
    JOIN Route r ON s.route_id = r.route_id
    JOIN employee e ON s.employee_id = e.employee_id
    ORDER BY s.date_travel DESC, s.departure_time ASC
");
$schedules = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo $edit_schedule ? 'แก้ไขข้อมูลตารางเดินทาง' : 'เพิ่มตารางเดินทางใหม่'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <?php if ($edit_schedule): ?>
                            <input type="hidden" name="schedule_id" value="<?php echo $edit_schedule['schedule_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="bus_id" class="form-label">รถ</label>
                            <select class="form-select" id="bus_id" name="bus_id" required>
                                <option value="">เลือกรถ</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?php echo $bus['bus_id']; ?>" <?php echo ($edit_schedule && $edit_schedule['bus_id'] == $bus['bus_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bus['bus_name'] . ' (' . $bus['bus_type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="route_id" class="form-label">เส้นทาง</label>
                            <select class="form-select" id="route_id" name="route_id" required>
                                <option value="">เลือกเส้นทาง</option>
                                <?php foreach ($routes as $route): ?>
                                    <option value="<?php echo $route['route_id']; ?>" <?php echo ($edit_schedule && $edit_schedule['route_id'] == $route['route_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($route['source'] . ' - ' . $route['destination']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_travel" class="form-label">วันที่เดินทาง</label>
                            <input type="date" class="form-control" id="date_travel" name="date_travel" value="<?php echo $edit_schedule ? $edit_schedule['date_travel'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="departure_time" class="form-label">เวลาออกเดินทาง</label>
                            <input type="time" class="form-control" id="departure_time" name="departure_time" value="<?php echo $edit_schedule ? $edit_schedule['departure_time'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="arrival_time" class="form-label">เวลาที่มาถึง</label>
                            <input type="time" class="form-control" id="arrival_time" name="arrival_time" value="<?php echo $edit_schedule ? $edit_schedule['arrival_time'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="price" class="form-label">ราคา (บาท)</label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?php echo $edit_schedule ? $edit_schedule['priec'] : ''; ?>" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_schedule ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มตารางเดินทาง'; ?></button>
                            <?php if ($edit_schedule): ?>
                                <a href="schedules.php" class="btn btn-outline-secondary">ยกเลิก</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">รายการตารางเดินทางทั้งหมด</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($schedules)): ?>
                        <div class="alert alert-info">ไม่มีข้อมูลตารางเดินทาง</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>เวลา</th>
                                        <th>รถ</th>
                                        <th>เส้นทาง</th>
                                        <th>ราคา</th>
                                        <th>พนักงาน</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($schedule['date_travel'])); ?></td>
                                            <td>
                                                <?php echo date('H:i', strtotime($schedule['departure_time'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['arrival_time'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($schedule['bus_name'] . ' (' . $schedule['bus_type'] . ')'); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['source'] . ' - ' . $schedule['destination']); ?></td>
                                            <td><?php echo number_format($schedule['priec'], 2); ?> บาท</td>
                                            <td><?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?></td>
                                            <td>
                                                <a href="schedules.php?edit=<?php echo $schedule['schedule_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-edit"></i> แก้ไข
                                                </a>
                                                <a href="schedules.php?delete=<?php echo $schedule['schedule_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบตารางเดินทางนี้?');">
                                                    <i class="fas fa-trash"></i> ลบ
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
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>