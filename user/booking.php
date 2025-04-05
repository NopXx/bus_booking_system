<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// ดึงรหัสตารางเดินรถจาก URL หากมีการระบุ
$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

// ดึงรายละเอียดตารางเดินรถหากมีการระบุ schedule_id
$schedule = null;
if ($schedule_id > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, r.source, r.destination, r.detail, b.bus_name, b.bus_type, e.first_name, e.last_name,
               TIMEDIFF(s.arrival_time, s.departure_time) as travel_duration
        FROM Schedule s
        JOIN Route r ON s.route_id = r.route_id
        JOIN Bus b ON s.bus_id = b.bus_id
        JOIN employee e ON s.employee_id = e.employee_id
        WHERE s.schedule_id = ?
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    
    if (!$schedule) {
        header('Location: /bus_booking_system/search.php');
        exit();
    }
    
    // ดึงข้อมูลที่นั่งที่ถูกจองแล้ว
    $stmt = $conn->prepare("
        SELECT seat_number 
        FROM Ticket 
        WHERE schedule_id = ? AND status != 'cancelled'
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booked_seats = [];
    while ($row = $result->fetch_assoc()) {
        $booked_seats[] = $row['seat_number'];
    }
    
    // ดึงข้อมูลผู้ใช้
    $stmt = $conn->prepare("SELECT * FROM User WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// จัดการการส่งแบบฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_id'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $seat_number = $_POST['seat_number'];
    
    $errors = [];
    
    // ตรวจสอบความถูกต้องของหมายเลขที่นั่ง
    if (empty($seat_number)) {
        $errors[] = "กรุณาเลือกที่นั่ง";
    }
    
    if (empty($errors)) {
        // ตรวจสอบว่าที่นั่งถูกจองแล้วหรือไม่
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM Ticket 
            WHERE schedule_id = ? AND seat_number = ? AND status != 'cancelled'
        ");
        $stmt->bind_param("is", $schedule_id, $seat_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors[] = "ที่นั่งนี้ถูกจองแล้ว กรุณาเลือกที่นั่งอื่น";
        } else {
            try {
                // สร้างตั๋วใหม่
                $stmt = $conn->prepare("
                    INSERT INTO Ticket (
                        user_id, schedule_id, employee_id, 
                        seat_number, status, booking_date
                    ) VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("iiis", $_SESSION['user_id'], $schedule_id, $schedule['employee_id'], $seat_number);
                $stmt->execute();
                
                $ticket_id = $conn->insert_id;
                $success = "จองตั๋วสำเร็จ กรุณารอการยืนยันจากพนักงาน";
                $redirect_url = "/bus_booking_system/user/tickets.php?id=" . $ticket_id;
                
                // เปลี่ยนเส้นทางหลังจากหน่วงเวลาสักครู่
                header("refresh:2;url=$redirect_url");
            } catch (Exception $e) {
                $errors[] = "เกิดข้อผิดพลาดในการจองตั๋ว: " . $e->getMessage();
            }
        }
    }
}

// ดึงเส้นทางยอดนิยมหากไม่ได้เลือกตารางเวลา
$recent_routes = [];
if (!$schedule) {
    $result = $conn->query("
        SELECT r.route_id, r.source, r.destination, COUNT(t.ticket_id) as booking_count
        FROM Route r
        JOIN Schedule s ON r.route_id = s.route_id
        JOIN Ticket t ON s.schedule_id = t.schedule_id
        GROUP BY r.route_id
        ORDER BY booking_count DESC
        LIMIT 3
    ");
    while ($row = $result->fetch_assoc()) {
        $recent_routes[] = $row;
    }
}
?>

<div class="container mt-5">
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php foreach ($errors as $error): ?>
                <?php echo $error; ?><br>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <div class="mt-2">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                กำลังนำท่านไปยังหน้าตั๋วของคุณ...
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($schedule && !isset($success)): ?>
        <!-- Booking Page -->
        <div class="row">
            <div class="col-lg-4">
                <!-- Journey Details Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> รายละเอียดการเดินทาง</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="d-block text-primary fs-4 fw-bold"><?php echo date('d', strtotime($schedule['date_travel'])); ?></span>
                                <span class="d-block text-muted"><?php echo date('M Y', strtotime($schedule['date_travel'])); ?></span>
                            </div>
                            <i class="fas fa-bus fa-2x text-primary"></i>
                        </div>
                        
                        <h5 class="mb-3"><?php echo htmlspecialchars($schedule['source'] . ' - ' . $schedule['destination']); ?></h5>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <div class="text-center">
                                <span class="d-block fw-bold"><?php echo date('H:i', strtotime($schedule['departure_time'])); ?></span>
                                <small class="text-muted">ออกเดินทาง</small>
                            </div>
                            <div class="align-self-center px-2">
                                <div class="border-top border-2 position-relative" style="width: 50px;">
                                    <div class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-primary">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center">
                                <span class="d-block fw-bold"><?php echo date('H:i', strtotime($schedule['arrival_time'])); ?></span>
                                <small class="text-muted">ถึงที่หมาย</small>
                            </div>
                        </div>
                        
                        <small class="d-block text-center text-muted mb-3">
                            ระยะเวลาเดินทาง <?php echo $schedule['travel_duration']; ?>
                        </small>
                        
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">ประเภทรถ:</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($schedule['bus_type']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">ชื่อรถ:</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($schedule['bus_name']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">ราคา:</span>
                            <span class="fs-4 fw-bold text-primary"><?php echo number_format($schedule['priec'], 2); ?> บาท</span>
                        </div>
                        
                        <?php if (!empty($schedule['detail'])): ?>
                            <div class="alert alert-info mt-3 mb-0">
                                <small class="fw-bold">หมายเหตุ:</small>
                                <small class="d-block"><?php echo htmlspecialchars($schedule['detail']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Booking Summary Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i> สรุปการจอง</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">เส้นทาง:</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($schedule['source'] . ' - ' . $schedule['destination']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">วันที่เดินทาง:</span>
                                <span class="fw-bold"><?php echo date('d/m/Y', strtotime($schedule['date_travel'])); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">เวลาออกเดินทาง:</span>
                                <span class="fw-bold"><?php echo date('H:i', strtotime($schedule['departure_time'])); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">ที่นั่ง:</span>
                                <span class="fw-bold" id="selected-seat-display">-</span>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">ราคาตั๋ว:</span>
                            <span class="fs-4 fw-bold text-primary"><?php echo number_format($schedule['priec'], 2); ?> บาท</span>
                        </div>
                        
                        <div class="alert alert-warning small mb-0">
                            <div class="d-flex">
                                <div class="me-2">
                                    <i class="fas fa-info-circle text-warning"></i>
                                </div>
                                <div>
                                    <p class="mb-0">ตั๋วจะต้องได้รับการยืนยันจากพนักงานก่อนจึงจะสามารถใช้เดินทางได้</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-chair me-2"></i> เลือกที่นั่ง</h5>
                        <a href="/bus_booking_system/search.php" class="btn btn-sm btn-light">
                            <i class="fas fa-search"></i> ค้นหาเส้นทางอื่น
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="booking-form">
                            <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
                            <input type="hidden" name="seat_number" id="seat_number" value="">
                            
                            <!-- แผนผังที่นั่งรูปแบบใหม่ -->
                            <div class="seat-map-container mb-4">
                                <div class="seat-legend">
                                    <div class="d-flex gap-3">
                                        <div class="legend-item">
                                            <div class="seat-box available-seat"></div>
                                            <span>ว่าง</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="seat-box booked-seat"></div>
                                            <span>ไม่ว่าง</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="seat-box selected-seat-title"></div>
                                            <span>ที่นั่งที่เลือก</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bus-layout">
                                    <!-- หน้ารถ -->
                                    <div class="bus-header">หน้ารถ</div>
                                    
                                    <?php 
                                    // สร้างที่นั่งตามจำนวนแถวและคอลัมน์
                                    $rows = 10; // 10 แถว
                                    $cols = 4;  // 4 ที่นั่งต่อแถว (A, B, C, D)
                                    
                                    for ($row = 1; $row <= $rows; $row++): ?>
                                        <div class="seat-row">
                                            <?php for ($col = 1; $col <= $cols; $col++): 
                                                // กำหนดตัวอักษรสำหรับคอลัมน์ A, B, C, D
                                                $colLetter = chr(64 + $col); // 1 -> A, 2 -> B, 3 -> C, 4 -> D
                                                
                                                // สร้างรหัสที่นั่ง เช่น 1A, 1B, 2C, etc.
                                                $seatCode = $row . $colLetter;
                                                
                                                // ตรวจสอบว่าที่นั่งนี้ถูกจองแล้วหรือไม่
                                                $isBooked = in_array($seatCode, $booked_seats);
                                                $seatClass = $isBooked ? 'booked-seat' : 'available-seat';
                                                $bookingAttr = $isBooked ? 'title="ที่นั่งนี้ถูกจองแล้ว"' : '';
                                            ?>
                                                <div class="seat-box <?php echo $seatClass; ?>" data-seat="<?php echo $seatCode; ?>" <?php echo $bookingAttr; ?>>
                                                    <?php echo $seatCode; ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endfor; ?>
                                    
                                    <!-- ท้ายรถ -->
                                    <div class="bus-footer">ท้ายรถ</div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="seat_number_display" class="form-label">
                                            <i class="fas fa-chair me-1"></i> ที่นั่งที่เลือก
                                        </label>
                                        <input type="text" class="form-control" id="seat_number_display" readonly>
                                        <div id="seat-help" class="form-text">กรุณาเลือกที่นั่งจากแผนผังด้านบน</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-info-circle me-1"></i> หมายเหตุ
                                        </label>
                                        <p class="text-muted small mb-0">
                                            - ที่นั่งสีแดงไม่สามารถเลือกได้เนื่องจากมีผู้จองแล้ว<br>
                                            - กรุณาตรวจสอบความถูกต้องก่อนกดยืนยันการจอง
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check-circle me-2"></i> ยืนยันการจอง
                                </button>
                                <a href="/bus_booking_system/search.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> ยกเลิก
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- No Schedule Selected or Booking Completed -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-search me-2"></i> ค้นหาเส้นทางเพื่อจองตั๋ว</h4>
            </div>
            <div class="card-body py-5">
                <?php if (isset($success)): ?>
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                        <h4><?php echo $success; ?></h4>
                        <p>กำลังนำท่านไปยังหน้าตั๋วของคุณ...</p>
                    </div>
                <?php else: ?>
                    <div class="text-center mb-4">
                        <i class="fas fa-ticket-alt fa-4x text-primary mb-3"></i>
                        <h4>กรุณาเลือกเส้นทางที่ต้องการจองตั๋ว</h4>
                        <p class="text-muted">คุณสามารถค้นหาเส้นทางได้จากหน้า "ค้นหาเส้นทาง"</p>
                    </div>
                    
                    <div class="text-center mb-4">
                        <a href="/bus_booking_system/search.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i> ค้นหาเส้นทาง
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Popular routes section -->
                <?php if (!empty($recent_routes)): ?>
                    <div class="mt-5">
                        <h5 class="text-center mb-4">เส้นทางยอดนิยม</h5>
                        <div class="row">
                            <?php foreach ($recent_routes as $route): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h5 class="card-title"><?php echo htmlspecialchars($route['source'] . ' - ' . $route['destination']); ?></h5>
                                            <p class="card-text small text-muted">จำนวนการจอง: <?php echo $route['booking_count']; ?></p>
                                            <a href="/bus_booking_system/search.php?source=<?php echo urlencode($route['source']); ?>&destination=<?php echo urlencode($route['destination']); ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-search me-2"></i> ค้นหา
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* แผนผังที่นั่ง */
.seat-map-container {
    background-color: #f7f9fc;
    padding: 20px;
    border-radius: 10px;
    max-width: 600px;
    margin: 0 auto;
}

/* คำอธิบายสถานะที่นั่ง */
.seat-legend {
    display: flex;
    margin-bottom: 15px;
    justify-content: center;
}

.legend-item {
    display: flex;
    align-items: center;
    margin: 0 10px;
}

.legend-item span {
    margin-left: 5px;
    font-size: 14px;
}

/* โครงสร้างรถ */
.bus-layout {
    background-color: white;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.bus-header, .bus-footer {
    background-color: #343a40;
    color: white;
    text-align: center;
    padding: 8px 0;
    border-radius: 5px;
    margin-bottom: 15px;
    font-weight: bold;
}

.bus-footer {
    margin-top: 15px;
    margin-bottom: 0;
    background-color: #495057;
}

/* แถวที่นั่ง */
.seat-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

/* ที่นั่ง */
.seat-box {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    font-weight: bold;
    font-size: 14px;
    margin: 0 5px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.available-seat {
    background-color: #28a745;
    color: white;
    border: 2px solid #218838;
}

.available-seat:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.booked-seat {
    background-color: #dc3545;
    color: white;
    border: 2px solid #c82333;
    cursor: not-allowed;
    opacity: 0.8;
}

.selected-seat {
    background-color: #007bff;
    color: white;
    border: 2px solid #0069d9;
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}
.selected-seat-title {
    background-color: #007bff;
    color: white;
    border: 2px solid #0069d9;
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* รองรับการแสดงผลบนอุปกรณ์มือถือ */
@media (max-width: 576px) {
    .seat-box {
        width: 35px;
        height: 35px;
        font-size: 12px;
        margin: 0 2px;
    }
    
    .bus-header, .bus-footer {
        font-size: 14px;
        padding: 5px 0;
    }
    
    .seat-map-container {
        padding: 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // เลือกองค์ประกอบที่จำเป็น
    const seatBoxes = document.querySelectorAll('.seat-box.available-seat');
    const seatInput = document.getElementById('seat_number');
    const seatDisplay = document.getElementById('selected-seat-display');
    const seatNumberDisplay = document.getElementById('seat_number_display');
    const bookingForm = document.getElementById('booking-form');
    
    // เพิ่ม event listener สำหรับการเลือกที่นั่ง
    seatBoxes.forEach(seat => {
        seat.addEventListener('click', function() {
            // ลบคลาส selected จากที่นั่งที่เลือกก่อนหน้า
            document.querySelectorAll('.seat-box.selected-seat').forEach(s => {
                s.classList.remove('selected-seat');
                if (!s.classList.contains('booked-seat')) {
                    s.classList.add('available-seat');
                }
            });
            
            // เพิ่มคลาส selected ให้กับที่นั่งที่เลือก
            this.classList.remove('available-seat');
            this.classList.add('selected-seat');
            
            // อัปเดตข้อมูลที่นั่งในฟอร์มและในส่วนแสดงผล
            const seatNumber = this.getAttribute('data-seat');
            seatInput.value = seatNumber;
            
            if (seatDisplay) {
                seatDisplay.textContent = seatNumber;
            }
            
            if (seatNumberDisplay) {
                seatNumberDisplay.value = seatNumber;
            }
        });
    });
    
    // ตรวจสอบการส่งฟอร์ม
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            if (!seatInput.value) {
                e.preventDefault();
                alert('กรุณาเลือกที่นั่งก่อนทำการจอง');
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>