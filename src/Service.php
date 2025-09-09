<?php

namespace App;

class Service
{
    private Database $db;
    private Telegram $tg;
    private Gemini $gemini;

    /**
     * @param Database $db
     * @param Telegram $tg
     * @param Gemini $gemini
     */
    public function __construct(Database $db, Telegram $tg, Gemini $gemini)
    {
        $this->db = $db;
        $this->tg = $tg;
        $this->gemini = $gemini;
    }

    /**
     * Единый запуск
     * @return void
     */
    public function run(): void
    {
        while (true) {
            # Вытягиваем последний update_id из БД
            $lastUpdateId = $this->db->getLastUpdateId();
            $offset = $lastUpdateId ? $lastUpdateId + 1 : 0;

            # Получаем новые обновления Telegram
            $updates = $this->tg->getUpdates($offset);

            if (!empty($updates['result'])) {
                $this->handleMessages($updates['result']);
            }

            sleep(1);
        }
    }

    /**
     * Обработчик сообщений
     * @param array $messages
     * @return void
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

                // Сохраняем в БД
                $this->db->recordData([
                    'chat_id' => $chatId,
                    'update_id' => $updateId,
                    'user_message' => $userMessage,
                    'bot_response' => $botResponse
                ]);
            }
        }
    }
}