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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito']);
    exit;
}

// Ottieni l'azione richiesta
$action = $_POST['action'] ?? '';
$pdo = getDbConnection();
$admin = getCurrentAdmin();

// Gestisci le diverse azioni
switch ($action) {
    case 'update_subscription':
        // Aggiorna l'abbonamento di un utente
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $planType = isset($_POST['plan_type']) ? sanitize($_POST['plan_type']) : '';

        // Verifica che i parametri siano validi
        if ($userId <= 0 || !in_array($planType, ['free', 'pro', 'premium'])) {
            echo json_encode(['status' => 'error', 'message' => 'Parametri non validi']);
            exit;
        }

        // Verifica se l'utente esiste
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Utente non trovato']);
            exit;
        }

        // Ottieni l'abbonamento attuale dell'utente
        $sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $currentSubscription = $stmt->fetch();

        // Prepara i dati per il nuovo abbonamento
        $currentDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));

        // Se c'è già un abbonamento attivo, aggiornalo
        if ($currentSubscription) {
            $sql = "UPDATE subscriptions 
                    SET plan_type = ?, 
                        status = 'active',
                        current_period_start = ?,
                        current_period_end = ?,
                        updated_at = NOW()
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$planType, $currentDate, $endDate, $currentSubscription['id']]);
        } else {
            // Altrimenti, crea un nuovo abbonamento
            $sql = "INSERT INTO subscriptions 
                    (user_id, plan_type, status, current_period_start, current_period_end) 
                    VALUES (?, ?, 'active', ?, ?)";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$userId, $planType, $currentDate, $endDate]);
        }

        if ($result) {
            // Registra l'azione
            $description = "Aggiornato abbonamento dell'utente ID: $userId a piano '$planType'";
            logAdminAction($admin['id'], 'update', 'subscription', $userId, $description);

            echo json_encode(['status' => 'success', 'message' => 'Abbonamento aggiornato con successo']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Errore durante l\'aggiornamento dell\'abbonamento']);
        }
        break;

    case 'cancel_subscription':
        // Cancella l'abbonamento di un utente
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        // Verifica che il parametro sia valido
        if ($userId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID utente non valido']);
            exit;
        }

        // Ottieni l'abbonamento attuale dell'utente
        $sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            echo json_encode(['status' => 'error', 'message' => 'Nessun abbonamento attivo trovato']);
            exit;
        }

        // Aggiorna lo stato dell'abbonamento
        $sql = "UPDATE subscriptions SET status = 'canceled', updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$subscription['id']]);

        if ($result) {
            // Registra l'azione
            $description = "Cancellato abbonamento dell'utente ID: $userId";
            logAdminAction($admin['id'], 'update', 'subscription', $userId, $description);

            echo json_encode(['status' => 'success', 'message' => 'Abbonamento cancellato con successo']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Errore durante la cancellazione dell\'abbonamento']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Azione non valida']);
}
