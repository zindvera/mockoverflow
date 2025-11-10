<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

$clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
$productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
$action = $_POST['action'] ?? '';

$clientFile = "clients/{$clientId}.json";
if (!$clientId || !file_exists($clientFile)) {
    die("Invalid client ID.");
}

$clientData = json_decode(file_get_contents($clientFile), true);
if (!$clientData) {
    die("Failed to load client data.");
}

if (!isset($clientData['products']) || !is_array($clientData['products'])) {
    $clientData['products'] = [];
}

function recalc_expiry($paymentDate, $package) {
    if (!$paymentDate || !$package) return '';
    $dateObj = DateTime::createFromFormat('Y-m-d', $paymentDate);
    if (!$dateObj) return '';
    switch ($package) {
        case 'free trial': $dateObj->modify('+7 days'); break;
        case 'month': $dateObj->modify('+30 days'); break;
        case 'year': $dateObj->modify('+365 days'); break;
    }
    return $dateObj->format('Y-m-d');
}

// Find product index by product ID to avoid issues with indexes changing
function findProductIndexById($products, $productId) {
    foreach ($products as $index => $product) {
        if (isset($product['id']) && $product['id'] == $productId) {
            return $index;
        }
    }
    return -1;
}

if ($action === 'update') {
    if ($productId === null) {
        die("Missing product ID for update.");
    }

    $index = findProductIndexById($clientData['products'], $productId);
    if ($index === -1) {
        die("Product not found for update.");
    }

    $url = trim($_POST['url'] ?? '');
    $product_type = $_POST['product_type'] ?? '';
    $package_name = $_POST['package_name'] ?? '';
    $last_payment_date = $_POST['last_payment_date'] ?? '';
    $payment_amount = $_POST['payment_amount'] ?? '';
    

    // Validation
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        die("Invalid URL.");
    }
    if (!in_array($product_type, ['wall art', 'sticker'])) {
        die("Invalid product type.");
    }
    if (!in_array($package_name, ['free trial', 'month', 'year'])) {
        die("Invalid package.");
    }
    if (!$last_payment_date || !DateTime::createFromFormat('Y-m-d', $last_payment_date)) {
        die("Invalid last payment date.");
    }

    $expiry_date = recalc_expiry($last_payment_date, $package_name);

    $currentProduct = $clientData['products'][$index];
    $productId = $currentProduct['id'];

    // Update product data
    $clientData['products'][$index] = [
        'id' => $productId,
        'url' => $url,
        'product_type' => $product_type,
        'package_name' => $package_name,
        'last_payment_date' => $last_payment_date,
        'payment_amount' => $payment_amount,
        'expiry_date' => $expiry_date,
        'creation_date' => $currentProduct['creation_date'] ?? date('Y-m-d')
    ];

    file_put_contents($clientFile, json_encode($clientData, JSON_PRETTY_PRINT));
    header("Location: client.php?client-id={$clientId}&product-id={$productId}&tab=products");
    exit;
}

if ($action === 'delete') {
    if ($productId === null) {
        die("Missing product ID for deletion.");
    }

    $index = findProductIndexById($clientData['products'], $productId);
    if ($index === -1) {
        die("Product not found for deletion.");
    }

    array_splice($clientData['products'], $index, 1);
    file_put_contents($clientFile, json_encode($clientData, JSON_PRETTY_PRINT));
    header("Location: client.php?client-id={$clientId}&tab=products");
    exit;
}

die("Unknown action.");
