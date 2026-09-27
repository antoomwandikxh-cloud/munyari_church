<?php
session_start();
$_SESSION['admin_id'] = 1; // Assuming 1 is a valid admin id
$_GET['tab'] = 'desired_roles';
require 'admin_dashboard.php';
?>
