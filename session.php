<?php
session_start();
echo "<pre>";
echo "Session contents:\n";
var_dump($_SESSION);
echo "</pre>";
echo "Phone: " . (isset($_SESSION['phone']) ? $_SESSION['phone'] : "NOT SET");
?>
