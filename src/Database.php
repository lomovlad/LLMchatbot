<?php

namespace App;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    public PDO $pdo;
    private Logger $logger;

    /**
     * @param string $dbPath
     * @param Logger $logger
     */
    public function __construct(string $dbPath, Logger $logger)
    {
        $this->logger = $logger;
        $this->connect($dbPath);
    }

    /**
     * Подключаемся к БД
     * @param string $dbPath
     * @return void
     */
    private function connect(string $dbPath): void
    {
        try {
            $this->pdo = new PDO("sqlite:" . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->logger->log("Подключение к БД успешно");
        } catch (PDOException $e) {
            $this->logger->error("Ошибка подключения к БД: " . $e->getMessage());
            throw new \RuntimeException("Не удалось подключиться к базе данных: " . $e->getMessage());
        }
    }

    /**
     * Универсальный метод запроса к базе
     * @param string $sql
     * @param array $params
     * @return PDOStatement|bool
     */
    public function query(string $sql, array $params = []): PDOStatement|bool
    {
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);
        $this->logger->debug("Выполнен SQL запрос: $sql, параметры: " . json_encode($params));

        if (stripos(trim($sql), 'SELECT') === 0) {
            return $stmt;
        }

        return $result;
    }

    /**
     * Возвращаем максимальный update_id из messages
     * @return int|null
     */
    public function getLastUpdateId(): ?int
    {
        $sql = "SELECT MAX(update_id) FROM messages";
        $max = $this->query($sql)->fetchColumn();

        return $max !== false ? (int)$max : null;
    }

    /**
     * Запись собранных данных в БД
     * @param array $data ['chat_id' => int, 'update_id' => int, 'user_message' => string, 'bot_response' => string]
     * @return bool
     */
    public function recordData(array $data): bool
    {
        $sql = "INSERT OR IGNORE INTO messages (chat_id, update_id, user_message, bot_response)
                VALUES (:chat_id, :update_id, :user_message, :bot_response)";

        return $this->query($sql, $data);
    }
}