<?php
/**
 * API per esportare i log delle richieste API
 *
 * Questo script consente agli utenti di esportare i propri log di richieste API
 * in formato CSV o JSON
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verifica che l'utente sia autenticato
requireLogin();

// Ottieni il formato richiesto dal parametro
$format = strtolower($_GET['format'] ?? 'csv');
if (!in_array($format, ['csv', 'json'])) {
    $format = 'csv'; // Formato predefinito
}

// Ottieni l'intervallo di date (opzionale)
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Valida le date
if (!validateDate($startDate) || !validateDate($endDate)) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
}

// Aggiungi un giorno all'end_date per includere l'intera giornata
$endDate = date('Y-m-d', strtotime($endDate . ' +1 day'));

// Ottieni l'utente corrente
$user = getCurrentUser();

// Ottieni i log delle richieste API dell'utente
$pdo = getDbConnection();
$sql = "SELECT * FROM request_logs
        WHERE user_id = ?
        AND created_at >= ?
        AND created_at < ?
        ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id'], $startDate, $endDate]);
$logs = $stmt->fetchAll();

// Registra l'esportazione nel log degli eventi
$sql = "INSERT INTO event_logs (event_type, event_data, created_at) VALUES (?, ?, NOW())";
$stmt = $pdo->prepare($sql);
$data = json_encode([
    'user_id' => $user['id'],
    'format' => $format,
    'start_date' => $startDate,
    'end_date' => date('Y-m-d', strtotime($endDate . ' -1 day')),
    'count' => count($logs)
]);
$stmt->execute(['logs.export', $data]);

// Definisci il nome del file
$fileName = 'api_logs_' . date('Y-m-d') . '_' . $user['id'] . '.' . $format;

// Genera ed invia i dati nel formato richiesto
if ($format === 'csv') {
    // Imposta gli header per il download del CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    // Crea lo stream di output
    $output = fopen('php://output', 'w');

    // Scrivi l'intestazione UTF-8 BOM per la compatibilità con Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Scrivi l'header della tabella
    fputcsv($output, ['ID', 'URL', 'Codice risposta', 'Tempo di esecuzione', 'IP', 'Data']);

    // Scrivi i dati
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['url'],
            $log['response_code'],
            $log['execution_time'],
            $log['ip_address'],
            $log['created_at']
        ]);
    }

    // Chiudi lo stream
    fclose($output);

} else {
    // Imposta gli header per il download del JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    // Prepara i dati
    $exportData = [
        'user_id' => $user['id'],
        'export_date' => date('Y-m-d H:i:s'),
        'period' => [
            'start_date' => $startDate,
            'end_date' => date('Y-m-d', strtotime($endDate . ' -1 day'))
        ],
        'logs' => $logs
    ];

    // Invia i dati JSON
    echo json_encode($exportData, JSON_PRETTY_PRINT);
}

/**
 * Verifica che una stringa sia una data valida in formato Y-m-d
 *
 * @param string $date Data da verificare
 * @return bool True se la data è valida
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
