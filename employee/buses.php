<?php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบในฐานะพนักงานหรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: /bus_booking_system/auth/login.php');
    exit();
}

// จัดการการส่งแบบฟอร์มสำหรับการเพิ่ม/แก้ไขข้อมูลรถ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bus_id = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
    $bus_name = $_POST['bus_name'];
    $bus_type = $_POST['bus_type'];

    $errors = [];

    // ตรวจสอบความถูกต้องของข้อมูล
    if (empty($bus_name)) {
        $errors[] = "กรุณากรอกชื่อรถ";
    }
    if (empty($bus_type)) {
        $errors[] = "กรุณากรอกประเภทรถ";
    }

    if (empty($errors)) {
        try {
            if ($bus_id > 0) {
                // อัปเดตข้อมูลรถที่มีอยู่
                $stmt = $conn->prepare("UPDATE Bus SET bus_name = ?, bus_type = ? WHERE bus_id = ?");
                $stmt->bind_param("ssi", $bus_name, $bus_type, $bus_id);
                $stmt->execute();
                $success = "อัปเดตข้อมูลรถสำเร็จ";
            } else {
                // เพิ่มรถใหม่
                $stmt = $conn->prepare("INSERT INTO Bus (bus_name, bus_type) VALUES (?, ?)");
                $stmt->bind_param("ss", $bus_name, $bus_type);
                $stmt->execute();
                $success = "เพิ่มรถใหม่สำเร็จ";
            }
        } catch (Exception $e) {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
    }
}

// จัดการการลบรถ
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $bus_id = (int)$_GET['delete'];

    try {
        // ตรวจสอบว่ารถถูกใช้ในตารางเดินรถหรือไม่
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Schedule WHERE bus_id = ?");
        $stmt->bind_param("i", $bus_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = $row['count'];

        if ($count > 0) {
            $error = "ไม่สามารถลบรถได้เนื่องจากมีการใช้งานในตารางเดินรถ";
        } else {
            $stmt = $conn->prepare("DELETE FROM Bus WHERE bus_id = ?");
            $stmt->bind_param("i", $bus_id);
            $stmt->execute();
            $success = "ลบรถสำเร็จ";
        }
    } catch (Exception $e) {
        $error = "เกิดข้อผิดพลาดในการลบรถ";
    }
}

// ดึงข้อมูลรถสำหรับการแก้ไขหากมีการระบุ ID
$edit_bus = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $bus_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM Bus WHERE bus_id = ?");
    $stmt->bind_param("i", $bus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_bus = $result->fetch_assoc();
}

// ดึงข้อมูลรถทั้งหมด
$result = $conn->query("SELECT * FROM Bus ORDER BY bus_id");
$buses = [];
while ($row = $result->fetch_assoc()) {
    $buses[] = $row;
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo $edit_bus ? 'แก้ไขข้อมูลรถ' : 'เพิ่มรถใหม่'; ?></h5>
                </div>
                <div class="card-body">
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
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

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