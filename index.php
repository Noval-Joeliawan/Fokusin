<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirectTo('/dashboard.php');
} else {
    redirectTo('/login.php');
}
