<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Check if user is logged in as employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// Handle form submission for adding/editing route
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $route_id = isset($_POST['route_id']) ? (int)$_POST['route_id'] : 0;
    $source = $_POST['source'];
    $destination = $_POST['destination'];
    $detail = $_POST['detail'];

    $errors = [];

    // Validate input
    if (empty($source)) {
        $errors[] = "กรุณากรอกต้นทาง";
    }
    if (empty($destination)) {
        $errors[] = "กรุณากรอกปลายทาง";
    }

    if (empty($errors)) {
        try {
            if ($route_id > 0) {
                // Update existing route
                $stmt = $pdo->prepare("UPDATE Route SET source = ?, destination = ?, detail = ? WHERE route_id = ?");
                $stmt->execute([$source, $destination, $detail, $route_id]);
                $success = "อัปเดตข้อมูลเส้นทางสำเร็จ";
            } else {
                // Add new route
                $stmt = $pdo->prepare("INSERT INTO Route (source, destination, detail) VALUES (?, ?, ?)");
                $stmt->execute([$source, $destination, $detail]);
                $success = "เพิ่มเส้นทางใหม่สำเร็จ";
            }
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
    }
}

// Handle route deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $route_id = (int)$_GET['delete'];

    try {
        // Check if route is used in any schedule
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Schedule WHERE route_id = ?");
        $stmt->execute([$route_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $error = "ไม่สามารถลบเส้นทางได้เนื่องจากมีการใช้งานในตารางเดินรถ";
        } else {
            $stmt = $pdo->prepare("DELETE FROM Route WHERE route_id = ?");
            $stmt->execute([$route_id]);
            $success = "ลบเส้นทางสำเร็จ";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการลบเส้นทาง";
    }
}

// Get route for editing if ID is provided
$edit_route = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $route_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM Route WHERE route_id = ?");
    $stmt->execute([$route_id]);
    $edit_route = $stmt->fetch();
}

// Get all routes
$stmt = $pdo->query("SELECT * FROM Route ORDER BY route_id");
$routes = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo $edit_route ? 'แก้ไขข้อมูลเส้นทาง' : 'เพิ่มเส้นทางใหม่'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($edit_route): ?>
                            <input type="hidden" name="route_id" value="<?php echo $edit_route['route_id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="source" class="form-label">ต้นทาง</label>
                            <input type="text" class="form-control" id="source" name="source" value="<?php echo $edit_route ? htmlspecialchars($edit_route['source']) : ''; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="destination" class="form-label">ปลายทาง</label>
                            <input type="text" class="form-control" id="destination" name="destination" value="<?php echo $edit_route ? htmlspecialchars($edit_route['destination']) : ''; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="detail" class="form-label">รายละเอียดเพิ่มเติม</label>
                            <textarea class="form-control" id="detail" name="detail" rows="3"><?php echo $edit_route ? htmlspecialchars($edit_route['detail']) : ''; ?></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_route ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มเส้นทาง'; ?></button>
                            <?php if ($edit_route): ?>
                                <a href="routes.php" class="btn btn-outline-secondary">ยกเลิก</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">รายการเส้นทางทั้งหมด</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (empty($routes)): ?>
                        <div class="alert alert-info">ไม่มีข้อมูลเส้นทาง</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>รหัสเส้นทาง</th>
                                        <th>ต้นทาง</th>
                                        <th>ปลายทาง</th>
                                        <th>รายละเอียด</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($routes as $route): ?>
                                        <tr>
                                            <td><?php echo $route['route_id']; ?></td>
                                            <td><?php echo htmlspecialchars($route['source']); ?></td>
                                            <td><?php echo htmlspecialchars($route['destination']); ?></td>
                                            <td><?php echo htmlspecialchars($route['detail']); ?></td>
                                            <td>
                                                <a href="routes.php?edit=<?php echo $route['route_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-edit"></i> แก้ไข
                                                </a>
                                                <a href="routes.php?delete=<?php echo $route['route_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบเส้นทางนี้?');">
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