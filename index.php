<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('TOKEN_TELEGRAM', $_ENV['TOKEN_TELEGRAM']);
define('API_KEY_GEMINI', $_ENV['API_KEY_GEMINI']);

$pdo = new PDO('sqlite:my_db.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Функция отправки сообщения пользователю в тг
 * @param int $chat_id
 * @param string $text
 * @return void
 */
function sendMessageTg(int $chat_id, string $text): void {
    $url = "https://api.telegram.org/bot" . TOKEN_TELEGRAM . "/sendMessage";
    $maxLength = 4000;

    # Проверка на отправку в одно сообщение
    if (mb_strlen($text, 'UTF-8') <= $maxLength) {
        file_get_contents("$url?chat_id=$chat_id&text=" . urlencode($text));
        return;
    }

    # Разбиваем текст на части
    $splitMessage = mb_str_split($text, $maxLength, "UTF-8");
    # Отправляем по частям пользователю
    foreach ($splitMessage as $chunk) {
        file_get_contents("$url?chat_id=$chat_id&text=" . urlencode($chunk));
        sleep(1);
    }
}

/**
 * Функция пересылки сообщения пользователя к gemini
 * @param string $prompt
 * @return string
 */
function getGeminiResponse(string $prompt): string {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . API_KEY_GEMINI;
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

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ));
    $result = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($result, true);

    # Получаем из ответа текст
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return $response['candidates'][0]['content']['parts'][0]['text'];
    }

    return "Не удалось получить ответ от Gemini. :(";
}

# Бесконечный цикл работы бота
while (true) {
    try {
        # Вытягиваем последний update_id из БД
        $lastUpdateId = $pdo->query("SELECT MAX(update_id) FROM messages")->fetchColumn();
        $offset = $lastUpdateId ? $lastUpdateId + 1 : 0;

        # Запрос обновлений Tg только по последнему update_id
        $urlUpdate = "https://api.telegram.org/bot" . TOKEN_TELEGRAM ."/getUpdates?offset=$offset&timeout=20";
        $updates = json_decode(file_get_contents($urlUpdate), true);

        # Если есть новые сообщения -> обрабатываем их
        if (!empty($updates['result'])) {
            foreach ($updates['result'] as $update) {
                # Проверка на сообщение
                if (isset($update['message'])) {
                    # Вытягиваем данные
                    $chat_id = $update['message']['chat']['id'];
                    $userMessage = $update['message']['text'];
                    $updateId = $update['update_id'];
                    $botResponse = '';

                    # Обработка стартовой команды бота
                    if ($userMessage === '/start') {
                        $botResponse = "Привет! Напиши свой вопрос :)";
                    } else {
                        # Отправляем сообщение Gemini и получаем ответ текстом
                        $botResponse = getGeminiResponse($userMessage);
                    }

                    if ($botResponse) {
                        # Отправляем ответ пользователю в тг
                        sendMessageTg($chat_id, $botResponse);

                        # Пишем в БД данные
                        $stmt = $pdo->prepare("
                                INSERT OR IGNORE INTO messages (chat_id, update_id, user_message, bot_response)
                                VALUES (:chat_id, :update_id, :user_message, :bot_response)
                            ");

                        $stmt->execute([
                            ':chat_id' => $chat_id,
                            ':update_id' => $updateId,
                            ':user_message' => $userMessage,
                            ':bot_response' => $botResponse,
                        ]);

                    }
                }
            }
        }
    } catch (Exception $ex) {
        echo $ex->getMessage() . PHP_EOL;
    }
}

