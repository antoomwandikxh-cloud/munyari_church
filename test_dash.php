<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
session_start();
$_SESSION['member_id'] = 41; 
require 'member_dashboard.php';
?>
