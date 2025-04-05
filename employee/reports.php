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

// Default date range (current month)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'booking_summary';

// Get report data based on type
$report_data = [];
$report_title = '';
$report_description = '';

if ($report_type == 'booking_summary') {
    $report_title = 'รายงานสรุปการจองตั๋ว';
    $report_description = 'แสดงสรุปการจองตั๋วในช่วงวันที่ที่เลือก';
    
    // Get booking summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(CASE WHEN status IN ('confirmed', 'issued') THEN s.priec ELSE 0 END) as total_revenue
        FROM Ticket t
        JOIN Schedule s ON t.schedule_id = s.schedule_id
        WHERE t.booking_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $report_data['summary'] = $stmt->fetch();
    
    // Get bookings by day
    $stmt = $pdo->prepare("
        SELECT 
            DATE(t.booking_date) as date,
            COUNT(*) as total_bookings,
            SUM(CASE WHEN t.status IN ('confirmed', 'issued') THEN s.priec ELSE 0 END) as revenue
        FROM Ticket t
        JOIN Schedule s ON t.schedule_id = s.schedule_id
        WHERE t.booking_date BETWEEN ? AND ?
        GROUP BY DATE(t.booking_date)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $report_data['daily'] = $stmt->fetchAll();
    
} elseif ($report_type == 'route_performance') {
    $report_title = 'รายงานประสิทธิภาพเส้นทาง';
    $report_description = 'แสดงประสิทธิภาพของแต่ละเส้นทางในช่วงวันที่ที่เลือก';
    
    // Get route performance
    $stmt = $pdo->prepare("
        SELECT 
            r.route_id,
            r.source,
            r.destination,
            COUNT(DISTINCT s.schedule_id) as total_schedules,
            COUNT(t.ticket_id) as total_bookings,
            SUM(CASE WHEN t.status IN ('confirmed', 'issued') THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN t.status IN ('confirmed', 'issued') THEN s.priec ELSE 0 END) as revenue
        FROM Route r
        LEFT JOIN Schedule s ON r.route_id = s.route_id AND s.date_travel BETWEEN ? AND ?
        LEFT JOIN Ticket t ON s.schedule_id = t.schedule_id
        GROUP BY r.route_id, r.source, r.destination
        ORDER BY revenue DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $report_data['routes'] = $stmt->fetchAll();
    
} elseif ($report_type == 'bus_utilization') {
    $report_title = 'รายงานการใช้งานรถ';
    $report_description = 'แสดงการใช้งานรถแต่ละคันในช่วงวันที่ที่เลือก';
    
    // Get bus utilization
    $stmt = $pdo->prepare("
        SELECT 
            b.bus_id,
            b.bus_name,
            b.bus_type,
            COUNT(DISTINCT s.schedule_id) as total_schedules,
            COUNT(t.ticket_id) as total_bookings,
            SUM(CASE WHEN t.status IN ('confirmed', 'issued') THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN t.status IN ('confirmed', 'issued') THEN s.priec ELSE 0 END) as revenue
        FROM Bus b
        LEFT JOIN Schedule s ON b.bus_id = s.bus_id AND s.date_travel BETWEEN ? AND ?
        LEFT JOIN Ticket t ON s.schedule_id = t.schedule_id
        GROUP BY b.bus_id, b.bus_name, b.bus_type
        ORDER BY total_schedules DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $report_data['buses'] = $stmt->fetchAll();
    
} elseif ($report_type == 'employee_performance') {
    $report_title = 'รายงานประสิทธิภาพพนักงาน';
    $report_description = 'แสดงประสิทธิภาพของพนักงานแต่ละคนในช่วงวันที่ที่เลือก';
    
    // Get employee performance
    $stmt = $pdo->prepare("
        SELECT 
            e.employee_id,
            e.first_name,
            e.last_name,
            COUNT(DISTINCT s.schedule_id) as total_schedules,
            COUNT(t.ticket_id) as total_bookings,
            SUM(CASE WHEN t.status IN ('confirmed', 'issued') THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN t.status IN ('confirmed', 'issued') THEN s.priec ELSE 0 END) as revenue
        FROM employee e
        LEFT JOIN Schedule s ON e.employee_id = s.employee_id AND s.date_travel BETWEEN ? AND ?
        LEFT JOIN Ticket t ON s.schedule_id = t.schedule_id
        GROUP BY e.employee_id, e.first_name, e.last_name
        ORDER BY total_schedules DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $report_data['employees'] = $stmt->fetchAll();
}
?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><?php echo $report_title; ?></h5>
        </div>
        <div class="card-body">
            <p class="text-muted"><?php echo $report_description; ?></p>
            
            <!-- Report filters -->
            <form method="GET" action="" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="type" class="form-label">ประเภทรายงาน</label>
                    <select class="form-select" id="type" name="type">
                        <option value="booking_summary" <?php echo $report_type == 'booking_summary' ? 'selected' : ''; ?>>รายงานสรุปการจองตั๋ว</option>
                        <option value="route_performance" <?php echo $report_type == 'route_performance' ? 'selected' : ''; ?>>รายงานประสิทธิภาพเส้นทาง</option>
                        <option value="bus_utilization" <?php echo $report_type == 'bus_utilization' ? 'selected' : ''; ?>>รายงานการใช้งานรถ</option>
                        <option value="employee_performance" <?php echo $report_type == 'employee_performance' ? 'selected' : ''; ?>>รายงานประสิทธิภาพพนักงาน</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">วันที่เริ่มต้น</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">วันที่สิ้นสุด</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> แสดงรายงาน
                    </button>
                </div>
            </form>
            
            <!-- Report content -->
            <?php if ($report_type == 'booking_summary'): ?>
                <!-- Booking Summary Report -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">สรุปการจองตั๋ว</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">จำนวนการจองทั้งหมด</h6>
                                        <h3 class="mb-0"><?php echo number_format($report_data['summary']['total_bookings']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">รอการยืนยัน</h6>
                                        <h3 class="mb-0"><?php echo number_format($report_data['summary']['pending_bookings']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">ยืนยันแล้ว</h6>
                                        <h3 class="mb-0"><?php echo number_format($report_data['summary']['confirmed_bookings']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">ออกตั๋วแล้ว</h6>
                                        <h3 class="mb-0"><?php echo number_format($report_data['summary']['issued_bookings']); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">ยกเลิกแล้ว</h6>
                                        <h3 class="mb-0"><?php echo number_format($report_data['summary']['cancelled_bookings']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">รายได้รวม</h6>
                                        <h3 class="mb-0"><?php echo number_format($report_data['summary']['total_revenue'], 2); ?> บาท</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">การจองรายวัน</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>จำนวนการจอง</th>
                                        <th>รายได้ (บาท)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['daily'] as $day): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($day['date'])); ?></td>
                                            <td><?php echo number_format($day['total_bookings']); ?></td>
                                            <td><?php echo number_format($day['revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($report_type == 'route_performance'): ?>
                <!-- Route Performance Report -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>เส้นทาง</th>
                                <th>จำนวนตารางเดินทาง</th>
                                <th>จำนวนการจอง</th>
                                <th>การจองที่ยืนยันแล้ว</th>
                                <th>รายได้ (บาท)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['routes'] as $route): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($route['source'] . ' - ' . $route['destination']); ?></td>
                                    <td><?php echo number_format($route['total_schedules']); ?></td>
                                    <td><?php echo number_format($route['total_bookings']); ?></td>
                                    <td><?php echo number_format($route['confirmed_bookings']); ?></td>
                                    <td><?php echo number_format($route['revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($report_type == 'bus_utilization'): ?>
                <!-- Bus Utilization Report -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>รถ</th>
                                <th>ประเภท</th>
                                <th>จำนวนตารางเดินทาง</th>
                                <th>จำนวนการจอง</th>
                                <th>การจองที่ยืนยันแล้ว</th>
                                <th>รายได้ (บาท)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['buses'] as $bus): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
                                    <td><?php echo htmlspecialchars($bus['bus_type']); ?></td>
                                    <td><?php echo number_format($bus['total_schedules']); ?></td>
                                    <td><?php echo number_format($bus['total_bookings']); ?></td>
                                    <td><?php echo number_format($bus['confirmed_bookings']); ?></td>
                                    <td><?php echo number_format($bus['revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($report_type == 'employee_performance'): ?>
                <!-- Employee Performance Report -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>พนักงาน</th>
                                <th>จำนวนตารางเดินทาง</th>
                                <th>จำนวนการจอง</th>
                                <th>การจองที่ยืนยันแล้ว</th>
                                <th>รายได้ (บาท)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['employees'] as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                    <td><?php echo number_format($employee['total_schedules']); ?></td>
                                    <td><?php echo number_format($employee['total_bookings']); ?></td>
                                    <td><?php echo number_format($employee['confirmed_bookings']); ?></td>
                                    <td><?php echo number_format($employee['revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Export buttons -->
            <div class="mt-4">
                <button type="button" class="btn btn-success" onclick="window.print();">
                    <i class="fas fa-print"></i> พิมพ์รายงาน
                </button>
                <button type="button" class="btn btn-info" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> ส่งออกเป็น Excel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // This is a placeholder for Excel export functionality
    // In a real implementation, you would use a library like SheetJS or make an AJAX call to a server-side script
    alert('ฟังก์ชันส่งออกเป็น Excel ยังไม่พร้อมใช้งาน');
}
</script>

<?php require_once '../includes/footer.php'; ?>