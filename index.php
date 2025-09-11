<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Telegram;
use App\Gemini;
use App\Service;
use App\Logger;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

$tokenTg = getenv('TOKEN_TELEGRAM');
$geminiApiKey = getenv('API_KEY_GEMINI');

$logger = new Logger(__DIR__ . '/logs/app.log');

new Service(
    new Database('my_db.db'),
    new Telegram($tokenTg),
    new Gemini($geminiApiKey),
    $logger
)->run();