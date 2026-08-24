<?php
/**
 * Site-wide configuration. Change the brand here and it updates everywhere.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';

const BRAND       = 'Core';
const BRAND_ALT   = 'Forge';
const BRAND_FULL  = 'CoreForge';
const TAGLINE     = 'PC components, picked and tested by people who build.';
const STORE_EMAIL = 'sales@coreforge.pk';
const STORE_PHONE = '+92 81 234 5678';
const STORE_ADDR  = 'Tech Enclave, Quetta, Pakistan';
const STORE_HOURS = 'Mon-Sat: 9:00 AM - 7:00 PM (PKT)';

/** Product categories, in display order, mapped to their illustration. */
const CATEGORIES = [
    'Processors'           => 'cpu-7800x3d.jpg',
    'Graphics Cards'       => 'gpu-4070ts.jpg',
    'Memory'               => 'ram-tz32.jpg',
    'Storage'              => 'ssd-990p.jpg',
    'Motherboards & Power' => 'mb-b650ef.jpg',
];

/** Default photo used when a new product is added for a category. */
const CATEGORY_IMAGE = CATEGORIES;

/** True when the current session has signed in to the admin area. */
function isAdmin(): bool {
    return !empty($_SESSION['admin']);
}

/** Escape helper — every bit of dynamic output goes through this. */
function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/** Format a price for display. */
function money($v): string {
    return '$' . number_format((float)$v, 2);
}

/**
 * Storefront flag: only shown when there is something worth saying.
 * Returns [css class, label]; an empty label means render no flag at all.
 */
function stockFlag(int $stock): array {
    if ($stock <= 0) return ['out', 'Sold out'];
    if ($stock <= 5) return ['low', 'Low stock'];
    return ['', ''];
}

/** Admin table tag: always shows a state. Returns [css class, label]. */
function stockTag(int $stock): array {
    if ($stock <= 0) return ['out', 'Sold out'];
    if ($stock <= 5) return ['low', $stock . ' left'];
    return ['ok', $stock . ' in stock'];
}

/** Items currently in the session cart, resolved against the database. */
function cartItems(PDO $pdo): array {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) return [];
    $in = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
    $stmt->execute(array_keys($cart));   // keys are product ids; values are quantities
    $rows = $stmt->fetchAll();
    // Attach the quantity held in the session against each row.
    foreach ($rows as &$r) { $r['qty'] = (int)($cart[$r['id']] ?? 1); }
    return $rows;
}

function cartCount(): int {
    return array_sum($_SESSION['cart'] ?? []);
}

/* ============================================================
   Card helpers
   Used to validate a card at checkout. Note that neither the full
   number nor the CVC is ever written to the database.
   ============================================================ */

/** Strip everything that is not a digit. */
function digitsOnly(string $v): string {
    return preg_replace('/\D+/', '', $v);
}

/** Luhn checksum — catches typos and obviously invalid numbers. */
function luhnValid(string $number): bool {
    $n = digitsOnly($number);
    if (strlen($n) < 12 || strlen($n) > 19) return false;
    $sum = 0;
    $alt = false;
    for ($i = strlen($n) - 1; $i >= 0; $i--) {
        $d = (int)$n[$i];
        if ($alt) {
            $d *= 2;
            if ($d > 9) $d -= 9;
        }
        $sum += $d;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

/** Identify the card scheme from its leading digits. */
function cardBrand(string $number): string {
    $n = digitsOnly($number);
    if ($n === '') return 'Unknown';
    if (preg_match('/^4/', $n))                                    return 'Visa';
    if (preg_match('/^(5[1-5]|2[2-7])/', $n))                      return 'Mastercard';
    if (preg_match('/^3[47]/', $n))                                return 'Amex';
    if (preg_match('/^(6011|65|64[4-9])/', $n))                    return 'Discover';
    if (preg_match('/^3(0[0-5]|[68])/', $n))                       return 'Diners';
    if (preg_match('/^35(2[89]|[3-8])/', $n))                      return 'JCB';
    return 'Unknown';
}

/** Digit counts each scheme actually issues. */
function cardLengths(string $brand): array {
    return match ($brand) {
        'Amex'       => [15],
        'Diners'     => [14],
        'Visa'       => [13, 16, 19],
        'Mastercard' => [16],
        'Discover'   => [16, 19],
        'JCB'        => [16],
        default      => [],
    };
}

/** True when the number has a length its scheme actually uses. */
function cardLengthOk(string $brand, string $number): bool {
    $lens = cardLengths($brand);
    return $lens === [] ? false : in_array(strlen(digitsOnly($number)), $lens, true);
}

/** How many CVC digits the scheme expects. */
function cvcLength(string $brand): int {
    return $brand === 'Amex' ? 4 : 3;
}

/** True when MM/YY is this month or later. */
function expiryInFuture(int $month, int $year): bool {
    if ($month < 1 || $month > 12) return false;
    if ($year < 100) $year += 2000;
    $now = (int)date('Y') * 12 + (int)date('n');
    return $year * 12 + $month >= $now;
}

/** A human-friendly order reference. */
function makeOrderRef(): string {
    return 'CF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}
