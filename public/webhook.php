<?php

// Carica l'autoloader di Composer
require __DIR__ . '/../vendor/autoload.php';

// Verifica il metodo della richiesta
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Ottieni il payload della richiesta
$input = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    // Carica le variabili d'ambiente
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();

    // Inizializza il gestore dei webhook di Stripe
    $stripeManager = new \SeoMetadataApi\Subscription\StripeManager();

    // Elabora l'evento webhook
    $stripeManager->handleWebhookEvent($input, $signatureHeader);

    // Risposta di successo
    echo json_encode(['status' => 'success']);
} catch (\Exception $e) {
    // Log dell'errore (in un'applicazione reale, utilizzare un logger appropriato)
    error_log('Errore webhook Stripe: ' . $e->getMessage());

    // Risposta di errore
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
