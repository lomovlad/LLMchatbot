<?php

namespace App;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    public PDO $pdo;

    /**
     * @param string $dbPath
     */
    public function __construct(private string $dbPath)
    {
        $this->connect();
    }

    /**
     * @return void
     */
    private function connect(): void
    {
        try {
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new \RuntimeException("Не удалось подключиться к базе данных: " . $e->getMessage());
        }
    }

    /**
     * Универсальный метод запроса к базе
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement|bool
    {
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);

        if (stripos(trim($sql), 'SELECT') === 0) {
            return $stmt;
        }

        return $result;
    }

    /**
     * Метод для коротких запросов выборки с получением ассоц. массива
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
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