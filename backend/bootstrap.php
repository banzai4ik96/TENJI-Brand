<?php
declare(strict_types=1);

const TENJI_STORAGE_DIR = __DIR__ . '/../storage';
const TENJI_DB_PATH = TENJI_STORAGE_DIR . '/site.sqlite';
const TENJI_UPLOAD_DIR = __DIR__ . '/../uploads/products';

function tenji_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(TENJI_STORAGE_DIR)) {
        mkdir(TENJI_STORAGE_DIR, 0775, true);
    }
    if (!is_dir(TENJI_UPLOAD_DIR)) {
        mkdir(TENJI_UPLOAD_DIR, 0775, true);
    }

    $pdo = new PDO('sqlite:' . TENJI_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    tenji_init_schema($pdo);
    tenji_seed_defaults($pdo);

    return $pdo;
}

function tenji_init_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cms_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            price INTEGER NOT NULL DEFAULT 0,
            collection_name TEXT NOT NULL DEFAULT "Other",
            description TEXT NOT NULL DEFAULT "",
            sizes_csv TEXT NOT NULL DEFAULT "S,M,L,XL,XXL",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            image_path TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
        )'
    );
}

function tenji_cms_schema(): array
{
    return [
        'brand_name' => ['label' => 'Название бренда', 'default' => 'TENJI BRAND'],
        'nav_catalog' => ['label' => 'Меню: Каталог', 'default' => 'Каталог'],
        'nav_new' => ['label' => 'Меню: Новинки', 'default' => 'Новинки'],
        'nav_about' => ['label' => 'Меню: О нас', 'default' => 'О нас'],
        'nav_contacts' => ['label' => 'Меню: Контакты', 'default' => 'Контакты'],
        'nav_cta' => ['label' => 'Кнопка в шапке', 'default' => 'Оформить предзаказ'],
        'hero_badge' => ['label' => 'Hero: бейдж', 'default' => 'Аниме × Рок капсула 2026'],
        'hero_title' => ['label' => 'Hero: заголовок', 'default' => 'Противостояние культур: неон Токио против гитарного шторма'],
        'hero_lead' => ['label' => 'Hero: текст', 'default' => 'TENJI BRAND создает одежду на стыке аниме-эстетики и рок-сцены. Мы смешиваем графику, фактуру и настроение, чтобы каждый образ звучал как мощный трек и выглядел как кадр из любимого тайтла.'],
        'hero_btn_primary' => ['label' => 'Hero: кнопка 1', 'default' => 'Смотреть коллекции'],
        'hero_btn_secondary' => ['label' => 'Hero: кнопка 2', 'default' => 'Собрать образ'],
        'metric_1_value' => ['label' => 'Метрика 1: значение', 'default' => '140+'],
        'metric_1_label' => ['label' => 'Метрика 1: подпись', 'default' => 'авторских принтов'],
        'metric_2_value' => ['label' => 'Метрика 2: значение', 'default' => '48 часов'],
        'metric_2_label' => ['label' => 'Метрика 2: подпись', 'default' => 'на сборку заказа'],
        'metric_3_value' => ['label' => 'Метрика 3: значение', 'default' => 'Limited'],
        'metric_3_label' => ['label' => 'Метрика 3: подпись', 'default' => 'дропы без повтора'],
        'duel_anime_label' => ['label' => 'Блок Anime: лейбл', 'default' => 'Аниме'],
        'duel_anime_title' => ['label' => 'Блок Anime: заголовок', 'default' => 'Гладкий неон'],
        'duel_anime_text' => ['label' => 'Блок Anime: текст', 'default' => 'Линии манги, световые градиенты и атмосфера ночного города.'],
        'duel_rock_label' => ['label' => 'Блок Rock: лейбл', 'default' => 'Рок'],
        'duel_rock_title' => ['label' => 'Блок Rock: заголовок', 'default' => 'Грубый драйв'],
        'duel_rock_text' => ['label' => 'Блок Rock: текст', 'default' => 'Гранж-текстуры, рваные акценты и энергия живого выступления.'],
        'catalog_title' => ['label' => 'Каталог: заголовок', 'default' => 'Каталог TENJI BRAND'],
        'catalog_text' => ['label' => 'Каталог: описание', 'default' => 'Выбирайте коллекцию, добавляйте товар и оформляйте заказ в WhatsApp.'],
        'lookbook_title' => ['label' => 'Lookbook: заголовок', 'default' => 'Lookbook TENJI'],
        'lookbook_text' => ['label' => 'Lookbook: описание', 'default' => 'Свежие образы из вашего каталога.'],
        'drops_title' => ['label' => 'Дропы: заголовок', 'default' => 'Дроп недели'],
        'drops_text' => ['label' => 'Дропы: описание', 'default' => 'Две культуры — два настроения. Выбери свою сторону.'],
        'drops_anime_label' => ['label' => 'Дроп Anime: лейбл', 'default' => 'Аниме линия'],
        'drops_anime_title' => ['label' => 'Дроп Anime: заголовок', 'default' => 'Capsule «Moon Pulse»'],
        'drops_anime_text' => ['label' => 'Дроп Anime: текст', 'default' => 'Светлый неон, чистая графика и мягкие оттенки японской ночи.'],
        'drops_anime_btn' => ['label' => 'Дроп Anime: кнопка', 'default' => 'Смотреть'],
        'drops_rock_label' => ['label' => 'Дроп Rock: лейбл', 'default' => 'Рок линия'],
        'drops_rock_title' => ['label' => 'Дроп Rock: заголовок', 'default' => 'Capsule «Black Voltage»'],
        'drops_rock_text' => ['label' => 'Дроп Rock: текст', 'default' => 'Грязные текстуры, жирная типографика и эффект сценической пыли.'],
        'drops_rock_btn' => ['label' => 'Дроп Rock: кнопка', 'default' => 'Смотреть'],
        'about_title' => ['label' => 'О нас: заголовок', 'default' => 'Почему выбирают TENJI BRAND'],
        'feature_1_title' => ['label' => 'Преимущество 1: заголовок', 'default' => 'Сильная идея'],
        'feature_1_text' => ['label' => 'Преимущество 1: текст', 'default' => 'Каждый дроп строится вокруг сюжета — от аниме-арки до концертного тура.'],
        'feature_2_title' => ['label' => 'Преимущество 2: заголовок', 'default' => 'Качество сцены'],
        'feature_2_text' => ['label' => 'Преимущество 2: текст', 'default' => 'Плотные ткани, стойкие краски и комфортный силуэт под ежедневный ритм.'],
        'feature_3_title' => ['label' => 'Преимущество 3: заголовок', 'default' => 'Доставка по Казахстану'],
        'feature_3_text' => ['label' => 'Преимущество 3: текст', 'default' => 'Быстро и прозрачно: трекинг, понятные сроки и поддержка в мессенджерах.'],
        'footer_about_title' => ['label' => 'Футер: заголовок бренда', 'default' => 'TENJI BRAND'],
        'footer_about_text' => ['label' => 'Футер: описание бренда', 'default' => 'Аниме и рок одежда TENJI BRAND с доставкой по всему Казахстану.'],
        'footer_contacts_title' => ['label' => 'Футер: Контакты заголовок', 'default' => 'Контакты'],
        'footer_telegram' => ['label' => 'Футер: Telegram', 'default' => 'Telegram: @tenjibrand'],
        'footer_email' => ['label' => 'Футер: Email', 'default' => 'Email: hello@tenjibrand.kz'],
        'footer_whatsapp' => ['label' => 'Футер: WhatsApp подпись', 'default' => 'WhatsApp: +7 708 007 41 62'],
        'footer_subscribe_title' => ['label' => 'Футер: Подписка заголовок', 'default' => 'Подписка'],
        'footer_subscribe_text' => ['label' => 'Футер: Подписка текст', 'default' => 'Получайте анонсы дропов первыми.'],
        'footer_subscribe_btn' => ['label' => 'Футер: Подписка кнопка', 'default' => 'Подписаться'],
        'whatsapp_number' => ['label' => 'WhatsApp номер', 'default' => '+77080074162'],
    ];
}

function tenji_seed_defaults(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :hash)');
        $stmt->execute([
            ':username' => 'admin',
            ':hash' => password_hash('admin12345', PASSWORD_DEFAULT),
        ]);
    }

    $insert = $pdo->prepare('INSERT OR IGNORE INTO cms_settings (setting_key, setting_value) VALUES (:key, :value)');
    foreach (tenji_cms_schema() as $key => $row) {
        $insert->execute([':key' => $key, ':value' => (string) $row['default']]);
    }
}

function tenji_get_settings(PDO $pdo): array
{
    $rows = $pdo->query('SELECT setting_key, setting_value FROM cms_settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    foreach (tenji_cms_schema() as $key => $meta) {
        if (!array_key_exists($key, $settings)) {
            $settings[$key] = (string) $meta['default'];
        }
    }
    return $settings;
}

function tenji_save_settings(PDO $pdo, array $payload): void
{
    $stmt = $pdo->prepare('INSERT INTO cms_settings (setting_key, setting_value) VALUES (:key, :value)
        ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value');
    foreach (tenji_cms_schema() as $key => $_meta) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }
        $stmt->execute([
            ':key' => $key,
            ':value' => trim((string) $payload[$key]),
        ]);
    }
}

function tenji_get_products(PDO $pdo): array
{
    $products = $pdo->query('SELECT * FROM products ORDER BY datetime(created_at) DESC, id DESC')->fetchAll();
    if (!$products) {
        return [];
    }

    $ids = array_map(static fn(array $row): int => (int) $row['id'], $products);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id IN ($in) ORDER BY sort_order ASC, id ASC");
    foreach ($ids as $i => $id) {
        $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $images = $stmt->fetchAll();

    $imagesByProduct = [];
    foreach ($images as $image) {
        $pid = (int) $image['product_id'];
        $imagesByProduct[$pid][] = $image['image_path'];
    }

    $result = [];
    foreach ($products as $row) {
        $id = (int) $row['id'];
        $sizes = array_values(array_filter(array_map('trim', explode(',', (string) $row['sizes_csv']))));
        if (!$sizes) {
            $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        }
        $result[] = [
            'id' => $id,
            'title' => $row['title'],
            'price' => (int) $row['price'],
            'collection' => $row['collection_name'],
            'description' => $row['description'],
            'sizes' => $sizes,
            'images' => $imagesByProduct[$id] ?? [],
            'created_at' => $row['created_at'],
        ];
    }
    return $result;
}

