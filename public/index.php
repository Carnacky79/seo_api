<?php

// Abilita la visualizzazione degli errori in fase di sviluppo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carica l'autoloader di Composer
require __DIR__ . '/../vendor/autoload.php';

// Imposta il Content-Type predefinito
header('Content-Type: application/json; charset=utf-8');

// Gestisci CORS per le richieste API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Gestisci le richieste OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Analizza l'URL per determinare il percorso richiesto
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api'; // Base path dell'API

// Rimuovi la query string, se presente
if (($pos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $pos);
}

// Rimuovi il base path dall'URI
$path = str_replace($basePath, '', $requestUri);
$path = trim($path, '/');

// Gestisci le rotte
try {
    switch ($path) {
        case 'metadata/generate':
            // Endpoint per generare metadati SEO
            $controller = new \SeoMetadataApi\Api\MetadataController();
            $controller->generateMetadata();
            break;

        case 'user/register':
            // Endpoint per la registrazione utente
            $controller = new \SeoMetadataApi\Api\UserController();
            $controller->register();
            break;

        case 'user/login':
            // Endpoint per il login utente
            $controller = new \SeoMetadataApi\Api\UserController();
            $controller->login();
            break;

        case 'user/profile':
            // Endpoint per ottenere/aggiornare il profilo utente
            $controller = new \SeoMetadataApi\Api\UserController();
            $controller->profile();
            break;

        case 'user/verify':
            // Endpoint per verificare l'email
            $controller = new \SeoMetadataApi\Api\UserController();
            $controller->verifyEmail();
            break;

        case 'user/resend-verification':
            // Endpoint per reinviare l'email di verifica
            $controller = new \SeoMetadataApi\Api\UserController();
            $controller->resendVerificationEmail();
            break;

        case 'subscription/plans':
            // Endpoint per ottenere i piani disponibili
            $controller = new \SeoMetadataApi\Api\SubscriptionController();
            $controller->getPlans();
            break;

        case 'subscription/checkout':
            // Endpoint per creare una sessione di checkout
            $controller = new \SeoMetadataApi\Api\SubscriptionController();
            $controller->createCheckout();
            break;

        case 'subscription/status':
            // Endpoint per ottenere lo stato dell'abbonamento
            $controller = new \SeoMetadataApi\Api\SubscriptionController();
            $controller->getStatus();
            break;

        case 'usage/stats':
            // Endpoint per ottenere le statistiche di utilizzo
            $controller = new \SeoMetadataApi\Api\UsageController();
            $controller->getStats();
            break;

        case '':
        case 'docs':
            // Redirect alla documentazione API
            header('Location: /docs/index.html');
            exit;

        default:
            // Rotta non trovata
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint non trovato: ' . $path
            ]);
    }
} catch (\Exception $e) {
    // Gestione degli errori generici
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
