<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
redirect('/public/bookings.php');
