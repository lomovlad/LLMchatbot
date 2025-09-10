<?php

declare(strict_types=1);

namespace App;

use App\Exception\GeminiResponseException;
use App\Exception\TelegramResponseException;
use App\Exception\DatabaseException;

class Service
{
    /**
     * @param Database $db
     * @param Telegram $tg
     * @param Gemini $gemini
     * @param Logger|null $logger
     */
    public function __construct(
        private Database $db,
        private Telegram $tg,
        private Gemini   $gemini,
        private ?Logger  $logger = null
    )
    {

    }

    /**
     * Единый запуск
     * @return void
     */
    public function run(): void
    {
        while (true) {
            try {
                $lastUpdateId = $this->getLastUpdateId('messages');
                $offset = $lastUpdateId ? $lastUpdateId + 1 : 0;
                // получаем апдейты (Telegram::getUpdates возвращает массив апдейтов)
                $updates = $this->tg->getUpdates($offset);

                if (!empty($updates)) {
                    $this->handleMessages($updates);
                }

                sleep(1);
            } catch (DatabaseException $e) {
                $this->logger?->error("Ошибка в БД при получении update_id в run(): " . $e->getMessage());
                sleep(5);
            } catch (TelegramResponseException $e) {
                $this->logger?->error("Ошибка Telegram API в run(): " . $e->getMessage());
                sleep(10);
            } catch (\Throwable $e) {
                $this->logger?->error("Неизвестная ошибка в run(): " . $e->getMessage());
                sleep(5);
            }
        }
    }

    /**
     * Обработчик сообщений
     * @param array $messages
     * @return void
     * @throws TelegramResponseException
     */
    private function handleMessages(array $messages): void
    {
        foreach ($messages as $update) {
            if (!isset($update['message'])) {
                continue;
            }

            $chatId = $update['message']['chat']['id'] ?? null;
            $userMessage = $update['message']['text'] ?? '';
            $updateId = $update['update_id'] ?? 0;
            $botResponse = '';

            try {
                // Обработка стартовой команды
                if ($userMessage === '/start') {
                    $botResponse = "Привет! Напиши свой вопрос :)";
                } elseif ($userMessage !== '') {
                    // Отправляем в Gemini и получаем ответ
                    $botResponse = $this->gemini->generateText($userMessage);
                }

                if ($botResponse && $chatId) {
                    // Отправка ответа пользователю
                    $this->tg->sendMessage($chatId, $botResponse);
                }
            } catch (GeminiResponseException $e) {
                $this->logger?->error("Gemini ошибка: " . $e->getMessage());
                if ($chatId) {
                    $this->tg->sendMessage($chatId, "❌ Gemini временно недоступен, попробуйте позже");
                }
            } catch (TelegramResponseException $e) {
                $this->logger?->error("Ошибка Telegram: " . $e->getMessage());
            } finally {
                try {
                    # Всегда пишем в БД
                    $this->db->insertData([
                        'chat_id' => $chatId,
                        'update_id' => $updateId,
                        'user_message' => $userMessage,
                        'bot_response' => $botResponse
                    ], 'messages');
                } catch (DatabaseException $e) {
                    $this->logger?->error("Ошибка БД при сохранении update: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Возвращаем максимальный update_id из таблицы БД
     * @param string $tableName
     * @return int|null
     * @throws DatabaseException
     */
    private function getLastUpdateId(string $tableName): ?int
    {
        $sql = "SELECT MAX(update_id) FROM {$tableName}";
        $maxMessenger = $this->db->query($sql)->fetchColumn();

        return $maxMessenger !== false ? (int)$maxMessenger : null;
    }
}