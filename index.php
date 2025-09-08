<?php

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

$db = new Database('my_db.db', $logger);

$tg = new Telegram($tokenTg, $logger);

$gemini = new Gemini($geminiApiKey, $logger);

$service = new Service($db, $tg, $gemini);

# Запуск бота
try {
    $service->run();
} catch (\Throwable $e) {
    $logger->error("Критическая ошибка сервиса: " . $e->getMessage());
    die("Сервис остановлен. Проверьте лог.");
}