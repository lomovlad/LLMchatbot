<?php

declare(strict_types=1);

namespace App;

use App\Exception\DatabaseException;
use PDO;
use PDOStatement;
use PDOException;

class Database
{
    public PDO $pdo;

    /**
     * @throws DatabaseException
     */
    public function __construct(string $dbPath)
    {
        try {
            $this->connect($dbPath);
        } catch (PDOException $e) {
            throw new DatabaseException("Не удалось подключиться к базе данных: " . $e->getMessage());
        }
    }

    /**
     * Подключаемся к БД
     * @param string $dbPath
     * @return void
     * @throws DatabaseException
     */
    private function connect(string $dbPath): void
    {
        try {
            $this->pdo = new PDO("sqlite:" . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new DatabaseException("Не удалось подключиться к базе данных: " . $e->getMessage());
        }
    }

    /**
     * Метод получает результаты выборки  *SELECT
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     * @throws DatabaseException
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($params);

        if (!$success) {
            throw new DatabaseException("Ошибка выполнения запроса $sql");
        }

        return $stmt;
    }

    /**
     * Метод для модификаций в базе // UPDATE, INSERT, DELETE
     * @param string $sql
     * @param array $params
     * @return bool
     * @throws DatabaseException
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($params);

        if (!$success) {
            throw new DatabaseException("Ошибка выполнения запроса $sql");
        }

        return true;
    }

    /**
     * Запись собранных данных в БД
     * @param array{chat_id: int, update_id: int, user_message: string, bot_response: string} $data
     * @param string $tableName
     * @return bool
     * @throws DatabaseException
     */
    public function insertData(array $data, string $tableName): bool
    {
        $sql = "INSERT OR IGNORE INTO {$tableName} (chat_id, update_id, user_message, bot_response)
                VALUES (:chat_id, :update_id, :user_message, :bot_response)";

        return $this->execute($sql, $data);
    }
}