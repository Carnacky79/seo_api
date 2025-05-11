<?php
// Avvia la sessione
session_start();

// Carica le variabili d'ambiente
require_once '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

// Definisci le costanti
define('ADMIN_URL', $_ENV['APP_URL'] . '/admin');
define('ASSETS_URL', ADMIN_URL . '/assets');
define('ADMIN_PATH', dirname(__DIR__));
define('API_PATH', dirname(ADMIN_PATH) . '/public/api');

// Connessione al database
function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], $options);
        } catch (PDOException $e) {
            die("Errore di connessione al database: " . $e->getMessage());
        }
    }

    return $pdo;
}

// Funzione per sanitizzare gli input
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
    } else {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    return $input;
}

// Funzione per reindirizzare
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// Funzione per registrare azioni dell'amministratore
function logAdminAction($adminId, $actionType, $entityType, $entityId, $description) {
    $pdo = getDbConnection();

    $sql = "INSERT INTO admin_actions (admin_id, action_type, entity_type, entity_id, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $adminId,
        $actionType,
        $entityType,
        $entityId,
        $description,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);

    return true;
}

// Funzione per verificare se c'è un amministratore loggato
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Funzione per ottenere i dati dell'amministratore corrente
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }

    $pdo = getDbConnection();
    $sql = "SELECT * FROM administrators WHERE id = ? AND status = 'active'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['admin_id']]);

    return $stmt->fetch();
}

// Funzione per verificare se l'amministratore è un super admin
function isSuperAdmin() {
    $admin = getCurrentAdmin();

    if (!$admin) {
        return false;
    }

    return $admin['role'] === 'super_admin';
}

// Formatta la data
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}
