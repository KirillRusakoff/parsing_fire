<?php

header('Content-type: text/html; charset=utf-8');
setlocale(LC_ALL, 'ru_RU.UTF-8');

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php-error.log');

set_time_limit(0);
ini_set('memory_limit', -1);

require_once __DIR__ . "/vendor/autoload.php";


use DiDom\Document;
use Krugozor\Database\Mysql;

$db = Mysql::create("localhost", "parser_user", "parser_pass")
      // Язык вывода ошибок - русский
      ->setErrorMessagesLang('ru')
      // Выбор базы данных
      ->setDatabaseName("data_db")
      // Выбор кодировки
      ->setCharset("utf8")
      // Включим хранение всех SQL-запросов для отчета/отладки/статистики
      ->setStoreQueries(true);

$urlCatalogs = [
    "https://nkamin.ru/catalog/pechi-dlya-doma/greo-g",
    "https://nkamin.ru/catalog/pechi-dlya-doma/everest?page=1&category_id=5477&show_by=24&sort_from=popularity&sort_by=desc&in_stock=1",
    "https://nkamin.ru/catalog/pechi-dlya-doma/vezuvij",
    "https://nkamin.ru/catalog/pechi-dlya-doma/guca?page=1&category_id=4792&show_by=24&filter=%7B%22price%22:%7B%22from%22:42860,%22to%22:187560%7D,%22brand%22:[],%22pech-material%22:[],%22pech-tip%22:[],%22pech-varochnaya%22:[],%22pech-objom%22:[],%22pech-dverka%22:[],%22pech-raspolozh%22:[],%22pech-podkluchenie%22:[],%22pech-dlit-gorenie%22:[]%7D&sort_from=popularity&sort_by=desc&in_stock=1",
    "https://nkamin.ru/catalog/pechi-dlya-doma/rubtsovsk",
    "https://nkamin.ru/catalog/pechi-dlya-doma/mbs",
    "https://nkamin.ru/catalog/pechi-dlya-doma/aston",
    "https://nkamin.ru/catalog/pechi-dlya-doma/greo",
    "https://nkamin.ru/catalog/pechi-dlya-doma/ekokamin-bavariya",
    "https://nkamin.ru/catalog/pechi-dlya-doma/etna",
    "https://nkamin.ru/catalog/pechi-dlya-doma/teplodar",
    "https://nkamin.ru/catalog/pechi-dlya-doma/varvara"
];

$urlCatalogs2 = [
    "https://nkamin.ru/catalog/kaminy/kaminnye-topki/aston",
    "https://nkamin.ru/catalog/kaminy/kaminnye-topki/everest", 
    "https://nkamin.ru/catalog/kaminy/kaminnye-topki/kratki",
    "https://nkamin.ru/catalog/kaminy/kaminnye-topki/ekokamin"
];

$urlCatalogs3 = [
    "https://nkamin.ru/catalog/pechi-dlya-bani/vezuvij",
    "https://nkamin.ru/catalog/pechi-dlya-bani/aston",
    "https://nkamin.ru/catalog/pechi-dlya-bani/etna",
    "https://nkamin.ru/catalog/pechi-dlya-bani/everest",
    "https://nkamin.ru/catalog/pechi-dlya-bani/stal-master",
    "https://nkamin.ru/catalog/pechi-dlya-bani/varvara",
    "https://nkamin.ru/catalog/pechi-dlya-bani/teplodar",
    "https://nkamin.ru/catalog/pechi-dlya-bani/grill-d"
];

$urlCatalogs4 = [
    "https://nkamin.ru/catalog/kotly/vezuviy",
    "https://nkamin.ru/catalog/kotly/kupper-teplodar",
    "https://nkamin.ru/catalog/kotly/zota"
];

$urlCatalogs5 = [
    "https://nkamin.ru/catalog/bbq/mangaly-mangalnye-vstavki",
    "https://nkamin.ru/catalog/bbq/tandyry",
    "https://nkamin.ru/catalog/bbq/barbekyu",
    "https://nkamin.ru/catalog/aksessuary/kaminnye-nabory",
    "https://nkamin.ru/catalog/aksessuary/drovnicy-dlya-kaminov",
    "https://nkamin.ru/catalog/aksessuary/podstavki-pod-topki-pechi",
    "https://nkamin.ru/catalog/aksessuary/zashitnye-ekrany-dlya-kamina"
];

echo "Start parsing ...";

// Функция для парсинга и сохранения данных
function parseCatalog($urlArray, $db, $tableName) {

    // Очищаем таблицу перед вставкой новых данных
    $db->query("TRUNCATE TABLE `$tableName`");

    echo "Old data has been deleted from the database ... ";

    $arrMainParams = [];

    foreach ($urlArray as $url) {
        $client = new \GuzzleHttp\Client();
        $resp = $client->get($url);
        $html = $resp->getBody()->getContents();

        $document = new Document();
        $document->loadHtml($html);

        $catalog = $document->find('.item');

        foreach ($catalog as $item) {
            $link = "https://nkamin.ru" . ($item->find(".img_box .for_img a")[0]->attr("href"));
            $title = str_replace(array("\r", "\n", "   "), '', trim($item->find(".name a")[0]->text()));
            $status = str_replace(".", "", trim($item->find(".item_data .item_shop span")[0]->text()));
            $image = "https://nkamin.ru" . $item->find(".img_box .for_img a img")[0]->attr("src");
            $price = (int) preg_replace('/[^\d]/', '', $item->find(".item_data .item_price .item_price_box .price")[0]->text());


            // Получаем подробную информацию о товаре
            $resp = $client->get($link);
            $html = $resp->getBody()->getContents();
            $document = new Document();
            $document->loadHtml($html);

            $description = '';
            for ($i = 1; $i <= 7; $i++) {
                if (isset($document->find('.card_tab .card_tab_text p')[$i])) {
                    $description .= $document->find('.card_tab .card_tab_text p')[$i]->text() . " ";
                }
            }

            $feature = '';
            if (isset($document->find('.row .tables')[0])) {
                $feature = str_replace("\n", "", trim($document->find('.row .tables')[0]->html()));
            }

            // Добавляем данные в массив
            $arrMainParams[] = [
                "title" => $title,
                "status" => $status,
                "image" => $image,
                "price" => $price,
                "description" => $description,
                "features" => $feature
            ];

            echo "Intro stage is passed ... ";

            sleep(rand(1, 3));
        }

        echo "Stage is passed ... ";

        sleep(rand(1, 3));
    }

    // Вставка данных в таблицу
    foreach ($arrMainParams as $mainParams) {
        $db->query("INSERT INTO `$tableName` SET ?As", $mainParams);
    }

    echo "Data has been added to the database ... ";

    sleep(rand(2, 4));
}

// Парсим данные и сохраняем их в разные таблицы
parseCatalog($urlCatalogs, $db, 'data');
parseCatalog($urlCatalogs2, $db, 'data2');
parseCatalog($urlCatalogs3, $db, 'data3');
parseCatalog($urlCatalogs4, $db, 'data4');
parseCatalog($urlCatalogs5, $db, 'data5');

echo "All data has been parsed and written to the respective tables!";