<?php
declare(strict_types=1);

session_start();

const BASE_DIR = __DIR__;

require_once BASE_DIR . "/src/bootstrap.php";
$app = new App\App();
$app->run();
