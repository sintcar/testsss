<?php
require_once __DIR__ . '/../app/auth.php';
logout();
redirect('/public/login.php');
