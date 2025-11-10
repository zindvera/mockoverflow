<?php
// Helper function to load JSON file and decode safely
function load_json($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [];
}

function paginate($data, $page, $perPage = 10) {
    $total = count($data);
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $start = ($page - 1) * $perPage;
    return [array_slice($data, $start, $perPage), $totalPages, $page];
}

// Get tab and page from URL, default tab = clients
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'clients';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Filter inputs for clients tab
$filter_name = isset($_GET['client_name']) ? trim($_GET['client_name']) : '';
$filter_whatsapp = isset($_GET['whatsapp']) ? trim($_GET['whatsapp']) : '';
$filter_email = isset($_GET['email']) ? trim($_GET['email']) : '';

$clients = load_json('clients.json');
if (!is_array($clients)) {
    $clients = [];
}

$today = new DateTime();

function get_all_products($clients) {
    $all_products = [];
    foreach ($clients as $client) {
        $clientFile = 'clients/' . $client['id'] . '.json';
        if (file_exists($clientFile)) {
            $clientData = json_decode(file_get_contents($clientFile), true);
            if (!empty($clientData['products'])) {
                foreach ($clientData['products'] as $product) {
                    $product['client_id'] = $clientData['id'];
                    $product['client_name'] = $clientData['name'];
                    $all_products[] = $product;
                }
            }
        }
    }
    return $all_products;
}

$displayData = [];
$totalPages = 1;

if ($tab === 'clients') {
    // Filter clients
    $filtered_clients = array_filter($clients, function ($client) use ($filter_name, $filter_whatsapp, $filter_email) {
        $match = true;
        if ($filter_name !== '') {
            $match = $match && (stripos($client['name'], $filter_name) !== false);
        }
        if ($filter_whatsapp !== '') {
            $match = $match && (isset($client['whatsapp']) && stripos($client['whatsapp'], $filter_whatsapp) !== false);
        }
        if ($filter_email !== '') {
            $match = $match && (isset($client['email']) && stripos($client['email'], $filter_email) !== false);
        }
        return $match;
    });

    usort($filtered_clients, function ($a, $b) {
        return $b['id'] - $a['id'];
    });

    list($displayData, $totalPages, $page) = paginate($filtered_clients, $page);
} else {
    // For expired and expiry coming, get all products
    $all_products = get_all_products($clients);
    $filtered_products = [];
    foreach ($all_products as $product) {
        $expiryDate = DateTime::createFromFormat('Y-m-d', $product['expiry_date']);
        if ($expiryDate) {
            if ($tab === 'expired' && $expiryDate < $today) {
                $filtered_products[] = $product;
            } elseif ($tab === 'expiry' && $expiryDate >= $today && $expiryDate <= (clone $today)->modify('+3 days')) {
                $filtered_products[] = $product;
            }
        }
    }
    usort($filtered_products, function ($a, $b) {
        return strtotime($b['expiry_date']) - strtotime($a['expiry_date']);
    });
    list($displayData, $totalPages, $page) = paginate($filtered_products, $page);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Client Management</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 20px auto;
            max-width: 95vw;
            color: #2c3e50;
            line-height: 1.6;
        }
        nav a {
            margin-right: 15px;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            border: 2px solid transparent;
            color: #34495e;
            transition: all 0.3s ease;
        }
        nav a:hover {
            border-color: #5979cfff;
            color: #2980b9;
        }
        nav a.active {
            background-color: #4a69bd;
            color: white;
            border-color: #2980b9;
        }
        .top-button {
            text-align: center;
            margin-bottom: 25px;
        }
        .top-button a {
            display: inline-block;
            padding: 12px 28px;
            font-weight: 700;
            font-size: 1.1em;
            background-color: #27ae60;
            color: #fff;
            border-radius: 7px;
            box-shadow: 0 4px 8px rgba(39, 174, 96, 0.4);
            border: none;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
        }
        .top-button a:hover {
            background-color: #219150;
            box-shadow: 0 6px 12px rgba(33, 145, 80, 0.6);
        }
        .form-container form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .form-container input, .form-container button {
            padding: 10px 12px;
            font-size: 0.95em;
            border: 1.8px solid #ccd6f6;
            border-radius: 6px;
            min-width: 150px;
            max-width: 220px;
            transition: border-color 0.3s ease;
        }
        .form-container input:focus {
            outline: none;
            border-color: #5661f9;
        }
        .form-container button {
            background-color: #5661f9;
            border: none;
            color: white;
            cursor: pointer;
            font-weight: 700;
            min-width: 100px;
            max-width: 120px;
            transition: background-color 0.3s ease;
        }
        .form-container button:hover {
            background-color: #434abf;
        }
        .table-container {
            max-width: 95%;
            margin: 0 auto 30px;
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 700px;
        }
        thead tr {
            background-color: #4a69bd;
            color: white;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e0e7ff;
            vertical-align: middle;
            user-select: none;
        }
        tbody tr:hover {
            background-color: #dbf0ff;
        }
        tbody tr {
            cursor: pointer;
        }
        .no-data {
            text-align: center;
            font-style: italic;
            color: #7f8c8d;
            font-weight: 600;
            padding: 20px 0;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
            user-select: none;
        }
        .pagination a {
            margin: 0 8px;
            padding: 8px 14px;
            background: #e0eaff;
            border-radius: 6px;
            color: #5661f9;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .pagination a:hover {
            background: #5661f9;
            color: white;
        }
    </style>
    <script>
        function goToClientProduct(clientId, productId, tab) {
            window.location.href = `client.php?client-id=${clientId}&product-id=${productId}&tab=${tab}`;
        }
        function goToClient(clientId) {
            window.location.href = `client.php?client-id=${clientId}`;
        }
        document.addEventListener('DOMContentLoaded', () => {
            // Attach click handlers to rows
            document.querySelectorAll('tbody tr[data-href]').forEach(tr => {
                tr.addEventListener('click', () => {
                    window.location.href = tr.getAttribute('data-href');
                });
            });
        });
    </script>
</head>
<body>

<div class="top-button">
    <a href="create.php">Add New Client</a>
</div>

<h2>Client Management</h2>

<div class="form-container">
    <form method="GET" action="index.php">
        <input type="text" name="client_name" placeholder="Client Name (optional)" value="<?= htmlspecialchars($filter_name) ?>" />
        <input type="text" name="whatsapp" placeholder="WhatsApp Number (optional)" value="<?= htmlspecialchars($filter_whatsapp) ?>" />
        <input type="email" name="email" placeholder="Email (optional)" value="<?= htmlspecialchars($filter_email) ?>" />
        <button type="submit">Filter</button>
    </form>
</div>

<nav>
    <a href="index.php?tab=clients" class="<?= $tab === 'clients' ? 'active' : '' ?>">Clients</a>
    <a href="index.php?tab=expired" class="<?= $tab === 'expired' ? 'active' : '' ?>">Expired</a>
    <a href="index.php?tab=expiry" class="<?= $tab === 'expiry' ? 'active' : '' ?>">Expiry Coming</a>
</nav>

<hr>

<?php if ($tab === 'clients'): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>WhatsApp</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($displayData) === 0): ?>
                    <tr><td class="no-data" colspan="4">No clients found.</td></tr>
                <?php else: ?>
                    <?php foreach ($displayData as $client): ?>
                        <tr data-href="client.php?client-id=<?= htmlspecialchars($client['id']) ?>" tabindex="0">
                            <td><?= htmlspecialchars($client['id']) ?></td>
                            <td><?= htmlspecialchars($client['name']) ?></td>
                            <td><?= htmlspecialchars($client['whatsapp']) ?></td>
                            <td><?= htmlspecialchars($client['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Client</th>
                    <th>URL</th>
                    <th>Type</th>
                    <th>Creation Date</th>
                    <th>Package</th>
                    <th>Expiry Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($displayData) === 0): ?>
                    <tr><td class="no-data" colspan="6">No products found for this tab.</td></tr>
                <?php else: ?>
                    <?php foreach ($displayData as $product): ?>
                        <tr data-href="client.php?client-id=<?= htmlspecialchars($product['client_id']) ?>&product-id=<?= htmlspecialchars($product['id'] ?? '') ?>&tab=<?= htmlspecialchars($tab) ?>" tabindex="0">
                            <td><?= htmlspecialchars($product['client_name']) ?></td>
                            <td><?= htmlspecialchars($product['url']) ?></td>
                            <td><?= htmlspecialchars($product['product_type']) ?></td>
                            <td><?= htmlspecialchars($product['creation_date']) ?></td>
                            <td><?= htmlspecialchars($product['package_name']) ?></td>
                            <td><?= htmlspecialchars($product['expiry_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="index.php?tab=<?= urlencode($tab) ?>&page=<?= $page - 1 ?><?= $tab === 'clients' ? '&client_name=' . urlencode($filter_name) . '&whatsapp=' . urlencode($filter_whatsapp) . '&email=' . urlencode($filter_email) : '' ?>">Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
        <a href="index.php?tab=<?= urlencode($tab) ?>&page=<?= $page + 1 ?><?= $tab === 'clients' ? '&client_name=' . urlencode($filter_name) . '&whatsapp=' . urlencode($filter_whatsapp) . '&email=' . urlencode($filter_email) : '' ?>">Next</a>
    <?php endif; ?>
</div>

</body>
</html>
