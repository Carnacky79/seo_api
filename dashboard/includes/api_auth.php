<?php
/**
 * Gestione dell'autenticazione API
 * Questo file gestisce la validazione delle chiavi API e il monitoraggio dell'utilizzo
 */

/**
 * Autentica la richiesta API tramite Bearer token
 *
 * @return array|false Dati utente se autenticato, false altrimenti
 */
function authenticateApiRequest() {
    // Ottieni l'header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    // Verifica che sia un Bearer token
    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        return false;
    }

    $apiKey = $matches[1];
    return getUserByApiKey($apiKey);
}

/**
 * Ottiene i dati dell'utente tramite chiave API
 *
 * @param string $apiKey Chiave API
 * @return array|false Dati utente se trovato, false altrimenti
 */
function getUserByApiKey($apiKey) {
    $pdo = getDbConnection();

    $sql = "SELECT * FROM users WHERE api_key = ? AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$apiKey]);

    return $stmt->fetch();
}

/**
 * Verifica che l'utente non abbia superato il limite di richieste
 *
 * @param int $userId ID dell'utente
 * @return bool True se l'utente può effettuare richieste, false altrimenti
 */
function checkRequestQuota($userId) {
    $pdo = getDbConnection();

    // Ottieni il piano dell'utente
    $sql = "SELECT plan_type FROM subscriptions 
            WHERE user_id = ? AND status = 'active' 
            AND current_period_end > NOW() 
            ORDER BY current_period_end DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch();

    // Se non ha un abbonamento, usa il piano gratuito
    $planType = $subscription['plan_type'] ?? 'free';

    // Imposta il limite in base al piano
    $requestLimit = 0;
    switch ($planType) {
        case 'free':
            $requestLimit = 10;
            break;
        case 'pro':
            $requestLimit = 1000;
            break;
        case 'premium':
            // Illimitato
            return true;
        default:
            $requestLimit = 10; // Fallback al piano gratuito
    }

    // Verifica il numero di richieste effettuate nel mese corrente
    $currentMonth = date('n');
    $currentYear = date('Y');

    $sql = "SELECT request_count FROM api_usage 
            WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $currentMonth, $currentYear]);
    $usage = $stmt->fetch();

    // Se non ci sono dati di utilizzo, crea un nuovo record
    if (!$usage) {
        $sql = "INSERT INTO api_usage (user_id, month, year, request_count) 
                VALUES (?, ?, ?, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $currentMonth, $currentYear]);

        return true; // L'utente non ha ancora effettuato richieste questo mese
    }

    // Verifica che l'utente non abbia superato il limite
    return (int)$usage['request_count'] < $requestLimit;
}

/**
 * Registra una nuova richiesta API
 *
 * @param int $userId ID dell'utente
 * @param string $url URL richiesto
 * @param int $responseCode Codice di risposta HTTP
 * @param float $executionTime Tempo di esecuzione in secondi
 * @return bool True se registrato con successo, false altrimenti
 */
function logApiRequest($userId, $url, $responseCode, $executionTime) {
    $pdo = getDbConnection();

    // Aggiorna il conteggio delle richieste per il mese corrente
    $currentMonth = date('n');
    $currentYear = date('Y');

    // Inizia una transazione
    $pdo->beginTransaction();

    try {
        // Verifica se esiste già un record per questo mese
        $sql = "SELECT id FROM api_usage 
                WHERE user_id = ? AND month = ? AND year = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $currentMonth, $currentYear]);
        $usage = $stmt->fetch();

        if ($usage) {
            // Aggiorna il conteggio
            $sql = "UPDATE api_usage 
                    SET request_count = request_count + 1 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usage['id']]);
        } else {
            // Crea un nuovo record
            $sql = "INSERT INTO api_usage (user_id, month, year, request_count) 
                    VALUES (?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $currentMonth, $currentYear]);
        }

        // Registra i dettagli della richiesta
        $sql = "INSERT INTO request_logs (user_id, url, response_code, execution_time) 
                VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $url, $responseCode, $executionTime]);

        // Commit della transazione
        $pdo->commit();

        return true;
    } catch (\Exception $e) {
        // Rollback in caso di errore
        $pdo->rollBack();
        error_log('Errore durante la registrazione della richiesta API: ' . $e->getMessage());

        return false;
    }
}

/**
 * Invia una risposta JSON con errore
 *
 * @param string $message Messaggio di errore
 * @param string $errorCode Codice di errore
 * @param int $httpCode Codice HTTP (default: 400)
 */
function sendApiError($message, $errorCode, $httpCode = 400) {
    http_response_code($httpCode);

    $response = [
        'status' => 'error',
        'error' => $errorCode,
        'message' => $message
    ];

    echo json_encode($response);
    exit;
}

/**
 * Invia una risposta JSON di successo
 *
 * @param array $data Dati da inviare
 * @param int $httpCode Codice HTTP (default: 200)
 */
function sendApiResponse($data, $httpCode = 200) {
    http_response_code($httpCode);

    $response = array_merge(['status' => 'success'], $data);

    echo json_encode($response);
    exit;
}
