<?php

use JetBrains\PhpStorm\NoReturn;
/**
 * функция отладки (Debug)
 *
 * @param $data
 */
function dump($data)
{
    echo '<pre>'; var_dump($data); echo '</pre>';
}

/**
 * подключение к бд
 *
 * @return PDO
 */
function connectDB(): \PDO
{
    static $dbh = null;

    if (!is_null($dbh)) {
        return $dbh;
    }

    try {
        //подключаем конфиг бд и считываем массив в переменную
        $config = require  __DIR__ . '/../config/db.php';
        $dbh = new \PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            $config['options']
        );

        return $dbh;
    } catch (\PDOException $e) {
        throw new \PDOException("Internal Server Error: {$e->getMessage()}", 500);
    }
}

/**
 * Устанавливаем токен для защиты
 * от межсайтовой подделки запроса
 */
function createCSRF(): void
{
    if (isset($_SESSION['_csrf']) !== true) {
        $hash = uniqid('');
        $_SESSION['_csrf'] = hash('sha512', time() . '' . $hash);
    }
}

/**
 * Проверяем подлинность токена csrf
 * если токен не совпадает, отправляем на 404 стр.
 */
function checkCSRF(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        if (!isset($_REQUEST['csrf_token'])) {
            $code = 404;
            //устанавливаем код ответа HTTP и подключаем шаблон ошибки по код
            dispatchNotFound($code);
        } elseif ($_REQUEST['csrf_token'] !== $_SESSION['_csrf']) {
            $code = 404;
            //устанавливаем код ответа HTTP и подключаем шаблон ошибки по код
            dispatchNotFound($code);
        }
    }
}

/**
 * Уничтожаем токен csrf
 */
function destroyCSRF(): void
{
    //проверяем установлен ли токен
    if (!isset($_SESSION['_csrf'])) {
        //меняем значение токена
        $_SESSION['_csrf'] = 'destroy';
        //удаляем сессию токена
        unset($_SESSION['_csrf']);
    }
}

/**
 * Подключение шаблона вида страницы
 * если шаблона нет, ошибка 404
 *
 * @param string $viewPath
 * @param array $data
 * @return string
 */
function render(string $viewPath, array $data = []): string
{
    //импортирует переменные из массива в текущую таблицу символов
    extract($data);
    //устанавливаем полный путь к виду страницы, для подключения
    $viewPath = __DIR__ . "/../views/{$viewPath}_tpl.php";
    //проверяем существование указанного файла или каталога
    //если нет подключаем 404 станицу
    if (!file_exists($viewPath)) {
        $code = 404;
        //устанавливаем код ответа HTTP
        dispatchNotFound($code);
    }
    //включаем буферизацию вывода
    ob_start();
    //подключаем шаблон вида
    include $viewPath;
    //получаем содержимое текущего буфера и удаляем его
    //то-есть возвращаем шаблон в виде строки, с уже вставленными переменными,
    //если они есть в шаблоне
    return ob_get_clean();
}

function redirect(string $http = ''): void
{
    if ($http) {
        $redirect = $http;
    } else {
        $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    }

    header("Location: {$redirect}");
    die;
}

//пагинация
function paginator($page, $countPages)
{
    $back = null;
    $forward = null;
    $startPage = null;
    $endPage = null;
    $page2Left = null;
    $page1Left = null;
    $page2Right = null;
    $page1Right = null;
    $path = null;
    $uri = '?';

    $url = trim($_SERVER['REQUEST_URI'], '/');
    $url = explode('?', $url);
    $path = $url[0];
    /*
     * Если хоть что есть на втором месте массива и если param не страница,
     * то закидывать в uri
     */
    if (!empty($url[1])) {
        $params = explode('&', $url[1]);
        foreach ($params as $param) {
            if (!preg_match("#page=#", $param)) {
                $uri .= "{$param}&amp;";
            }
        }
    }
    //Логика разбиения на страницы
    if ($page > 1) {
        $back = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page=" . ($page - 1) . "\">&lt;</a></li>";
    }
    if ($page < $countPages) {
        $forward = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page=" . ($page + 1) . "\">&gt;</a></li>";
    }
    if ($page > 3) {
        $startPage = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page=1\">&laquo;</a></li>";
    }
    if ($page < ($countPages - 2)) {
        $endPage = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page={$countPages}\">&raquo;</a></li>";
    }
    if ($page - 2 > 0) {
        $page2Left = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page=" . ($page - 2) . "\">" . ($page - 2) . "</a></li>";
    }
    if ($page - 1 > 0) {
        $page1Left = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page=" . ($page - 1) . "\">" . ($page - 1) . "</a></li>";
    }
    if ($page + 1 <= $countPages) {
        $page1Right = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page=" . ($page + 1) . "\">" . ($page + 1) . "</a></li>";
    }
    if ($page + 2 <= $countPages) {
        $page2Right = "<li class=\"page-item\"><a class=\"page-link\" href=\"/{$path}{$uri}page=" . ($page + 2) . "\">" . ($page + 2) . "</a></li>";
    }
    //Логика вывода на страницы
    return $startPage . $back . $page2Left . $page2Right
        . "  <li class=\"page-item active\" aria-current=\"page\">
              <span class=\"page-link\">{$page}</span>
            </li>"
        . $page1Right . $page2Right . $forward . $endPage;

}
#[NoReturn]
function dispatchNotFound(int $code): void
{
    //устанавливаем код ответа HTTP
    http_response_code((int)$code);
    require __DIR__ . "/../Views/errors/{$code}.php";
    die;
}
