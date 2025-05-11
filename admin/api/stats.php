<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verifica che l'utente sia autenticato
if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Non autorizzato']);
    exit;
}

// Verifica il metodo della richiesta
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito']);
    exit;
}

// Imposta l'header della risposta
header('Content-Type: application/json');

// Ottieni l'azione richiesta
$action = $_GET['action'] ?? '';
$pdo = getDbConnection();

// Gestisci le diverse azioni
switch ($action) {
    case 'registrations_chart':
        // Grafico delle registrazioni degli ultimi 30 giorni
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM users 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                GROUP BY DATE(created_at) 
                ORDER BY date";

        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();

        // Prepara i dati per il grafico
        $labels = [];
        $values = [];

        // Crea un array con tutte le date degli ultimi 30 giorni
        $endDate = new DateTime('now');
        $startDate = new DateTime('30 days ago');
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate);

        // Inizializza tutti i giorni a 0
        $registrations = [];
        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            $registrations[$dateStr] = 0;
        }

        // Aggiorna i valori con i dati reali
        foreach ($data as $row) {
            $registrations[$row['date']] = (int)$row['count'];
        }

        // Prepara i dati per il grafico
        foreach ($registrations as $date => $count) {
            $labels[] = date('d/m', strtotime($date));
            $values[] = $count;
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'labels' => $labels,
                'values' => $values
            ]
        ]);
        break;

    case 'subscriptions_chart':
        // Grafico della distribuzione degli abbonamenti
        $sql = "SELECT plan_type, COUNT(*) as count 
                FROM subscriptions 
                WHERE status = 'active' 
                GROUP BY plan_type";

        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();

        // Prepara i dati per il grafico
        $labels = [];
        $values = [];

        // Assicura che ci siano dati per tutti i tipi di abbonamento
        $subscriptions = [
            'free' => 0,
            'pro' => 0,
            'premium' => 0
        ];

        foreach ($data as $row) {
            $subscriptions[$row['plan_type']] = (int)$row['count'];
        }

        // Mappa i nomi dei piani
        $planNames = [
            'free' => 'Gratuito',
            'pro' => 'Pro',
            'premium' => 'Premium'
        ];

        // Prepara i dati per il grafico
        foreach ($subscriptions as $plan => $count) {
            $labels[] = $planNames[$plan] . ' (' . $count . ')';
            $values[] = $count;
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'labels' => $labels,
                'values' => $values
            ]
        ]);
        break;

    case 'api_requests_chart':
        // Grafico delle richieste API degli ultimi 7 giorni
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM request_logs 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                GROUP BY DATE(created_at) 
                ORDER BY date";

        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();

        // Prepara i dati per il grafico
        $labels = [];
        $values = [];

        // Crea un array con tutte le date degli ultimi 7 giorni
        $endDate = new DateTime('now');
        $startDate = new DateTime('7 days ago');
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate);

        // Inizializza tutti i giorni a 0
        $requests = [];
        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            $requests[$dateStr] = 0;
        }

        // Aggiorna i valori con i dati reali
        foreach ($data as $row) {
            $requests[$row['date']] = (int)$row['count'];
        }

        // Prepara i dati per il grafico
        foreach ($requests as $date => $count) {
            $labels[] = date('d/m', strtotime($date));
            $values[] = $count;
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'labels' => $labels,
                'values' => $values
            ]
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Azione non valida']);
}
