<?php
header('Content-Type: text/html; charset=utf-8');

class TooManyRequests extends Exception {}

class Dadata
{
    private $clean_url = "https://cleaner.dadata.ru/api/v1/clean";
    private $suggest_url = "https://suggestions.dadata.ru/suggestions/api/4_1/rs";
    private $token;
    private $secret;
    private $handle;

    public function __construct($token, $secret)
    {
        $this->token = $token;
        $this->secret = $secret;
    }

    public function init()
    {
        $this->handle = curl_init();
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->handle, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: Token " . $this->token,
            "X-Secret: " . $this->secret
        ));
        curl_setopt($this->handle, CURLOPT_POST, 1);
    }

    public function clean($type, $value)
    {
        $url = $this->clean_url . "/$type";
        $fields = array($value);
        return $this->executeRequest($url, $fields);
    }

    private function executeRequest($url, $fields)
    {
        curl_setopt($this->handle, CURLOPT_URL, $url);
        if ($fields != null) {
            curl_setopt($this->handle, CURLOPT_POST, 1);
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, json_encode($fields));
        } else {
            curl_setopt($this->handle, CURLOPT_POST, 0);
        }
        $result = $this->exec();
        $result = json_decode($result, true);
        return $result;
    }

    private function exec()
    {
        $result = curl_exec($this->handle);
        $info = curl_getinfo($this->handle);
        if ($info['http_code'] == 429) {
            throw new TooManyRequests();
        } elseif ($info['http_code'] != 200) {
            throw new Exception('Request failed with http code ' . $info['http_code'] . ': ' . $result);
        }
        return $result;
    }

    public function close()
    {
        curl_close($this->handle);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userLastName = $_POST['user_last_name'] ?? '';
    $userName = $_POST['user_name'] ?? '';
    $userSecondName = $_POST['user_second_name'] ?? '';
    $apiKey = $_POST['api_key'] ?? '';
    $secretKey = $_POST['secret_key'] ?? '';
} else {
    $userLastName = $_GET['user_last_name'] ?? '';
    $userName = $_GET['user_name'] ?? '';
    $userSecondName = $_GET['user_second_name'] ?? '';
    $apiKey = $_GET['api_key'] ?? '';
    $secretKey = $_GET['secret_key'] ?? '';
}

if (empty($userLastName) || empty($userName) || empty($userSecondName) || empty($apiKey) || empty($secretKey)) {
    echo '<p style="color: red;">Ошибка: все поля обязательны для заполнения!</p>';
    exit;
}

try {
    $dadata = new Dadata($apiKey, $secretKey);
    $dadata->init();

    $fullName = $userLastName . ' ' . $userName . ' ' . $userSecondName;
    $result = $dadata->clean("name", $fullName);

    if (!empty($result) && isset($result[0])) {
        $person = $result[0];
        echo '<h2>Стандартизированные данные:</h2>';
        echo '<p><strong>Фамилия:</strong> ' . htmlspecialchars($person['surname'] ?? 'Не указана') . '</p>';
        echo '<p><strong>Имя:</strong> ' . htmlspecialchars($person['name'] ?? 'Не указано') . '</p>';
        echo '<p><strong>Отчество:</strong> ' . htmlspecialchars($person['patronymic'] ?? 'Не указано') . '</p>';
        echo '<p><strong>Пол:</strong> ' . htmlspecialchars($person['gender'] ?? 'Не определён') . '</p>';
        echo '<p><strong>Качество очистки:</strong> ' . htmlspecialchars($person['qc'] ?? 'Нет данных') . '</p>';
    } else {
        echo '<p>Не удалось стандартизировать данные.</p>';
    }

    $dadata->close();
} catch (TooManyRequests $e) {
    echo '<p style="color: orange;">Ошибка: превышен лимит запросов к API.</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка API: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
