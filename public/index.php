<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = currentUser();
if (!$u) redirect('login.php');
redirect($u['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
