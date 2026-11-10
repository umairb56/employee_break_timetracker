<?php
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $_SESSION = [];
    session_destroy();
}
redirect('login.php');
