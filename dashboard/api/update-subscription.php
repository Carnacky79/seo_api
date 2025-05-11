<?php
/**
 * API per la gestione degli abbonamenti con Stripe
 *
 * Questo script gestisce l'aggiornamento degli abbonamenti utente
 * e il reindirizzamento al checkout di Stripe per i piani a pagamento.
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
if ($action !== 'update') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Azione non valida']);
    exit;
}

// Ottieni il piano selezionato
$planType = $_POST['plan_type'] ?? '';
if (!in_array($planType, ['free', 'pro', 'premium'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Piano non valido']);
    exit;
}

// Ottieni l'utente corrente
$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Utente non autorizzato']);
    exit;
}

// Ottieni l'abbonamento corrente
$currentSubscription = getCurrentSubscription();
$currentPlan = $currentSubscription ? $currentSubscription['plan_type'] : 'free';

// Se il piano selezionato è lo stesso di quello corrente, non fare nulla
if ($planType === $currentPlan) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Sei già abbonato a questo piano',
        'reload' => true
    ]);
    exit;
}

try {
    // Configura Stripe
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    // Se il piano selezionato è gratuito
    if ($planType === 'free') {
        // Se l'utente ha un abbonamento a pagamento attivo, annullalo
        if ($currentPlan !== 'free' && !empty($currentSubscription['stripe_subscription_id'])) {
            // Annulla l'abbonamento Stripe
            $subscription = \Stripe\Subscription::retrieve($currentSubscription['stripe_subscription_id']);
            $subscription->cancel();

            // Aggiorna lo stato dell'abbonamento nel database
            $pdo = getDbConnection();
            $sql = "UPDATE subscriptions SET status = 'canceled' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$currentSubscription['id']]);
        }

        // Crea un nuovo abbonamento gratuito
        createFreeSubscription($user['id']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Sei passato al piano Gratuito',
            'reload' => true
        ]);
        exit;
    }

    // Per piani a pagamento, crea una sessione di checkout Stripe
    $priceId = '';
    switch ($planType) {
        case 'pro':
            $priceId = STRIPE_PRICE_PRO;
            break;
        case 'premium':
            $priceId = STRIPE_PRICE_PREMIUM;
            break;
    }

    // Crea un cliente Stripe se l'utente non ne ha uno
    if (empty($user['stripe_customer_id'])) {
        $customer = \Stripe\Customer::create([
            'email' => $user['email'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'metadata' => [
                'user_id' => $user['id'],
                'fiscal_code' => $user['fiscal_code']
            ]
        ]);

        // Aggiorna l'ID cliente Stripe nel database
        $pdo = getDbConnection();
        $sql = "UPDATE users SET stripe_customer_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer->id, $user['id']]);
    } else {
        $customer = \Stripe\Customer::retrieve($user['stripe_customer_id']);
    }

    // Crea una sessione di checkout
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'customer' => $customer->id,
        'line_items' => [[
            'price' => $priceId,
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => getBaseUrl() . '/subscription-success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => getBaseUrl() . '/index.php?page=subscription&canceled=1',
        'client_reference_id' => $user['id'],
        'customer_email' => null, // Usiamo il cliente esistente
        'metadata' => [
            'user_id' => $user['id'],
            'plan_type' => $planType
        ],
        'subscription_data' => [
            'metadata' => [
                'user_id' => $user['id'],
                'plan_type' => $planType
            ]
        ],
        'allow_promotion_codes' => true,
        'billing_address_collection' => 'required',
        'locale' => 'it'
    ]);

    // Restituisci l'URL di checkout
    echo json_encode([
        'status' => 'success',
        'redirect' => $session->url
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Errore durante l\'aggiornamento dell\'abbonamento: ' . $e->getMessage()
    ]);
}
