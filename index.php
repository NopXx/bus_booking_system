<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Get featured routes
$stmt = $pdo->query("SELECT * FROM Route ORDER BY route_id DESC LIMIT 6");
$featured_routes = $stmt->fetchAll();

// Fetch all unique sources and destinations from Route table for dropdowns
$routeStmt = $pdo->query("SELECT DISTINCT source FROM Route ORDER BY source");
$sources = $routeStmt->fetchAll(PDO::FETCH_COLUMN);

$routeStmt = $pdo->query("SELECT DISTINCT destination FROM Route ORDER BY destination");
$destinations = $routeStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container-fluid p-0">
    <!-- Hero Section -->
    <div class="bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4">จองตั๋วรถโดยสารออนไลน์</h1>
                    <p class="lead">บริการจองตั๋วรถโดยสารออนไลน์ ที่ใช้งานง่าย สะดวก รวดเร็ว</p>
                    <a href="/bus_booking_system/search.php" class="btn btn-light btn-lg">ค้นหาเส้นทาง</a>
                </div>
                <div class="col-md-6">
                    <img src="/assets/img/bus-hero.jpg" alt="Bus" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">ค้นหาเส้นทาง</h3>
                <form action="/bus_booking_system/search.php" method="GET">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="source" class="form-label">ต้นทาง</label>
                            <select class="form-control" id="source" name="source" required>
                                <option value="">เลือกต้นทาง</option>
                                <?php foreach ($sources as $src): ?>
                                    <option value="<?php echo htmlspecialchars($src); ?>">
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
                                    <option value="<?php echo htmlspecialchars($dest); ?>">
                                        <?php echo htmlspecialchars($dest); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date" class="form-label">วันที่เดินทาง</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg">ค้นหา</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Featured Routes -->
    <div class="container mt-5">
        <h2 class="text-center mb-4">เส้นทางยอดนิยม</h2>
        <div class="row">
            <?php foreach ($featured_routes as $route): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($route['source']); ?> - <?php echo htmlspecialchars($route['destination']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($route['detail']); ?></p>
                            <a href="/bus_booking_system/search.php?source=<?php echo urlencode($route['source']); ?>&destination=<?php echo urlencode($route['destination']); ?>" class="btn btn-primary">ดูรายละเอียด</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container mt-5">
        <div class="row text-center">
            <div class="col-md-4 mb-4">
                <i class="fas fa-clock fa-3x text-primary mb-3"></i>
                <h4>จองตั๋ว 24 ชั่วโมง</h4>
                <p>บริการจองตั๋วตลอด 24 ชั่วโมง ไม่มีวันหยุด</p>
            </div>
            <div class="col-md-4 mb-4">
                <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                <h4>ปลอดภัย 100%</h4>
                <p>ระบบการชำระเงินที่ปลอดภัย รองรับการชำระเงินผ่านหลายช่องทาง</p>
            </div>
            <div class="col-md-4 mb-4">
                <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                <h4>บริการลูกค้า 24/7</h4>
                <p>ทีมงานพร้อมให้บริการและแก้ไขปัญหา ตลอด 24 ชั่วโมง</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>