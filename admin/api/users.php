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
    case 'change_status':
        // Modifica lo stato di un utente
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : '';

        // Verifica che i parametri siano validi
        if ($userId <= 0 || !in_array($status, ['active', 'inactive', 'suspended'])) {
            echo json_encode(['status' => 'error', 'message' => 'Parametri non validi']);
            exit;
        }

        // Aggiorna lo stato dell'utente
        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$status, $userId]);

        if ($result) {
            // Registra l'azione
            $description = "Modificato stato utente ID: $userId a '$status'";
            logAdminAction($admin['id'], 'update', 'user', $userId, $description);

            echo json_encode(['status' => 'success', 'message' => 'Stato utente aggiornato con successo']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Errore durante l\'aggiornamento dello stato']);
        }
        break;

    case 'verify_email':
        // Verifica manualmente l'email di un utente
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        // Verifica che il parametro sia valido
        if ($userId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID utente non valido']);
            exit;
        }

        // Aggiorna lo stato di verifica dell'email
        $sql = "UPDATE users SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$userId]);

        if ($result) {
            // Registra l'azione
            $description = "Verificata manualmente l'email dell'utente ID: $userId";
            logAdminAction($admin['id'], 'update', 'user', $userId, $description);

            echo json_encode(['status' => 'success', 'message' => 'Email verificata con successo']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Errore durante la verifica dell\'email']);
        }
        break;

    case 'regenerate_api_key':
        // Rigenera la chiave API di un utente
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        // Verifica che il parametro sia valido
        if ($userId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID utente non valido']);
            exit;
        }

        // Genera una nuova chiave API
        $newApiKey = bin2hex(random_bytes(32));

        // Aggiorna la chiave API dell'utente
        $sql = "UPDATE users SET api_key = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$newApiKey, $userId]);

        if ($result) {
            // Registra l'azione
            $description = "Rigenerata la chiave API dell'utente ID: $userId";
            logAdminAction($admin['id'], 'update', 'user', $userId, $description);

            echo json_encode([
                'status' => 'success',
                'message' => 'Chiave API rigenerata con successo',
                'api_key' => $newApiKey
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Errore durante la rigenerazione della chiave API']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Azione non valida']);
}
