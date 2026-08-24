<?php
/**
 * Session-based gate for the admin area.
 *
 * Accounts live in the `admins` table, not in this file. To add one:
 *   php -r 'echo password_hash("their-password", PASSWORD_DEFAULT);'
 *   INSERT INTO admins (username, password_hash, display_name)
 *   VALUES ('name', '<paste hash>', 'Their Name');
 */
require_once __DIR__ . '/config.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECS = 300;

/** Send anyone who is not signed in to the login page. */
function requireAdmin(): void {
    if (!isAdmin()) {
        $_SESSION['login_next'] = basename($_SERVER['PHP_SELF']);
        header('Location: login.php');
        exit;
    }
}

/** The signed-in admin's row (username, display_name, role), or null. */
function adminUser(): ?array {
    return $_SESSION['admin'] ?? null;
}

/** Seconds remaining on a lockout, or 0 when the user may try again. */
function loginLockRemaining(): int {
    $until = $_SESSION['login_locked_until'] ?? 0;
    return $until > time() ? $until - time() : 0;
}

/** Check credentials against the admins table. */
function loginAttempt(PDO $pdo, string $user, string $pass): bool {
    if (loginLockRemaining() > 0) return false;

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    // Always run a hash comparison so a missing username takes the same
    // time as a wrong password.
    $hash = $row['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
    $ok   = password_verify($pass, $hash) && $row;

    if ($ok) {
        session_regenerate_id(true);            // new id on privilege change
        $_SESSION['admin'] = [
            'id'           => (int)$row['id'],
            'username'     => $row['username'],
            'display_name' => $row['display_name'],
            'role'         => $row['role'],
        ];
        $pdo->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = ?")->execute([$row['id']]);
        unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
        return true;
    }

    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECS;
        $_SESSION['login_attempts'] = 0;
    }
    return false;
}

function logoutAdmin(): void {
    unset($_SESSION['admin']);
    session_regenerate_id(true);
}
