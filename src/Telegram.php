<?php

declare(strict_types=1);

namespace App;

use App\Exception\TelegramResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Telegram
{
    public string $tokenTg;
    private Client $http;

    /**
     * @param string $tokenTg
     */
    public function __construct(string $tokenTg)
    {
        $this->tokenTg = $tokenTg;
        $this->http = new Client([
            'base_uri' => "https://api.telegram.org/bot$this->tokenTg/",
            'timeout' => 10.0,
            'connect_timeout' => 5.0
        ]);
    }

    /**
     * Отправляем запрос API.
     * @param string $method
     * @param array $params
     * @return array
     * @throws TelegramResponseException
     */
    protected function sendRequest(string $method, array $params = []): array
    {
        try {
            $response = $this->http->get($method, [
                'query' => $params
            ]);
            $body = (string)$response->getBody();
            $data = json_decode($body, true);

            if (!isset($data['ok']) || !$data['ok']) {
                $description = $data['description'] ?? 'неизвестно';
                throw new TelegramResponseException(
                    "Ошибка Api Telegram: " . $description);
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new TelegramResponseException("Ошибка запроса к Telegram: " .  $e->getMessage());
        }
    }

    /**
     * Обработка сообщения по макс длине и отправка
     * @param int $chat_id
     * @param string $text
     * @return void
     * @throws TelegramResponseException
     */
    public function sendMessage(int $chat_id, string $text): void
    {
        $maxLength = 4000;

        # Если текст короткий, отправляем сразу
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            $this->sendRequest("sendMessage", [
                "chat_id" => $chat_id,
                "text" => $text
            ]);
            return;
        }

        # Разбиваем текст на части
        $splitMessages = mb_str_split($text, $maxLength, "UTF-8");
        # Отправляем по частям пользователю
        foreach ($splitMessages as $chunk) {
            $this->sendRequest("sendMessage", [
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
     * @throws TelegramResponseException
     */
    public function getUpdates(int $offset = 0): array
    {
        $response = $this->sendRequest("getUpdates", ["offset" => $offset]);

        return $response['result'] ?? [];
    }
}