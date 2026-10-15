```php
<?php
$host = getenv('DB_HOST') ?: 'mysql';
$db = getenv('MYSQL_DATABASE') ?: 'cruddb';
$user = getenv('MYSQL_USER') ?: 'cruduser';
$pass = getenv('MYSQL_PASSWORD') ?: '';

function h($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$message = '';
$error = '';
$items = [];
$editItem = null;

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS inventory_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(150) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            description VARCHAR(500) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $itemName = trim($_POST['item_name'] ?? '');
            $quantityInput = $_POST['quantity'] ?? '';
            $description = trim($_POST['description'] ?? '');

            if ($itemName === '') {
                throw new RuntimeException(
                    'Item name is required.'
                );
            }

            if (strlen($itemName) > 150) {
                throw new RuntimeException(
                    'Item name cannot exceed 150 characters.'
                );
            }

            if (strlen($description) > 500) {
                throw new RuntimeException(
                    'Description cannot exceed 500 characters.'
                );
            }

            if (
                $quantityInput === '' ||
                filter_var($quantityInput, FILTER_VALIDATE_INT) === false ||
                (int) $quantityInput < 0
            ) {
                throw new RuntimeException(
                    'Quantity must be a non-negative whole number.'
                );
            }

            $quantity = (int) $quantityInput;

            if ($action === 'create') {
                $statement = $pdo->prepare(
                    'INSERT INTO inventory_items
                        (item_name, quantity, description)
                     VALUES (?, ?, ?)'
                );

                $statement->execute([
                    $itemName,
                    $quantity,
                    $description,
                ]);

                header('Location: /?message=created');
                exit;
            }

            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id < 1) {
                throw new RuntimeException(
                    'A valid item ID is required.'
                );
            }

            $statement = $pdo->prepare(
                'UPDATE inventory_items
                 SET item_name = ?, quantity = ?, description = ?
                 WHERE id = ?'
            );

            $statement->execute([
                $itemName,
                $quantity,
                $description,
                $id,
            ]);

            header('Location: /?message=updated');
            exit;
        }

        if ($action === 'delete') {
            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id < 1) {
                throw new RuntimeException(
                    'A valid item ID is required.'
                );
            }

            $statement = $pdo->prepare(
                'DELETE FROM inventory_items WHERE id = ?'
            );

            $statement->execute([$id]);

            header('Location: /?message=deleted');
            exit;
        }
    }

    if (isset($_GET['edit'])) {
        $id = filter_var(
            $_GET['edit'],
            FILTER_VALIDATE_INT
        );

        if ($id !== false && $id > 0) {
            $statement = $pdo->prepare(
                'SELECT id, item_name, quantity, description
                 FROM inventory_items
                 WHERE id = ?'
            );

            $statement->execute([$id]);
            $editItem = $statement->fetch() ?: null;
        }
    }

    $messageCode = $_GET['message'] ?? '';

    if ($messageCode === 'created') {
        $message = 'Item created successfully.';
    } elseif ($messageCode === 'updated') {
        $message = 'Item updated successfully.';
    } elseif ($messageCode === 'deleted') {
        $message = 'Item deleted successfully.';
    }

    $items = $pdo->query(
        'SELECT id, item_name, quantity, description, updated_at
         FROM inventory_items
         ORDER BY id DESC'
    )->fetchAll();

} catch (Throwable $exception) {
    http_response_code(503);

    // Keep database details out of the public web page.
    error_log('Inventory CRUD database/application error: ' .
        $exception->getMessage());

    $error = 'Database is not ready or the request could not be completed. Check MySQL and the application logs.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenShift Inventory CRUD</title>

    <style>
        body {
            margin: 0;
            background: #f4f6f8;
            color: #1f2933;
            font-family: Arial, sans-serif;
        }

        header {
            background: #151515;
            color: white;
            padding: 24px;
        }

        header div,
        main {
            max-width: 1100px;
            margin: auto;
        }

        main {
            padding: 24px 16px;
        }

        .card {
            background: white;
            border: 1px solid #d2d2d2;
            border-radius: 8px;
            margin-bottom: 22px;
            padding: 20px;
        }

        .message {
            background: #e7f6e7;
            border-left: 5px solid #3e8635;
            margin-bottom: 16px;
            padding: 12px;
        }

        .error {
            background: #faeae8;
            border-left: 5px solid #c9190b;
            margin-bottom: 16px;
            padding: 12px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 12px;
        }

        input,
        textarea {
            border: 1px solid #8a8d90;
            border-radius: 4px;
            box-sizing: border-box;
            font: inherit;
            margin-top: 6px;
            padding: 10px;
            width: 100%;
        }

        textarea {
            min-height: 90px;
        }

        button,
        .button {
            border: 0;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            display: inline-block;
            font: inherit;
            margin: 8px 4px 0 0;
            padding: 10px 16px;
            text-decoration: none;
        }

        .create { background: #0066cc; }
        .update { background: #3e8635; }
        .edit { background: #5752d1; }
        .delete { background: #c9190b; }
        .cancel { background: #6a6e73; }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #d2d2d2;
            padding: 12px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f0f0f0;
        }

        .inline-form {
            display: inline;
        }

        .version {
            color: #6a6e73;
            font-size: 14px;
        }
    </style>
</head>

<body>
<header>
    <div>
        <h1>OpenShift Inventory CRUD</h1>
        <p>PHP and MySQL deployed through ACM and Argo CD</p>
    </div>
</header>

<main>
    <?php if ($message !== ''): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <?php if ($editItem): ?>
            <h2>Edit inventory item</h2>

            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id"
                       value="<?= h($editItem['id']) ?>">

                <label for="edit-name">Item name</label>
                <input id="edit-name" name="item_name"
                       maxlength="150" required
                       value="<?= h($editItem['item_name']) ?>">

                <label for="edit-quantity">Quantity</label>
                <input id="edit-quantity" name="quantity"
                       type="number" min="0" step="1" required
                       value="<?= h($editItem['quantity']) ?>">

                <label for="edit-description">Description</label>
                <textarea id="edit-description" name="description"
                          maxlength="500"><?= h($editItem['description']) ?></textarea>

                <button class="update" type="submit">Update Item</button>
                <a class="button cancel" href="/">Cancel</a>
            </form>
        <?php else: ?>
            <h2>Create inventory item</h2>

            <form method="post">
                <input type="hidden" name="action" value="create">

                <label for="item-name">Item name</label>
                <input id="item-name" name="item_name"
                       maxlength="150" required
                       placeholder="Example: Network Cable">

                <label for="quantity">Quantity</label>
                <input id="quantity" name="quantity"
                       type="number" min="0" step="1"
                       value="1" required>

                <label for="description">Description</label>
                <textarea id="description" name="description"
                          maxlength="500"
                          placeholder="Enter the item description"></textarea>

                <button class="create" type="submit">Create Item</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Inventory records</h2>

        <?php if (!$items): ?>
            <p>No inventory records are available.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Description</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h($item['id']) ?></td>
                            <td><?= h($item['item_name']) ?></td>
                            <td><?= h($item['quantity']) ?></td>
                            <td><?= h($item['description']) ?></td>
                            <td><?= h($item['updated_at']) ?></td>
                            <td>
                                <a class="button edit"
                                   href="/?edit=<?= h($item['id']) ?>">
                                    Edit
                                </a>

                                <form class="inline-form" method="post"
                                      onsubmit="return confirm('Delete this item?');">
                                    <input type="hidden" name="action"
                                           value="delete">
                                    <input type="hidden" name="id"
                                           value="<?= h($item['id']) ?>">

                                    <button class="delete" type="submit">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <p class="version">Application version: 1.0</p>
</main>
</body>
</html>
```
