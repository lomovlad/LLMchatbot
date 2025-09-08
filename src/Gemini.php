<?php

namespace App;

class Gemini
{
    public string $apiKey;
    private Logger $logger;

    /**
     * @param string $apiKey
     */
    public function __construct(string $apiKey, Logger $logger)
    {
        $this->logger = $logger;
        $this->apiKey = $apiKey;
    }

    /**
     * Отправка запроса к Gemini API
     * @param string $data
     * @return string
     */
    public function request(string $data): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$this->apiKey}";
        $this->logger->debug("Отправка запроса к Gemini: $url, данные: " . substr($data, 0, 200));

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ));

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logger->error("Ошибка запроса к Gemini: $error");
            throw new \RuntimeException("Ошибка запроса к Gemini: " . $error);
        }

        curl_close($ch);

        $this->logger->debug("Ответ от Gemini получен: " . $result);

        return $result;
    }

    /**
     * Разбор ответа от Gemini API. перерабатывает JSON-строку в массив и извлекает конкретный текст
     * @param string $response
     * @return string
     */
    public function parseResponse(string $response): string
    {
        $decoded = json_decode($response, true);

        if ($decoded === null) {
            $this->logger->error("Ошибка разбора ответа Gemini: неверный JSON");
            throw new \RuntimeException("Нет ответа от Gemini или неверный JSON");
        }

        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Метод готовит промт, отправляет запрос и разбирает ответ
     * @param string $prompt
     * @return string
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

        $response = $this->request($data);

        return $this->parseResponse($response);
    }
}