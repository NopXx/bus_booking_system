<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// Get schedule ID from URL if provided
$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

// Get schedule details if schedule_id is provided
$schedule = null;
if ($schedule_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, r.source, r.destination, b.bus_name, b.bus_type, e.first_name, e.last_name
        FROM Schedule s
        JOIN Route r ON s.route_id = r.route_id
        JOIN Bus b ON s.bus_id = b.bus_id
        JOIN employee e ON s.employee_id = e.employee_id
        WHERE s.schedule_id = ?
    ");
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        header('Location: /bus_booking_system/search.php');
        exit();
    }
    
    // Get booked seats
    $stmt = $pdo->prepare("SELECT seat_number FROM Ticket WHERE schedule_id = ? AND status != 'cancelled'");
    $stmt->execute([$schedule_id]);
    $booked_seats = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_id'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $seat_number = $_POST['seat_number'];
    
    // Validate seat number
    if (empty($seat_number)) {
        $error = "กรุณาเลือกที่นั่ง";
    } else {
        // Check if seat is already booked
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Ticket WHERE schedule_id = ? AND seat_number = ? AND status != 'cancelled'");
        $stmt->execute([$schedule_id, $seat_number]);
        if ($stmt->fetchColumn() > 0) {
            $error = "ที่นั่งนี้ถูกจองแล้ว กรุณาเลือกที่นั่งอื่น";
        } else {
            try {
                // Create new ticket
                $stmt = $pdo->prepare("
                    INSERT INTO Ticket (user_id, schedule_id, employee_id, seat_number, status, booking_date)
                    VALUES (?, ?, ?, ?, 'pending', CURDATE())
                ");
                $stmt->execute([$_SESSION['user_id'], $schedule_id, $schedule['employee_id'], $seat_number]);
                
                $success = "จองตั๋วสำเร็จ กรุณารอการยืนยันจากพนักงาน";
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาดในการจองตั๋ว";
            }
        }
    }
}
?>

<div class="container mt-5">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
            <div class="mt-2">
                <a href="/bus_booking_system/user/tickets.php" class="btn btn-primary btn-sm">ดูตั๋วของฉัน</a>
                <a href="/bus_booking_system/search.php" class="btn btn-outline-primary btn-sm">จองตั๋วเพิ่ม</a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($schedule): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">รายละเอียดการเดินทาง</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>ข้อมูลเส้นทาง</h5>
                        <p><strong>ต้นทาง:</strong> <?php echo htmlspecialchars($schedule['source']); ?></p>
                        <p><strong>ปลายทาง:</strong> <?php echo htmlspecialchars($schedule['destination']); ?></p>
                        <p><strong>วันที่เดินทาง:</strong> <?php echo date('d/m/Y', strtotime($schedule['date_travel'])); ?></p>
                        <p><strong>เวลาออก:</strong> <?php echo date('H:i', strtotime($schedule['departure_time'])); ?></p>
                        <p><strong>เวลาถึง:</strong> <?php echo date('H:i', strtotime($schedule['arrival_time'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5>ข้อมูลรถ</h5>
                        <p><strong>รถ:</strong> <?php echo htmlspecialchars($schedule['bus_name']); ?></p>
                        <p><strong>ประเภท:</strong> <?php echo htmlspecialchars($schedule['bus_type']); ?></p>
                        <p><strong>พนักงานขับรถ:</strong> <?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?></p>
                        <p><strong>ราคา:</strong> <?php echo number_format($schedule['priec'], 2); ?> บาท</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">เลือกที่นั่ง</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="seat-map mb-4">
                                <h5>แผนที่นั่ง</h5>
                                <div class="seat-legend mb-3">
                                    <span class="badge bg-success me-2">ว่าง</span>
                                    <span class="badge bg-danger me-2">ไม่ว่าง</span>
                                    <span class="badge bg-primary me-2">ที่เลือก</span>
                                </div>
                                
                                <div class="seat-grid">
                                    <?php
                                    // Simple seat map with 40 seats (4 rows x 10 seats)
                                    for ($row = 1; $row <= 4; $row++) {
                                        echo '<div class="seat-row">';
                                        for ($seat = 1; $seat <= 10; $seat++) {
                                            $seat_number = $row . str_pad($seat, 2, '0', STR_PAD_LEFT);
                                            $is_booked = in_array($seat_number, $booked_seats);
                                            $seat_class = $is_booked ? 'booked' : 'available';
                                            
                                            echo '<div class="seat ' . $seat_class . '" data-seat="' . $seat_number . '">';
                                            echo $seat_number;
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="seat_number" class="form-label">ที่นั่งที่เลือก</label>
                                <input type="text" class="form-control" id="seat_number" name="seat_number" readonly required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="booking-summary">
                                <h5>สรุปรายการจอง</h5>
                                <p><strong>ราคาตั๋ว:</strong> <?php echo number_format($schedule['priec'], 2); ?> บาท</p>
                                <p><strong>วันที่เดินทาง:</strong> <?php echo date('d/m/Y', strtotime($schedule['date_travel'])); ?></p>
                                <p><strong>เวลาออก:</strong> <?php echo date('H:i', strtotime($schedule['departure_time'])); ?></p>
                                <p><strong>ที่นั่ง:</strong> <span id="selected-seat-display">-</span></p>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary">ยืนยันการจอง</button>
                                    <a href="/bus_booking_system/search.php" class="btn btn-outline-secondary">ยกเลิก</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            กรุณาเลือกเส้นทางที่ต้องการจองจากหน้า <a href="/bus_booking_system/search.php">ค้นหาเส้นทาง</a>
        </div>
    <?php endif; ?>
</div>

<style>
.seat-map {
    border: 1px solid #ddd;
    padding: 20px;
    border-radius: 5px;
}

.seat-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.seat {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #ddd;
    border-radius: 5px;
    cursor: pointer;
    margin: 2px;
}

.seat.available {
    background-color: #28a745;
    color: white;
}

.seat.booked {
    background-color: #dc3545;
    color: white;
    cursor: not-allowed;
}

.seat.selected {
    background-color: #007bff;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const seats = document.querySelectorAll('.seat.available');
    const seatInput = document.getElementById('seat_number');
    const seatDisplay = document.getElementById('selected-seat-display');
    
    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            // Remove selected class from all seats
            document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
            
            // Add selected class to clicked seat
            this.classList.add('selected');
            
            // Update input and display
            const seatNumber = this.getAttribute('data-seat');
            seatInput.value = seatNumber;
            seatDisplay.textContent = seatNumber;
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>