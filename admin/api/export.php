<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verifica che l'utente sia autenticato
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autorizzato']);
    exit;
}

// Ottieni i parametri della richiesta
$action = $_GET['action'] ?? '';

// Verifica l'azione richiesta
if ($action !== 'export_users') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Azione non valida']);
    exit;
}

// Parametri dell'esportazione
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'csv';
$allData = isset($_GET['all_data']) ? (bool)$_GET['all_data'] : false;
$userIds = isset($_GET['user_ids']) ? json_decode($_GET['user_ids'], true) : [];

// Filtri aggiuntivi
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$verified = isset($_GET['verified']) ? (int)$_GET['verified'] : -1;
$planType = isset($_GET['plan_type']) ? sanitize($_GET['plan_type']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Costruisci la query SQL con i filtri
$sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.fiscal_code, 
               u.phone, u.company, u.vat_number, u.created_at, u.status, 
               u.email_verified, s.plan_type, s.current_period_end";

// Se richiesti tutti i dati, aggiungi campi sensibili
if ($allData) {
    $sql .= ", u.api_key";
}

$sql .= " FROM users u
          LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active' 
          WHERE 1=1";

$params = [];

// Filtra per utenti specifici
if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql .= " AND u.id IN ($placeholders)";
    $params = array_merge($params, $userIds);
}

// Applica i filtri aggiuntivi
if (!empty($search)) {
    $sql .= " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.fiscal_code LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($status)) {
    $sql .= " AND u.status = ?";
    $params[] = $status;
}

if ($verified !== -1) {
    $sql .= " AND u.email_verified = ?";
    $params[] = $verified;
}

if (!empty($planType)) {
    $sql .= " AND s.plan_type = ?";
    $params[] = $planType;
}

if (!empty($dateFrom)) {
    $sql .= " AND u.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $sql .= " AND u.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

// Ordina per ID
$sql .= " ORDER BY u.id ASC";

// Esegui la query
$pdo = getDbConnection();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Verifica che ci siano risultati
if (empty($users)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Nessun utente trovato']);
    exit;
}

// Registra l'azione di esportazione
$admin = getCurrentAdmin();
$userCount = count($users);
$description = "Esportazione di $userCount utenti in formato $format";
logAdminAction($admin['id'], 'export', 'users', 0, $description);

// Formatta i dati per l'esportazione
$exportData = [];

foreach ($users as $user) {
    $userData = [
        'id' => $user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'fiscal_code' => $user['fiscal_code'],
        'phone' => $user['phone'] ?? '',
        'company' => $user['company'] ?? '',
        'vat_number' => $user['vat_number'] ?? '',
        'status' => $user['status'],
        'email_verified' => $user['email_verified'] ? 'Sì' : 'No',
        'plan_type' => $user['plan_type'] ?? 'Nessuno',
        'subscription_end' => $user['current_period_end'] ?? 'N/A',
        'created_at' => $user['created_at']
    ];

    // Aggiungi campi sensibili se richiesti
    if ($allData) {
        $userData['api_key'] = $user['api_key'];
    }

    $exportData[] = $userData;
}

// Esporta i dati nel formato richiesto
$filename = 'export_utenti_' . date('Y-m-d_H-i-s');

switch ($format) {
    case 'json':
        // Esporta in JSON
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename.json\"");
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    case 'excel':
        // Esporta in Excel (CSV con UTF-8 BOM per supporto Excel)
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename.csv\"");

        // Output del file CSV formattato per Excel
        $output = fopen('php://output', 'w');

        // UTF-8 BOM per supporto corretto dei caratteri in Excel
        fwrite($output, "\xEF\xBB\xBF");

        // Intestazioni
        $headers = array_map(function($header) {
            return ucfirst(str_replace('_', ' ', $header));
        }, array_keys($exportData[0]));

        fputcsv($output, $headers, ';'); // Usa il punto e virgola come separatore per Excel

        // Dati
        foreach ($exportData as $row) {
            // Converti valori null in stringhe vuote
            $rowData = array_map(function($value) {
                return $value ?? '';
            }, $row);

            fputcsv($output, $rowData, ';');
        }

        fclose($output);
        break;

    case 'csv':
    default:
        // Esporta in CSV standard
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename.csv\"");

        // Output del file CSV
        $output = fopen('php://output', 'w');

        // UTF-8 BOM per supporto dei caratteri
        fwrite($output, "\xEF\xBB\xBF");

        // Intestazioni
        $headers = array_map(function($header) {
            return ucfirst(str_replace('_', ' ', $header));
        }, array_keys($exportData[0]));

        fputcsv($output, $headers);

        // Dati
        foreach ($exportData as $row) {
            // Converti valori null in stringhe vuote
            $rowData = array_map(function($value) {
                return $value ?? '';
            }, $row);

            fputcsv($output, $rowData);
        }

        fclose($output);
        break;
}

// Fine dell'esecuzione
exit;
