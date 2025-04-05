<?php
session_start();
session_destroy();
header('Location: /bus_booking_system/auth/login.php');
exit();
?>