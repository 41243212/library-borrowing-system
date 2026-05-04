<?php
require __DIR__ . '/config.php';

$_SESSION = [];
session_destroy();
session_start();
flash('success', '你已登出。');
redirect_to('/login.php');
