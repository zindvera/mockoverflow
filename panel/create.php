<?php
function load_json($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [];
}

function save_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$clientsFile = 'clients.json';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Simple validation: name required
    if ($name === '') {
        $errors[] = 'Client name is required.';
    }

    if (empty($errors)) {
        $clients = load_json($clientsFile);
        if (!is_array($clients)) {
            $clients = [];
        }

        // Determine next id (max existing + 1)
        $maxId = 0;
        foreach ($clients as $c) {
            if (isset($c['id']) && $c['id'] > $maxId) {
                $maxId = $c['id'];
            }
        }
        $newId = $maxId + 1;

        $newClient = [
            'id' => $newId,
            'name' => $name,
            'whatsapp' => $whatsapp,
            'email' => $email
        ];

        $clients[] = $newClient;
        save_json($clientsFile, $clients);

        // Optionally, create empty client product JSON file
        $clientProductFile = 'clients/' . $newId . '.json';
        if (!file_exists('clients')) {
            mkdir('clients', 0777, true);
        }
        if (!file_exists($clientProductFile)) {
            file_put_contents($clientProductFile, json_encode([
                'id' => $newId,
                'name' => $name,
                'products' => []
            ], JSON_PRETTY_PRINT));
        }

        $success = 'Client added successfully. <a href="index.php">Go back to list</a>';
        // Clear form fields after success
        $name = $whatsapp = $email = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Add New Client</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { max-width: 400px; margin: 0 auto; }
        label { display: block; margin-top: 15px; }
        input[type="text"], input[type="email"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { margin-top: 20px; padding: 10px 15px; font-weight: bold; }
        .error { color: red; }
        .success { color: green; }
        a { color: #007BFF; text-decoration: none; }
    </style>
</head>
<body>

<h2 style="text-align:center;">Add New Client</h2>

<?php if (!empty($errors)) : ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err) : ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success) : ?>
    <div class="success"><?= $success ?></div>
<?php endif; ?>

<form method="POST" action="create.php">
    <label for="name">Client Name (required):</label>
    <input type="text" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required>

    <label for="whatsapp">WhatsApp Number (optional):</label>
    <input type="text" id="whatsapp" name="whatsapp" value="<?= htmlspecialchars($whatsapp ?? '') ?>">

    <label for="email">Email (optional):</label>
    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>">

    <button type="submit">Add Client</button>
</form>

</body>
</html>
