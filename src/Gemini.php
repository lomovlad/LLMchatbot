<?php

declare(strict_types=1);

namespace App;

use App\Exception\GeminiResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

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
            'timeout' => 10.0,
            'connect_timeout' => 5.0
        ]);
    }

    /**
     * Отправка запроса к Gemini API
     * @param string $data
     * @return ResponseInterface
     * @throws GeminiResponseException
     */
    public function sendRequest(string $data): ResponseInterface
    {
        try {
            $response = $this->http->post("models/gemini-2.0-flash:generateContent", [
                'query' => ['key' => $this->apiKey],
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $data
            ]);

            if ($response->getBody()->getSize() === 0) {
                throw new GeminiResponseException("Пустой ответ от Gemini");
            }

            return $response;
        } catch (GuzzleException $e) {
            throw new GeminiResponseException("Ошибка запроса к Gemini: " . $e->getMessage());
        }
    }

    /**
     * Разбор ответа от Gemini API. перерабатывает JSON-строку в массив и извлекает конкретный текст
     * @param ResponseInterface $response
     * @return string
     * @throws GeminiResponseException
     */
    public function parseResponse(ResponseInterface $response): string
    {
        $body = $response->getBody()->getContents();

        $decoded = json_decode($body, true);

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