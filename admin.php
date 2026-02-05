<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/backend/bootstrap.php';

$pdo = tenji_db();
$errors = [];
$flash = null;

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['tenji_admin_logged']);
}

function redirect_admin(array $params = []): void
{
    $query = $params ? ('?' . http_build_query($params)) : '';
    header('Location: admin.php' . $query);
    exit;
}

function normalize_uploads(array $fileInput): array
{
    $files = [];
    if (!isset($fileInput['name']) || !is_array($fileInput['name'])) {
        return $files;
    }
    $count = count($fileInput['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($fileInput['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $files[] = [
            'name' => (string) $fileInput['name'][$i],
            'tmp_name' => (string) $fileInput['tmp_name'][$i],
            'size' => (int) $fileInput['size'][$i],
        ];
    }
    return $files;
}

function upload_product_images(array $uploads): array
{
    $saved = [];
    foreach ($uploads as $upload) {
        $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            continue;
        }
        $filename = 'product_' . bin2hex(random_bytes(10)) . '.' . $ext;
        $targetAbsolute = TENJI_UPLOAD_DIR . '/' . $filename;
        if (!move_uploaded_file($upload['tmp_name'], $targetAbsolute)) {
            continue;
        }
        $saved[] = 'uploads/products/' . $filename;
    }
    return $saved;
}

function delete_image_file(string $relative): void
{
    $relative = ltrim($relative, '/\\');
    $absolute = __DIR__ . '/' . $relative;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, (string) $user['password_hash'])) {
            $_SESSION['tenji_admin_logged'] = true;
            $_SESSION['tenji_admin_user'] = $username;
            redirect_admin(['ok' => 'login']);
        }
        $errors[] = 'Неверный логин или пароль.';
    }

    if ($action === 'logout' && is_admin_logged_in()) {
        session_destroy();
        redirect_admin(['ok' => 'logout']);
    }

    if (is_admin_logged_in() && $action === 'save_texts') {
        tenji_save_settings($pdo, $_POST);
        redirect_admin(['ok' => 'texts_saved']);
    }

    if (is_admin_logged_in() && $action === 'save_product') {
        $id = (int) ($_POST['product_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $price = (int) ($_POST['price'] ?? 0);
        $collection = trim((string) ($_POST['collection'] ?? 'Other'));
        $description = trim((string) ($_POST['description'] ?? ''));
        $sizes = trim((string) ($_POST['sizes'] ?? 'S,M,L,XL,XXL'));
        $replaceImages = !empty($_POST['replace_images']);
        $uploads = normalize_uploads($_FILES['images'] ?? []);
        $savedImages = upload_product_images($uploads);

        if ($title === '') {
            $errors[] = 'Название товара обязательно.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE products SET title=:title, price=:price, collection_name=:collection, description=:description, sizes_csv=:sizes, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
                $stmt->execute([
                    ':id' => $id,
                    ':title' => $title,
                    ':price' => $price,
                    ':collection' => $collection !== '' ? $collection : 'Other',
                    ':description' => $description,
                    ':sizes' => $sizes,
                ]);

                if ($replaceImages) {
                    $old = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id=:id');
                    $old->execute([':id' => $id]);
                    foreach ($old->fetchAll() as $row) {
                        delete_image_file((string) $row['image_path']);
                    }
                    $pdo->prepare('DELETE FROM product_images WHERE product_id=:id')->execute([':id' => $id]);
                }

                if ($savedImages) {
                    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id=:id');
                    $maxStmt->execute([':id' => $id]);
                    $sort = (int) $maxStmt->fetchColumn() + 1;
                    $imgStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (:pid,:path,:sort)');
                    foreach ($savedImages as $imgPath) {
                        $imgStmt->execute([':pid' => $id, ':path' => $imgPath, ':sort' => $sort]);
                        $sort++;
                    }
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO products (title, price, collection_name, description, sizes_csv) VALUES (:title,:price,:collection,:description,:sizes)');
                $stmt->execute([
                    ':title' => $title,
                    ':price' => $price,
                    ':collection' => $collection !== '' ? $collection : 'Other',
                    ':description' => $description,
                    ':sizes' => $sizes,
                ]);
                $newId = (int) $pdo->lastInsertId();
                $imgStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (:pid,:path,:sort)');
                foreach ($savedImages as $sort => $imgPath) {
                    $imgStmt->execute([':pid' => $newId, ':path' => $imgPath, ':sort' => $sort]);
                }
            }
            redirect_admin(['ok' => 'product_saved']);
        }
    }

    if (is_admin_logged_in() && $action === 'delete_product') {
        $id = (int) ($_POST['product_id'] ?? 0);
        if ($id > 0) {
            $old = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id=:id');
            $old->execute([':id' => $id]);
            foreach ($old->fetchAll() as $row) {
                delete_image_file((string) $row['image_path']);
            }
            $pdo->prepare('DELETE FROM products WHERE id=:id')->execute([':id' => $id]);
        }
        redirect_admin(['ok' => 'product_deleted']);
    }

    if (is_admin_logged_in() && $action === 'remove_image') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        if ($imageId > 0) {
            $stmt = $pdo->prepare('SELECT image_path FROM product_images WHERE id=:id');
            $stmt->execute([':id' => $imageId]);
            $row = $stmt->fetch();
            if ($row) {
                delete_image_file((string) $row['image_path']);
                $pdo->prepare('DELETE FROM product_images WHERE id=:id')->execute([':id' => $imageId]);
            }
        }
        redirect_admin(['ok' => 'image_deleted']);
    }
}

if (isset($_GET['ok'])) {
    $flash = match ((string) $_GET['ok']) {
        'login' => 'Вы вошли в админку.',
        'logout' => 'Вы вышли из админки.',
        'texts_saved' => 'Тексты сайта сохранены.',
        'product_saved' => 'Товар сохранен.',
        'product_deleted' => 'Товар удален.',
        'image_deleted' => 'Изображение удалено.',
        default => null,
    };
}

$settings = tenji_get_settings($pdo);
$schema = tenji_cms_schema();
$products = tenji_get_products($pdo);
$editId = (int) ($_GET['edit'] ?? 0);
$editProduct = null;
foreach ($products as $item) {
    if ((int) $item['id'] === $editId) {
        $editProduct = $item;
        break;
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>TENJI Admin</title>
  <style>
    body{font-family:Arial,sans-serif;background:#111;color:#eee;margin:0;padding:20px}
    .wrap{max-width:1200px;margin:0 auto}
    .card{background:#1a1a22;border:1px solid #333;border-radius:14px;padding:16px;margin-bottom:16px}
    h1,h2,h3{margin:0 0 12px}
    label{display:block;margin-bottom:10px;font-size:14px}
    input,textarea{width:100%;padding:8px 10px;border-radius:8px;border:1px solid #444;background:#0f0f15;color:#fff}
    textarea{min-height:70px}
    .grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(240px,1fr))}
    .span2{grid-column:span 2}
    button{padding:8px 12px;border-radius:8px;border:1px solid #666;background:#20202a;color:#fff;cursor:pointer}
    button.primary{background:#e9e9e9;color:#111;border-color:#e9e9e9}
    .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .flash{background:#17351f;border:1px solid #2a6f3b;padding:8px 10px;border-radius:8px;margin-bottom:10px}
    .error{background:#3f1c1c;border:1px solid #8f3535;padding:8px 10px;border-radius:8px;margin-bottom:10px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #333;padding:8px;vertical-align:top;text-align:left}
    .thumbs{display:flex;gap:6px;flex-wrap:wrap}
    .thumbs img{width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #444}
    .mini{font-size:12px;color:#bbb}
    @media(max-width:850px){.grid{grid-template-columns:1fr}.span2{grid-column:span 1}}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Админка TENJI</h1>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endforeach; ?>

    <?php if (!is_admin_logged_in()): ?>
      <div class="card" style="max-width:420px">
        <h2>Вход</h2>
        <p class="mini">Логин по умолчанию: admin / admin12345 (сразу смените в БД).</p>
        <form method="post">
          <input type="hidden" name="action" value="login" />
          <label>Логин <input type="text" name="username" required /></label>
          <label>Пароль <input type="password" name="password" required /></label>
          <button class="primary" type="submit">Войти</button>
        </form>
      </div>
    <?php else: ?>
      <div class="card row">
        <strong>Вы вошли как <?= htmlspecialchars((string) ($_SESSION['tenji_admin_user'] ?? 'admin'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
        <form method="post" style="margin-left:auto">
          <input type="hidden" name="action" value="logout" />
          <button type="submit">Выйти</button>
        </form>
        <a href="index.html" target="_blank" rel="noopener noreferrer"><button type="button">Открыть сайт</button></a>
      </div>

      <div class="card">
        <h2>Тексты сайта</h2>
        <form method="post">
          <input type="hidden" name="action" value="save_texts" />
          <div class="grid">
            <?php foreach ($schema as $key => $meta): ?>
              <label>
                <?= htmlspecialchars((string) $meta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                <input type="text" name="<?= htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($settings[$key] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
              </label>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:10px"><button class="primary" type="submit">Сохранить тексты</button></div>
        </form>
      </div>

      <div class="card">
        <h2><?= $editProduct ? 'Редактировать товар' : 'Добавить товар' ?></h2>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_product" />
          <input type="hidden" name="product_id" value="<?= $editProduct ? (int) $editProduct['id'] : 0 ?>" />
          <div class="grid">
            <label>Название
              <input type="text" name="title" required value="<?= htmlspecialchars((string) ($editProduct['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
            </label>
            <label>Цена (₸)
              <input type="number" name="price" min="0" step="100" value="<?= htmlspecialchars((string) ($editProduct['price'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
            </label>
            <label>Коллекция
              <input type="text" name="collection" value="<?= htmlspecialchars((string) ($editProduct['collection'] ?? 'Other'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
            </label>
            <label>Размеры (через запятую)
              <input type="text" name="sizes" value="<?= htmlspecialchars($editProduct ? implode(',', $editProduct['sizes']) : 'S,M,L,XL,XXL', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
            </label>
            <label class="span2">Описание
              <textarea name="description"><?= htmlspecialchars((string) ($editProduct['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            </label>
            <label class="span2">Фото товара (можно выбрать несколько)
              <input type="file" name="images[]" accept="image/*" multiple />
            </label>
            <?php if ($editProduct): ?>
              <label class="span2"><input type="checkbox" name="replace_images" value="1" /> Заменить все текущие фото новыми</label>
            <?php endif; ?>
          </div>
          <div class="row" style="margin-top:10px">
            <button class="primary" type="submit">Сохранить товар</button>
            <?php if ($editProduct): ?><a href="admin.php"><button type="button">Отмена редактирования</button></a><?php endif; ?>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>Товары (<?= count($products) ?>)</h2>
        <table>
          <thead>
            <tr><th>ID</th><th>Товар</th><th>Коллекция</th><th>Цена</th><th>Фото</th><th>Действия</th></tr>
          </thead>
          <tbody>
            <?php foreach ($products as $item): ?>
              <tr>
                <td>#<?= (int) $item['id'] ?></td>
                <td>
                  <strong><?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><br />
                  <span class="mini"><?= htmlspecialchars((string) $item['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><br />
                  <span class="mini">Размеры: <?= htmlspecialchars(implode(', ', $item['sizes']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </td>
                <td><?= htmlspecialchars((string) $item['collection'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= number_format((int) $item['price'], 0, '.', ' ') ?> ₸</td>
                <td>
                  <div class="thumbs">
                    <?php foreach ($item['images'] as $imagePath): ?>
                      <img src="<?= htmlspecialchars((string) $imagePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="" />
                    <?php endforeach; ?>
                  </div>
                </td>
                <td>
                  <div class="row">
                    <a href="admin.php?edit=<?= (int) $item['id'] ?>"><button type="button">Ред.</button></a>
                    <form method="post" onsubmit="return confirm('Удалить товар?');">
                      <input type="hidden" name="action" value="delete_product" />
                      <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>" />
                      <button type="submit">Удалить</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>

