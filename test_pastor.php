<?php
session_start();
$_SESSION['pastor_id'] = 1; 
$_GET['tab'] = 'desired_roles';
require 'pastor_dashboard.php';
?>
