<?php
require_once 'config/app.php';
logoutUser();
header('Location: ' . BASE_URL . '/login');
exit;
