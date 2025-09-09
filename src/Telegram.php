<?php

namespace App;

class Telegram
{
    public string $tokenTg;
    private Logger $logger;

    /**
     * @param string $tokenTg
     * @param Logger $logger
     */
    public function __construct(string $tokenTg, Logger $logger)
    {
        $this->logger = $logger;
        $this->tokenTg = $tokenTg;
    }

    /**
     * Универсальный метод для работы с API.
     * @param string $method
     * @param array $params
     * @return string
     */
    protected function request(string $method, array $params = []): string
    {
        $url = "https://api.telegram.org/bot{$this->tokenTg}/$method?" . http_build_query($params);
        $this->logger->debug("Отправка запроса: {$url}");
        $response =  @file_get_contents($url);

        if ($response === false) {
            $error = error_get_last()['message'] ?? 'Неизвестная ошибка';
            $this->logger->error("Ошибка запроса к Telegram API: " . $error);
            throw new \RuntimeException("Ошибка запроса к Telegram API: " . $error);
        }

        $this->logger->debug("Получен ответ: " . substr($response, 0, 200) . (strlen($response) > 200 ? '...' : ''));

        return $response;
    }

    /**
     * Обработка сообщения по макс длине и отправка
     * @param int $chat_id
     * @param string $text
     * @return void
     */
    public function sendMessage(int $chat_id, string $text): void
    {
        $maxLength = 4000;

        # Если текст короткий, отправляем сразу
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            $this->request("sendMessage", [
                "chat_id" => $chat_id,
                "text" => $text
            ]);
            return;
        }

        # Разбиваем текст на части
        $splitMessages = mb_str_split($text, $maxLength, "UTF-8");
        # Отправляем по частям пользователю
        foreach ($splitMessages as $chunk) {
            $this->request("sendMessage", [
                "chat_id" => $chat_id,
                "text" => $chunk
            ]);

            sleep(1);
        }
    }

    /**
     * Получение массива обновлений
     * @param int $offset
     * @return array
     */
    public function getUpdates(int $offset = 0): array
    {
        $response = $this->request("getUpdates", ["offset" => $offset]);

        return json_decode($response, true);
    }
}