<?php
require_once 'config.php';

// Verifica che l'utente sia autenticato come amministratore
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        // Salva l'URL corrente per il redirect dopo il login
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        redirect(ADMIN_URL . '/login.php');
    }

    // Verifica che l'account sia attivo
    $admin = getCurrentAdmin();

    if (!$admin || $admin['status'] !== 'active') {
        // L'account admin è stato disattivato
        session_destroy();
        redirect(ADMIN_URL . '/login.php?error=account_inactive');
    }

    // Aggiorna il timestamp dell'ultimo accesso
    updateLastLogin($admin['id']);
}

// Verifica che l'utente sia un super admin
function requireSuperAdmin() {
    requireAdmin();

    if (!isSuperAdmin()) {
        redirect(ADMIN_URL . '/index.php?error=permission_denied');
    }
}

// Autentica un amministratore
function loginAdmin($username, $password) {
    $pdo = getDbConnection();

    $sql = "SELECT * FROM administrators WHERE (username = ? OR email = ?) AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();

    // Verifica che l'utente esista e la password sia corretta
    if ($admin && password_verify($password, $admin['password'])) {
        // Imposta le variabili di sessione
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
        $_SESSION['admin_role'] = $admin['role'];

        // Aggiorna l'ultimo accesso
        updateLastLogin($admin['id']);

        // Registro l'azione di login
        logAdminAction($admin['id'], 'login', 'admin', $admin['id'], "Login effettuato");

        return true;
    }

    return false;
}

// Aggiorna il timestamp dell'ultimo accesso
function updateLastLogin($adminId) {
    $pdo = getDbConnection();

    $sql = "UPDATE administrators SET last_login = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$adminId]);
}

// Logout dell'amministratore
function logoutAdmin() {
    // Registro l'azione di logout
    if (isAdminLoggedIn()) {
        logAdminAction($_SESSION['admin_id'], 'logout', 'admin', $_SESSION['admin_id'], "Logout effettuato");
    }

    // Distruggi la sessione
    session_unset();
    session_destroy();

    // Reindirizza alla pagina di login
    redirect(ADMIN_URL . '/login.php');
}

// Genera un hash per la password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Cambia la password di un amministratore
function changeAdminPassword($adminId, $newPassword) {
    $pdo = getDbConnection();

    $hashedPassword = hashPassword($newPassword);

    $sql = "UPDATE administrators SET password = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$hashedPassword, $adminId]);

    if ($result && $stmt->rowCount() > 0) {
        // Registro l'azione di cambio password
        logAdminAction($adminId, 'update', 'admin', $adminId, "Password aggiornata");
        return true;
    }

    return false;
}
