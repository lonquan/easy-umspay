<?php

declare(strict_types=1);

// Pest 会自动加载 vendor/autoload.php 和 tests/Pest.php
// 此文件保留用于额外的测试环境初始化

// 加载环境变量
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
