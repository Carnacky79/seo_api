<?php
/**
 * API per annullare abbonamenti
 *
 * Questo script gestisce l'annullamento degli abbonamenti utente
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php'; // Per Stripe PHP SDK

// Verifica che l'utente sia autenticato
requireLogin();

// Imposta la risposta come JSON
header('Content-Type: application/json');

// Verifica che la richiesta sia di tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito']);
    exit;
}

// Verifica l'azione richiesta
$action = $_POST['action'] ?? '';
if ($action !== 'cancel') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Azione non valida']);
    exit;
}

// Ottieni l'ID dell'abbonamento da annullare
$subscriptionId = $_POST['subscription_id'] ?? '';
if (empty($subscriptionId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID abbonamento mancante']);
    exit;
}

// Ottieni il database
$pdo = getDbConnection();

// Verifica che l'abbonamento appartenga all'utente corrente
$sql = "SELECT * FROM subscriptions WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$subscriptionId, $_SESSION['user_id']]);
$subscription = $stmt->fetch();

if (!$subscription) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Abbonamento non trovato o non autorizzato']);
    exit;
}

// Verifica che l'abbonamento sia attivo
if ($subscription['status'] !== 'active') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'L\'abbonamento non è attivo']);
    exit;
}

// Verifica che ci sia un ID abbonamento Stripe
if (empty($subscription['stripe_subscription_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID abbonamento Stripe mancante']);
    exit;
}

try {
    // Configura Stripe
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    // Ottieni l'abbonamento Stripe
    $stripeSubscription = \Stripe\Subscription::retrieve($subscription['stripe_subscription_id']);

    // Annulla l'abbonamento
    $stripeSubscription->cancel();

    // Aggiorna lo stato dell'abbonamento nel database
    $sql = "UPDATE subscriptions SET status = 'canceled', updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subscriptionId]);

    // Crea un nuovo abbonamento gratuito per l'utente
    createFreeSubscription($_SESSION['user_id']);

    // Registra l'evento nel log
    $sql = "INSERT INTO event_logs (event_type, event_data, created_at) VALUES (?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $data = json_encode([
        'subscription_id' => $subscription['id'],
        'stripe_subscription_id' => $subscription['stripe_subscription_id'],
        'user_id' => $_SESSION['user_id'],
        'action' => 'manual_cancel'
    ]);
    $stmt->execute(['subscription.manual_cancel', $data]);

    // Restituisci una risposta di successo
    echo json_encode([
        'status' => 'success',
        'message' => 'Abbonamento annullato con successo. Il tuo abbonamento rimarrà attivo fino alla fine del periodo di fatturazione corrente.'
    ]);

} catch (\Exception $e) {
    // Gestisci gli errori
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Errore durante l\'annullamento dell\'abbonamento: ' . $e->getMessage()
    ]);

    // Registra l'errore nel log
    error_log('Errore nell\'annullamento dell\'abbonamento: ' . $e->getMessage());
}
/**
 * API per annullare abbonamenti
 *
 * Questo script gestisce l'annullamento degli abbonamenti utente
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php'; // Per Stripe PHP SDK

// Verifica che l'utente sia autenticato
requireLogin();

// Imposta la risposta come JSON
header('Content-Type: application/json');

// Verifica che la richiesta sia di tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito']);
    exit;
}

// Verifica l'azione richiesta
$action = $_POST['action'] ?? '';
if ($action !== 'cancel') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Azione non valida']);
    exit;
}

// Ottieni l'ID dell'abbonamento da annullare
$subscriptionId = $_POST['subscription_id'] ?? '';
if (empty($subscriptionId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID abbonamento mancante']);
    exit;
}

// Ottieni il database
$pdo = getDbConnection();

// Verifica che l'abbonamento appartenga all'utente corrente
$sql = "SELECT * FROM subscriptions WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$subscriptionId, $_SESSION['user_id']]);
$subscription = $stmt->fetch();

if (!$subscription) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Abbonamento non trovato o non autorizzato']);
    exit;
}

// Verifica che l'abbonamento sia attivo
if ($subscription['status'] !== 'active') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'L\'abbonamento non è attivo']);
    exit;
}

// Verifica che ci sia un ID abbonamento Stripe
if (empty($subscription['stripe_subscription_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID abbonamento Stripe mancante']);
    exit;
}

try {
    // Configura Stripe
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    // Ottieni l'abbonamento Stripe
    $stripeSubscription = \Stripe\Subscription::retrieve($subscription['stripe_subscription_id']);

    // Annulla l'abbonamento
    $stripeSubscription->cancel();

    // Aggiorna lo stato dell'abbonamento nel database
    $sql = "UPDATE subscriptions SET status = 'canceled', updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subscriptionId]);

    // Crea un nuovo abbonamento gratuito per l'utente
    createFreeSubscription($_SESSION['user_id']);

    // Registra l'evento nel log
    $sql = "INSERT INTO event_logs (event_type, event_data, created_at) VALUES (?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $data = json_encode([
        'subscription_id' => $subscription['id'],
        'stripe_subscription_id' => $subscription['stripe_subscription_id'],
        'user_id' => $_SESSION['user_id'],
        'action' => 'manual_cancel'
    ]);
    $stmt->execute(['subscription.manual_cancel', $data]);

    // Restituisci una risposta di successo
    echo json_encode([
        'status' => 'success',
        'message' => 'Abbonamento annullato con successo. Il tuo abbonamento rimarrà attivo fino alla fine del periodo di fatturazione corrente.'
    ]);

} catch (\Exception $e) {
    // Gestisci gli errori
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Errore durante l\'annullamento dell\'abbonamento: ' . $e->getMessage()
    ]);

    // Registra l'errore nel log
    error_log('Errore nell\'annullamento dell\'abbonamento: ' . $e->getMessage());
}
