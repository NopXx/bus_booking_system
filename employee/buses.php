<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Check if user is logged in as employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// Handle form submission for adding/editing bus
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bus_id = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
    $bus_name = $_POST['bus_name'];
    $bus_type = $_POST['bus_type'];
    
    $errors = [];
    
    // Validate input
    if (empty($bus_name)) {
        $errors[] = "กรุณากรอกชื่อรถ";
    }
    if (empty($bus_type)) {
        $errors[] = "กรุณากรอกประเภทรถ";
    }
    
    if (empty($errors)) {
        try {
            if ($bus_id > 0) {
                // Update existing bus
                $stmt = $pdo->prepare("UPDATE Bus SET bus_name = ?, bus_type = ? WHERE bus_id = ?");
                $stmt->execute([$bus_name, $bus_type, $bus_id]);
                $success = "อัปเดตข้อมูลรถสำเร็จ";
            } else {
                // Add new bus
                $stmt = $pdo->prepare("INSERT INTO Bus (bus_name, bus_type) VALUES (?, ?)");
                $stmt->execute([$bus_name, $bus_type]);
                $success = "เพิ่มรถใหม่สำเร็จ";
            }
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
    }
}

// Handle bus deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $bus_id = (int)$_GET['delete'];
    
    try {
        // Check if bus is used in any schedule
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Schedule WHERE bus_id = ?");
        $stmt->execute([$bus_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $error = "ไม่สามารถลบรถได้เนื่องจากมีการใช้งานในตารางเดินรถ";
        } else {
            $stmt = $pdo->prepare("DELETE FROM Bus WHERE bus_id = ?");
            $stmt->execute([$bus_id]);
            $success = "ลบรถสำเร็จ";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการลบรถ";
    }
}

// Get bus for editing if ID is provided
$edit_bus = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $bus_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM Bus WHERE bus_id = ?");
    $stmt->execute([$bus_id]);
    $edit_bus = $stmt->fetch();
}

// Get all buses
$stmt = $pdo->query("SELECT * FROM Bus ORDER BY bus_id");
$buses = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo $edit_bus ? 'แก้ไขข้อมูลรถ' : 'เพิ่มรถใหม่'; ?></h5>
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
                        <?php if ($edit_bus): ?>
                            <input type="hidden" name="bus_id" value="<?php echo $edit_bus['bus_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="bus_name" class="form-label">ชื่อรถ</label>
                            <input type="text" class="form-control" id="bus_name" name="bus_name" value="<?php echo $edit_bus ? htmlspecialchars($edit_bus['bus_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bus_type" class="form-label">ประเภทรถ</label>
                            <input type="text" class="form-control" id="bus_type" name="bus_type" value="<?php echo $edit_bus ? htmlspecialchars($edit_bus['bus_type']) : ''; ?>" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_bus ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มรถ'; ?></button>
                            <?php if ($edit_bus): ?>
                                <a href="buses.php" class="btn btn-outline-secondary">ยกเลิก</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">รายการรถทั้งหมด</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($buses)): ?>
                        <div class="alert alert-info">ไม่มีข้อมูลรถ</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>รหัสรถ</th>
                                        <th>ชื่อรถ</th>
                                        <th>ประเภทรถ</th>
                                        <th>การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($buses as $bus): ?>
                                        <tr>
                                            <td><?php echo $bus['bus_id']; ?></td>
                                            <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
                                            <td><?php echo htmlspecialchars($bus['bus_type']); ?></td>
                                            <td>
                                                <a href="buses.php?edit=<?php echo $bus['bus_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-edit"></i> แก้ไข
                                                </a>
                                                <a href="buses.php?delete=<?php echo $bus['bus_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบรถนี้?');">
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