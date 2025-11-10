<?php
$clientId = isset($_GET['client-id']) ? intval($_GET['client-id']) : 0;
$clientFile = "clients/{$clientId}.json";

if (!$clientId || !file_exists($clientFile)) {
    die("Invalid or missing client ID.");
}

$clientData = json_decode(file_get_contents($clientFile), true);
if (!$clientData) {
    die("Failed to load client data.");
}

$errors = [];
$success = '';


// Load main clients.json to get WhatsApp and phone from main clients list
$clientList = json_decode(file_get_contents('clients.json'), true);
$clientInfo = null;
if (is_array($clientList)) {
    foreach ($clientList as $client) {
        if (isset($client['id']) && $client['id'] == $clientId) {
            $clientInfo = $client;
            break;
        }
    }
}



// Handle Add New Product form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $url = trim($_POST['url'] ?? '');
    $product_type = $_POST['product_type'] ?? '';
    $package_name = $_POST['package_name'] ?? '';
    $payment_amount = trim($_POST['payment_amount'] ?? '');
    $creation_date = date('Y-m-d');

    if (!$url) {
        $errors[] = "Product URL is required.";
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = "Invalid URL format.";
    }
    if (!in_array($product_type, ['wall art', 'sticker'])) {
        $errors[] = "Invalid product type.";
    }
    if (!in_array($package_name, ['free trial', 'month', 'year'])) {
        $errors[] = "Invalid package.";
    }

    if (empty($errors)) {
        $daysToAdd = 0;
        switch ($package_name) {
            case 'free trial':
                $daysToAdd = 7;
                break;
            case 'month':
                $daysToAdd = 30;
                break;
            case 'year':
                $daysToAdd = 365;
                break;
        }
        $expiry_date = date('Y-m-d', strtotime("+{$daysToAdd} days"));

        $maxId = 0;
        foreach ($clientData['products'] ?? [] as $p) {
            if (isset($p['id']) && $p['id'] > $maxId) {
                $maxId = $p['id'];
            }
        }
        $newProductId = $maxId + 1;

        $newProduct = [
            'id' => $newProductId,
            'url' => $url,
            'product_type' => $product_type,
            'package_name' => $package_name,
            'creation_date' => $creation_date,
            'last_payment_date' => $creation_date,
            'payment_amount' => $payment_amount,
            'expiry_date' => $expiry_date
        ];

        if (!isset($clientData['products']) || !is_array($clientData['products'])) {
            $clientData['products'] = [];
        }
        array_unshift($clientData['products'], $newProduct);
        file_put_contents($clientFile, json_encode($clientData, JSON_PRETTY_PRINT));
        header("Location: client.php?client-id={$clientId}&tab=products");
        exit;
    }
}

$prodTab = $_GET['tab'] ?? 'products';
$productIdToOpen = isset($_GET['product-id']) ? intval($_GET['product-id']) : null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$today = new DateTime();

function paginate($data, $page, $perPage = 10)
{
    $total = count($data);
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $start = ($page - 1) * $perPage;
    return [array_slice($data, $start, $perPage), $totalPages];
}

$products = $clientData['products'] ?? [];
$filteredProducts = [];

// Filters from GET
$filterURL = trim($_GET['filter_url'] ?? '');
$filterProductType = $_GET['filter_product_type'] ?? '';
$filterCreationDate = $_GET['filter_creation_date'] ?? '';
$filterPackage = $_GET['filter_package'] ?? '';
$filterLastPaymentDate = $_GET['filter_last_payment_date'] ?? '';
$filterPaymentAmount = trim($_GET['filter_payment_amount'] ?? '');
$filterExpiryDate = $_GET['filter_expiry_date'] ?? '';

// Search active if any filter filled
$searchActive = ($filterURL !== ''
    || ($filterProductType !== '' && $filterProductType !== 'all')
    || $filterCreationDate !== ''
    || ($filterPackage !== '' && $filterPackage !== 'all')
    || $filterLastPaymentDate !== ''
    || $filterPaymentAmount !== ''
    || $filterExpiryDate !== '');


// Force tab to 'products' on search active
if ($searchActive) {
    $prodTab = 'products';
}

// Filtering products based on search or tab
if ($searchActive) {
    foreach ($products as $prod) {
        if ($filterURL !== '' && stripos($prod['url'] ?? '', $filterURL) === false) continue;
        if ($filterProductType !== '' && $filterProductType !== 'all' && ($prod['product_type'] ?? '') !== $filterProductType) continue;
        if ($filterCreationDate !== '' && ($prod['creation_date'] ?? '') !== $filterCreationDate) continue;
        if ($filterPackage !== '' && $filterPackage !== 'all' && ($prod['package_name'] ?? '') !== $filterPackage) continue;
        if ($filterLastPaymentDate !== '' && ($prod['last_payment_date'] ?? '') !== $filterLastPaymentDate) continue;
        if ($filterPaymentAmount !== '' && stripos($prod['payment_amount'] ?? '', $filterPaymentAmount) === false) continue;
        if ($filterExpiryDate !== '' && ($prod['expiry_date'] ?? '') !== $filterExpiryDate) continue;
        $filteredProducts[] = $prod;
    }
} else {
    foreach ($products as $prod) {
        $expiryDate = DateTime::createFromFormat('Y-m-d', $prod['expiry_date'] ?? '');
        if (!$expiryDate) {
            if ($prodTab === 'products') {
                $filteredProducts[] = $prod;
            }
            continue;
        }
        if ($prodTab === 'products') {
            $filteredProducts[] = $prod;
        } elseif ($prodTab === 'expired' && $expiryDate < $today) {
            $filteredProducts[] = $prod;
        } elseif ($prodTab === 'expiry') {
            $daysAhead = (clone $today)->modify('+3 days');
            if ($expiryDate >= $today && $expiryDate <= $daysAhead) {
                $filteredProducts[] = $prod;
            }
        }
    }
}

// Highlighted product and exclusion
$highlightedProduct = null;
if ($productIdToOpen !== null) {
    foreach ($filteredProducts as $key => $prod) {
        if (isset($prod['id']) && $prod['id'] == $productIdToOpen) {
            $highlightedProduct = $prod;
            unset($filteredProducts[$key]);
            $filteredProducts = array_values($filteredProducts);
            break;
        }
    }
}

usort($filteredProducts, fn($a, $b) => strtotime($b['expiry_date'] ?? '') - strtotime($a['expiry_date'] ?? ''));
list($pagedProducts, $totalPages) = paginate($filteredProducts, $page);

function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Client <?= h($clientData['name'] ?? '') ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen,
                Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: #fafafa;
            margin: 20px auto;
            color: #222;
            line-height: 1.5;
            width: 95%;
        }

        .client-details {
            border-bottom: 2px solid #4a69bd;
            padding-bottom: 12px;
            margin-bottom: 20px;
            user-select: none;
        }

        .client-details h2 {
            font-weight: 700;
            color: #4a69bd;
            margin: 0 0 8px 0;
        }

        .client-details p {
            font-weight: 500;
            color: #2c3e50;
        }

        nav {
            margin-bottom: 20px;
        }

        nav a {
            font-weight: 600;
            text-decoration: none;
            color: #4a69bd;
            border: 1.5px solid #4a69bd;
            padding: 6px 12px;
            border-radius: 6px;
            margin-right: 8px;
            transition: background-color 0.3s ease;
        }

        nav a:hover {
            background-color: #4a69bd;
            color: white;
        }

        nav a.active {
            background-color: #2e4aad;
            color: white;
            border-color: #2e4aad;
        }

        form {
            margin-bottom: 30px;
        }

        form label {
            display: inline-block;
            margin-right: 10px;
            font-weight: 600;
            color: #3a3a3a;
        }

        form input[type="url"],
        form input[type="text"],
        form input[type="date"],
        form select {
            padding: 6px 10px;
            border: 1.3px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            margin-right: 16px;
            width: 190px;
            box-sizing: border-box;
        }

        form button {
            background-color: #4a69bd;
            border: none;
            color: white;
            font-weight: 700;
            padding: 8px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        form button:hover {
            background-color: #2f488f;
        }

        .highlighted-product {
            background-color: #d9e8ff;
            border: 2px solid #4a69bd;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 35px;
        }

        .highlighted-product h4 {
            margin-top: 0;
            font-size: 1.3em;
            font-weight: 700;
            color: #254884;
        }

        .highlighted-product p {
            margin: 5px 0;
            font-weight: 500;
        }

        .highlighted-product button {
            padding: 6px 14px;
            margin-right: 12px;
        }

        .delete-confirm {
            margin-top: 12px;
            background: #ffe1e1;
            color: #940000;
            border: 1px solid #940000;
            padding: 10px 15px;
            border-radius: 8px;
        }

        .delete-confirm button {
            margin-left: 8px;
            min-width: 70px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            user-select: none;
        }

        th,
        td {
            text-align: left;
            padding: 10px 14px;
            border-bottom: 1px solid #ddd;
            font-weight: 500;
        }

        thead {
            background-color: #4a69bd;
            color: white;
            font-weight: 700;
        }

        tbody tr:hover {
            background-color: #dbe9ff;
        }

        .update-form-row {
            background-color: #f0f5ff;
        }

        .update-form label {
            display: block;
            margin: 8px 0 4px 0;
            font-weight: 600;
        }

        .update-form input[type="url"],
        .update-form input[type="date"],
        .update-form input[type="text"],
        .update-form select {
            width: 280px;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1.5px solid #aaa;
            font-size: 14px;
            box-sizing: border-box;
        }

        .update-form button {
            margin-right: 12px;
            font-weight: 700;
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            color: white;
            background-color: #4a69bd;
            transition: background-color 0.3s;
        }

        .update-form button:hover {
            background-color: #2f488f;
        }

        .update-form button.cancel {
            background-color: #aaa;
        }

        .update-form button.cancel:hover {
            background-color: #888;
        }

        #filterForm label {
            padding-bottom: 0.1in;
        }
    </style>
    <script>
        function toggleUpdate(index) {
            const id = index === 'highlighted' ? 'update-row-highlighted' : 'update-row-' + index;
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'table-row' : 'none';
        }

        function toggleDeleteConfirm(index) {
            const id = index === 'highlighted' ? 'delete-confirm-highlighted' : 'delete-confirm-' + index;
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = (el.style.display === 'block') ? 'none' : 'block';
        }

        function recalcExpiry(prefix, index) {
            const lastPaymentInput = document.getElementById(prefix + '-last_payment_date-' + index);
            const packageSelect = document.getElementById(prefix + '-package_name-' + index);
            const expiryInput = document.getElementById(prefix + '-expiry_date-' + index);
            if (!lastPaymentInput || !packageSelect || !expiryInput) return;
            const lastPaymentDate = lastPaymentInput.value;
            if (!lastPaymentDate) {
                expiryInput.value = '';
                return;
            }
            const date = new Date(lastPaymentDate);
            let addDays = 0;
            switch (packageSelect.value) {
                case 'free trial':
                    addDays = 7;
                    break;
                case 'month':
                    addDays = 30;
                    break;
                case 'year':
                    addDays = 365;
                    break;
            }
            date.setDate(date.getDate() + addDays);
            expiryInput.value = date.toISOString().slice(0, 10);
        }
        window.addEventListener('DOMContentLoaded', () => {
            const count = <?= count($pagedProducts) ?>;
            for (let i = 0; i < count; i++) {
                ['last_payment_date', 'package_name'].forEach(field => {
                    const el = document.getElementById('update-' + field + '-' + i);
                    if (el) el.addEventListener('change', () => recalcExpiry('update', i));
                });
            }
            <?php if ($highlightedProduct): ?>
                recalcExpiry('update-highlighted', '');
            <?php endif; ?>
            const openId = <?= json_encode($productIdToOpen) ?>;
            if (openId) {
                for (let i = 0; i < count; i++) {
                    if (<?= json_encode($pagedProducts) ?>[i].id == openId) {
                        toggleUpdate(i);
                        break;
                    }
                }
            }
        });
    </script>
</head>

<body>

    <div class="client-details">
        <h2>Client: <?= h($clientData['name']) ?></h2>
        <p><strong>ID:</strong> <?= h($clientData['id']) ?></p>
        <p><strong>WhatsApp:</strong> <?= isset($clientInfo['whatsapp']) && $clientInfo['whatsapp'] !== '' ? h($clientInfo['whatsapp']) : 'N/A' ?></p>
        <p><strong>Email:</strong> <?= isset($clientInfo['email']) && $clientInfo['email'] !== '' ? h($clientInfo['email']) : 'N/A' ?></p>

    </div>


    <h3>Add New Product</h3>
    <?php if ($errors): ?>
        <div style="color:#b00020; margin-bottom:15px;">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="POST" action="client.php?client-id=<?= h($clientId) ?>">
        <input type="hidden" name="action" value="add" />
        <label for="url">Product URL:</label>
        <input type="url" id="url" name="url" required />
        <label for="product_type">Product Type:</label>
        <select id="product_type" name="product_type" required>
            <option value="wall art">Wall Art</option>
            <option value="sticker">Sticker</option>
        </select>
        <label for="package_name">Package:</label>
        <select id="package_name" name="package_name" required>
            <option value="free trial">Free Trial</option>
            <option value="month">Month</option>
            <option value="year">Year</option>
        </select>
        <label for="payment_amount">Payment Amount:</label>
        <input type="text" id="payment_amount" name="payment_amount" />
        <div style="margin-top: 0.2in; text-align:end;" class="btn">
            <button type="submit">Add Product</button>
        </div>

    </form>

    <?php if ($highlightedProduct): ?>
        <div class="highlighted-product">
            <h4>Highlighted Product (ID: <?= h($highlightedProduct['id']) ?>)</h4>
            <p><strong>URL:</strong> <a href="<?= h($highlightedProduct['url']) ?>" target="_blank"><?= h($highlightedProduct['url']) ?></a></p>
            <p><strong>Type:</strong> <?= h($highlightedProduct['product_type']) ?></p>
            <p><strong>Creation Date:</strong> <?= h($highlightedProduct['creation_date']) ?></p>
            <p><strong>Package:</strong> <?= h($highlightedProduct['package_name']) ?></p>
            <p><strong>Last Payment Date:</strong> <?= h($highlightedProduct['last_payment_date'] ?? '') ?></p>
            <p><strong>Payment Amount:</strong> <?= h($highlightedProduct['payment_amount'] ?? '') ?></p>
            <p><strong>Expiry Date:</strong> <?= h($highlightedProduct['expiry_date']) ?></p>
            <button type="button" class="btn-link" onclick="toggleUpdate('highlighted')">Update</button>
            <button type="button" class="btn-link btn-danger" onclick="toggleDeleteConfirm('highlighted')">Delete</button>
            <div id="delete-confirm-highlighted" class="delete-confirm" style="display:none;">
                <form method="POST" action="update.php">
                    <input type="hidden" name="client_id" value="<?= $clientId ?>">
                    <input type="hidden" name="product_id" value="<?= h($highlightedProduct['id']) ?>">
                    <input type="hidden" name="action" value="delete" />
                    Confirm delete?
                    <button type="submit" class="btn-danger">Yes</button>
                    <button type="button" onclick="toggleDeleteConfirm('highlighted')">No</button>
                </form>
            </div>
            <div id="update-row-highlighted" class="update-form" style="display:none;">
                <form method="POST" action="update.php">
                    <input type="hidden" name="client_id" value="<?= $clientId ?>">
                    <input type="hidden" name="product_id" value="<?= h($highlightedProduct['id']) ?>">
                    <input type="hidden" name="action" value="update" />
                    <label>URL:
                        <input type="url" name="url" value="<?= h($highlightedProduct['url']) ?>" required />
                    </label>
                    <label>Product Type:
                        <select name="product_type" required>
                            <option value="wall art" <?= $highlightedProduct['product_type'] === 'wall art' ? 'selected' : '' ?>>Wall Art</option>
                            <option value="sticker" <?= $highlightedProduct['product_type'] === 'sticker' ? 'selected' : '' ?>>Sticker</option>
                        </select>
                    </label>
                    <label>Package:
                        <select name="package_name" id="update-package_name-highlighted" onchange="recalcExpiry('update-highlighted', '')" required>
                            <option value="free trial" <?= $highlightedProduct['package_name'] === 'free trial' ? 'selected' : '' ?>>Free Trial</option>
                            <option value="month" <?= $highlightedProduct['package_name'] === 'month' ? 'selected' : '' ?>>Month</option>
                            <option value="year" <?= $highlightedProduct['package_name'] === 'year' ? 'selected' : '' ?>>Year</option>
                        </select>
                    </label>
                    <label>Last Payment Date:
                        <input type="date" id="update-last_payment_date-highlighted" name="last_payment_date" value="<?= h($highlightedProduct['last_payment_date'] ?? '') ?>" onchange="recalcExpiry('update-highlighted', '')" required />
                    </label>
                    <label>Payment Amount:
                        <input type="text" name="payment_amount" value="<?= h($highlightedProduct['payment_amount'] ?? '') ?>" />
                    </label>
                    <label>Expiry Date:
                        <input type="date" id="update-expiry_date-highlighted" name="expiry_date" value="<?= h($highlightedProduct['expiry_date']) ?>" readonly />
                    </label>

                    <button type="submit">Save</button>
                    <button type="button" onclick="toggleUpdate('highlighted')" class="cancel">Cancel</button>
                </form>
            </div>
        </div>
        <hr />
    <?php endif; ?>


    <h3>Products List</h3>

    <nav>
        <a href="client.php?client-id=<?= $clientId ?>&tab=products" class="<?= $prodTab === 'products' ? 'active' : '' ?>">Products</a>
        <a href="client.php?client-id=<?= $clientId ?>&tab=expired" class="<?= $prodTab === 'expired' ? 'active' : '' ?>">Expired</a>
        <a href="client.php?client-id=<?= $clientId ?>&tab=expiry" class="<?= $prodTab === 'expiry' ? 'active' : '' ?>">Expiry Coming</a>
    </nav>


    <form id="filterForm" method="GET" action="client.php" style="margin:20px 5px; padding:15px; border:1px solid #c5cee0; border-radius:8px; background:#fff;">
        <input type="hidden" name="client-id" value="<?= h($clientId) ?>" />
        <input type="hidden" name="tab" value="products" />

        <label>URL:
            <input type="text" name="filter_url" value="<?= h($filterURL) ?>" />
        </label>
        <label>Product Type:
            <select name="filter_product_type">
                <option value="all">All</option>
                <option value="wall art" <?= $filterProductType === 'wall art' ? 'selected' : '' ?>>Wall Art</option>
                <option value="sticker" <?= $filterProductType === 'sticker' ? 'selected' : '' ?>>Sticker</option>
            </select>
        </label>
        <label>Creation Date:
            <input type="date" name="filter_creation_date" value="<?= h($filterCreationDate) ?>" />
        </label>
        <label>Package:
            <select name="filter_package">
                <option value="all">All</option>
                <option value="free trial" <?= $filterPackage === 'free trial' ? 'selected' : '' ?>>Free Trial</option>
                <option value="month" <?= $filterPackage === 'month' ? 'selected' : '' ?>>Month</option>
                <option value="year" <?= $filterPackage === 'year' ? 'selected' : '' ?>>Year</option>
            </select>
        </label>
        <label>Last Payment Date:
            <input type="date" name="filter_last_payment_date" value="<?= h($filterLastPaymentDate) ?>" />
        </label>
        <label>Payment Amount:
            <input type="text" name="filter_payment_amount" value="<?= h($filterPaymentAmount) ?>" style="width:110px;" />
        </label>
        <label>Expiry Date:
            <input type="date" name="filter_expiry_date" value="<?= h($filterExpiryDate) ?>" />
        </label>

        <div style="text-align:end;" class="btn">
            <button type="submit">Search</button>
        </div>

    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Type</th>
                    <th>Creation Date</th>
                    <th>Package</th>
                    <th>Last Payment Date</th>
                    <th>Payment Amount</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pagedProducts) === 0): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">No products found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pagedProducts as $index => $product): ?>
                        <tr>
                            <td><a href="<?= h($product['url']) ?>" target="_blank"><?= h($product['url']) ?></a></td>
                            <td><?= h($product['product_type']) ?></td>
                            <td><?= h($product['creation_date']) ?></td>
                            <td><?= h($product['package_name']) ?></td>
                            <td><?= h($product['last_payment_date'] ?? '') ?></td>
                            <td><?= h($product['payment_amount'] ?? '') ?></td>
                            <td><?= h($product['expiry_date']) ?></td>
                            <td>
                                <button type="button" class="btn-link" onclick="toggleUpdate(<?= $index ?>)">Update</button><br>
                                <button type="button" class="btn-link btn-danger" onclick="toggleDeleteConfirm(<?= $index ?>)">Delete</button>
                                <div id="delete-confirm-<?= $index ?>" class="delete-confirm" style="display:none;">
                                    <form method="POST" action="update.php">
                                        <input type="hidden" name="client_id" value="<?= $clientId ?>">
                                        <input type="hidden" name="index" value="<?= $index ?>">
                                        <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
                                        <input type="hidden" name="action" value="delete" />
                                        Confirm delete?
                                        <button type="submit" class="btn-danger">Yes</button>
                                        <button type="button" onclick="toggleDeleteConfirm(<?= $index ?>)">No</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="update-row-<?= $index ?>" class="update-form-row" style="display:none;">
                            <td colspan="8">
                                <form method="POST" action="update.php">
                                    <input type="hidden" name="client_id" value="<?= $clientId ?>">
                                    <input type="hidden" name="index" value="<?= $index ?>">
                                    <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
                                    <input type="hidden" name="action" value="update" />
                                    <label>URL:
                                        <input type="url" name="url" value="<?= h($product['url']) ?>" required />
                                    </label><br>
                                    <label>Product Type:
                                        <select name="product_type" required>
                                            <option value="wall art" <?= $product['product_type'] === 'wall art' ? 'selected' : '' ?>>Wall Art</option>
                                            <option value="sticker" <?= $product['product_type'] === 'sticker' ? 'selected' : '' ?>>Sticker</option>
                                        </select>
                                    </label><br>
                                    <label>Package:
                                        <select name="package_name" id="update-package_name-<?= $index ?>" required onchange="recalcExpiry('update', <?= $index ?>)">
                                            <option value="free trial" <?= $product['package_name'] === 'free trial' ? 'selected' : '' ?>>Free Trial</option>
                                            <option value="month" <?= $product['package_name'] === 'month' ? 'selected' : '' ?>>Month</option>
                                            <option value="year" <?= $product['package_name'] === 'year' ? 'selected' : '' ?>>Year</option>
                                        </select>
                                    </label><br>
                                    <label>Last Payment Date:
                                        <input type="date" id="update-last_payment_date-<?= $index ?>" name="last_payment_date" value="<?= h($product['last_payment_date'] ?? '') ?>" required onchange="recalcExpiry('update', <?= $index ?>)" />
                                    </label><br>
                                    <label>Payment Amount:
                                        <input type="text" name="payment_amount" value="<?= h($product['payment_amount'] ?? '') ?>" />
                                    </label><br>
                                    <label>Expiry Date:
                                        <input type="date" id="update-expiry_date-<?= $index ?>" name="expiry_date" value="<?= h($product['expiry_date']) ?>" readonly />
                                    </label><br>
                                    <button type="submit">Save</button>
                                    <button type="button" onclick="toggleUpdate(<?= $index ?>)" class="cancel">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="margin: 20px auto; text-align: center; max-width: 900px;">
        <?php if ($page > 1): ?>
            <a href="client.php?client-id=<?= urlencode($clientId) ?>&tab=<?= urlencode($prodTab) ?>&page=<?= $page - 1 ?>" style="margin-right:15px;">&laquo; Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="client.php?client-id=<?= urlencode($clientId) ?>&tab=<?= urlencode($prodTab) ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>

    <script>
        function toggleUpdate(index) {
            const id = index === 'highlighted' ? 'update-row-highlighted' : 'update-row-' + index;
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'table-row' : 'none';
        }

        function toggleDeleteConfirm(index) {
            const id = index === 'highlighted' ? 'delete-confirm-highlighted' : 'delete-confirm-' + index;
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = (el.style.display === 'block') ? 'none' : 'block';
        }

        function recalcExpiry(prefix, index) {
            const lastPaymentInput = document.getElementById(prefix + '-last_payment_date-' + index);
            const packageSelect = document.getElementById(prefix + '-package_name-' + index);
            const expiryInput = document.getElementById(prefix + '-expiry_date-' + index);
            if (!lastPaymentInput || !packageSelect || !expiryInput) return;
            const lastPaymentDate = lastPaymentInput.value;
            if (!lastPaymentDate) {
                expiryInput.value = '';
                return;
            }
            const date = new Date(lastPaymentDate);
            let addDays = 0;
            switch (packageSelect.value) {
                case 'free trial':
                    addDays = 7;
                    break;
                case 'month':
                    addDays = 30;
                    break;
                case 'year':
                    addDays = 365;
                    break;
            }
            date.setDate(date.getDate() + addDays);
            expiryInput.value = date.toISOString().slice(0, 10);
        }
        window.addEventListener('DOMContentLoaded', () => {
            const count = <?= count($pagedProducts) ?>;
            for (let i = 0; i < count; i++) {
                ['last_payment_date', 'package_name'].forEach(field => {
                    const el = document.getElementById('update-' + field + '-' + i);
                    if (el) el.addEventListener('change', () => recalcExpiry('update', i));
                });
            }
            <?php if ($highlightedProduct): ?>
                recalcExpiry('update-highlighted', '');
            <?php endif; ?>
            const openId = <?= json_encode($productIdToOpen) ?>;
            if (openId) {
                for (let i = 0; i < count; i++) {
                    if (<?= json_encode($pagedProducts) ?>[i].id == openId) {
                        toggleUpdate(i);
                        break;
                    }
                }
            }
        });
    </script>
</body>

</html>