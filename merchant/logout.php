<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Core/Autoloader.php';
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
use App\Core\Bootstrap;
use App\Security\MerchantAuth;
Bootstrap::init();
MerchantAuth::logout();
header('Location: login.php');
exit;
