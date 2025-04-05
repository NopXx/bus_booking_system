<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="/bus_booking_system/">ระบบจองตั๋วรถโดยสาร</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="/bus_booking_system/">หน้าแรก</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'search.php' ? 'active' : ''; ?>" href="/bus_booking_system/search.php">ค้นหาเส้นทาง</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'employee'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="employeeDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-tie"></i> พนักงาน
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/bus_booking_system/employee/">แดชบอร์ด</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/employee/buses.php">จัดการรถ</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/employee/routes.php">จัดการเส้นทาง</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/employee/schedules.php">จัดการตารางเดินรถ</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/employee/tickets.php">จัดการตั๋ว</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/employee/profile.php">โปรไฟล์</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/auth/logout.php">ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> ผู้ใช้
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/bus_booking_system/user/">แดชบอร์ด</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/user/booking.php">จองตั๋ว</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/user/tickets.php">ตั๋วของฉัน</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/user/profile.php">โปรไฟล์</a></li>
                                <li><a class="dropdown-item" href="/bus_booking_system/auth/logout.php">ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/bus_booking_system/auth/login.php">เข้าสู่ระบบ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/bus_booking_system/auth/register.php">สมัครสมาชิก</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>