<?php

declare(strict_types=1);

namespace App;

use App\Exception\GeminiResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Gemini
{
    public string $apiKey;
    private Client $http;

    /**
     * @param string $apiKey
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->http = new Client([
            'base_uri' => "https://generativelanguage.googleapis.com/v1beta/",
            'timeout' => 2.0
        ]);
    }

    /**
     * Отправка запроса к Gemini API
     * @param string $data
     * @return string
     * @throws GeminiResponseException
     */
    public function sendRequest(string $data): string
    {
        try {
            $response = $this->http->post("models/gemini-2.0-flash:generateContent", [
                'query' => ['key' => $this->apiKey],
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $data
            ]);

            $body = (string)$response->getBody();

            if (!$body) {
                throw new GeminiResponseException("Пустой ответ от Gemini");
            }

            return $body;
        } catch (GuzzleException $e) {
            throw new GeminiResponseException("Ошибка запроса к Gemini: " . $e->getMessage());
        }
    }


    /**
     * Разбор ответа от Gemini API. перерабатывает JSON-строку в массив и извлекает конкретный текст
     * @param string $response
     * @return string
     * @throws GeminiResponseException
     */
    public function parseResponse(string $response): string
    {
        $decoded = json_decode($response, true);

        if ($decoded === null) {
            throw new GeminiResponseException('Ошибка разбора ответа Gemini: неверный JSON');
        }

        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Метод готовит промт, отправляет запрос и разбирает ответ
     * @param string $prompt
     * @return string
     * @throws GeminiResponseException
     */
    public function generateText(string $prompt): string
    {
        # Готовим данные для отправки
        $data = json_encode([
            "contents" => [
                [
                    "parts" => [
                        "text" => $prompt
                    ]
                ]
            ]
        ]);

        $response = $this->sendRequest($data);

        return $this->parseResponse($response);
    }
}