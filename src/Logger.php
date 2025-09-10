<?php

declare(strict_types=1);

namespace App;

use DateTime;
use DateTimeZone;

class Logger
{
    /**
     * @param string $pathToLogFile
     */
    public function __construct(
        public string $pathToLogFile
    )
    {
    }

    /**
     * Метод записи лога в файл
     * @param string $message
     * @param string $level
     * @return void
     */
    public function log(string $message, string $level = 'info'): void
    {
        $dt = new DateTime('now');
        $dt->setTimezone(new DateTimeZone('Europe/Moscow'));
        $time = $dt->format('Y-m-d H:i:s');
        $line = "[{$time}] [{$level}] {$message}\n";
        file_put_contents($this->pathToLogFile, $line, FILE_APPEND);
    }

    /**
     * Метод для ошибки
     * @param string $message
     * @return void
     */
    public function error(string $message): void
    {
        $this->log($message, 'error');
    }

    /**
     * Метод для отладки
     * @param string $message
     * @return void
     */
    public function debug(string $message): void
    {
        $this->log($message, 'debug');
    }
}