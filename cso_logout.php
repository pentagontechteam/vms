<?php
session_start();
session_destroy();
header("Location: cso_login.php");
