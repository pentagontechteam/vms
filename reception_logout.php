<?php
session_start();
session_destroy();
header("Location: receptionist_login.php"); // Adjust to the path of your login page
exit();
