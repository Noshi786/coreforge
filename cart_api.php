<?php
/**
 * JSON bag endpoint. The storefront forms still work without JavaScript —
 * this exists so the client can update the bag without a page reload.
 */
require_once __DIR__ . '/partials/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$action = $_POST['cart_action'] ?? '';
$id     = (int)($_POST['product_id'] ?? 0);
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$ok = true; $message = ''; $tone = 'success';

switch ($action) {
    case 'add':
        $stmt = $pdo->prepare("SELECT name, stock FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            $ok = false; $tone = 'error'; $message = 'That product no longer exists.';
        } else {
            $have = $_SESSION['cart'][$id] ?? 0;
            if ((int)$row['stock'] <= 0) {
                $ok = false; $tone = 'warn'; $message = $row['name'] . ' is sold out.';
            } elseif ($have >= (int)$row['stock']) {
                $ok = false; $tone = 'warn'; $message = 'That is all the stock we have of ' . $row['name'] . '.';
            } else {
                $_SESSION['cart'][$id] = $have + 1;
                $message = $row['name'] . ' added to your bag.';
            }
        }
        break;

    case 'decrement':
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] < 1) unset($_SESSION['cart'][$id]);
        }
        break;

    case 'remove':
        unset($_SESSION['cart'][$id]);
        $message = 'Removed from your bag.';
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        $message = 'Bag emptied.';
        break;

    case 'peek':          // read-only: used to prime the drawer on page load
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        exit;
}

/* Rebuild the bag so the client always renders from server truth. */
$items = cartItems($pdo);
$subtotal = 0;
$out = [];
foreach ($items as $it) {
    $subtotal += $it['price'] * $it['qty'];
    $out[] = [
        'id'    => (int)$it['id'],
        'name'  => $it['name'],
        'brand' => $it['brand'],
        'sku'   => $it['sku'],
        'image' => $it['image'],
        'qty'   => (int)$it['qty'],
        'stock' => (int)$it['stock'],
        'line'  => money($it['price'] * $it['qty']),
    ];
}
$shipping = ($subtotal > 0 && $subtotal < 150) ? 12.00 : 0.00;

echo json_encode([
    'ok'       => $ok,
    'tone'     => $tone,
    'message'  => $message,
    'count'    => cartCount(),
    'items'    => $out,
    'subtotal' => money($subtotal),
    'shipping' => $shipping == 0 ? 'Free' : money($shipping),
    'total'    => money($subtotal + $shipping),
    'qty'      => $id ? (int)($_SESSION['cart'][$id] ?? 0) : 0,
    'freeFrom' => $subtotal > 0 && $subtotal < 150 ? money(150 - $subtotal) : null,
], JSON_UNESCAPED_UNICODE);
