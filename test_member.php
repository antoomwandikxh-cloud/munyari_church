<?php
session_start();
$_SESSION['member_id'] = 1; 
$_GET['tab'] = 'manage_church_village';
require 'member_dashboard.php';
?>
