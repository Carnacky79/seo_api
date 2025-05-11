<?php

namespace SeoMetadataApi\Api;

use SeoMetadataApi\Auth\ApiKeyManager;
use SeoMetadataApi\Auth\UserManager;
use SeoMetadataApi\Subscription\PlanManager;
use SeoMetadataApi\Subscription\UsageTracker;

class SubscriptionController extends BaseController {
    private $apiKeyManager;
    private $planManager;
    private $userManager;
    private $usageTracker;

    public function __construct() {
        $this->apiKeyManager = new ApiKeyManager();
        $this->planManager = new PlanManager();
        $this->userManager = new UserManager();
        $this->usageTracker = new UsageTracker();
    }

    /**
     * Ottiene i piani di abbonamento disponibili
     */
    public function getPlans() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('GET')) {
            return;
        }

        // Ottieni tutti i piani disponibili
        $plans = $this->planManager->getAllPlans();

        // Formatta la risposta
        $response = [
            'status' => 'success',
            'plans' => array_values($plans) // Converti in array numerato per JSON
        ];

        $this->jsonResponse($response);
    }

    /**
     * Crea una sessione di checkout per l'abbonamento
     */
    public function createCheckout() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('POST')) {
            return;
        }

        // Ottieni i dati della richiesta
        $data = $this->getJsonInput();

        // Verifica i parametri richiesti
        if (!$this->validateRequiredParams($data, ['plan_type'])) {
            return;
        }

        // Ottieni l'API key dall'header
        $apiKey = $this->getApiKeyFromHeader();

        // Verifica la validità dell'API key
        try {
            $user = $this->apiKeyManager->validateApiKey($apiKey);

            if (!$user) {
                $this->errorResponse('API key non valida', 401);
                return;
            }
        } catch (\Exception $e) {
            $this->errorResponse($e->getMessage(), 403);
            return;
        }

        // Ottieni il tipo di piano
        $planType = $data['plan_type'];

        // Verifica che il piano sia valido
        if (!in_array($planType, ['pro', 'premium'])) {
            $this->errorResponse('Piano non valido. Scegli tra: pro, premium');
            return;
        }

        try {
            // Ottieni l'email dell'utente
            $userInfo = $this->userManager->getUserById($user['id']);

            // Crea la sessione di checkout
            $checkoutUrl = $this->planManager->createCheckoutSession(
                $user['id'],
                $planType,
                $userInfo['email']
            );

            // Invia l'URL di checkout
            $this->jsonResponse([
                'status' => 'success',
                'checkout_url' => $checkoutUrl
            ]);
        } catch (\Exception $e) {
            $this->errorResponse('Errore durante la creazione della sessione di checkout: ' . $e->getMessage());
        }
    }

    /**
     * Ottiene lo stato dell'abbonamento corrente
     */
    public function getStatus() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('GET')) {
            return;
        }

        // Ottieni l'API key dall'header
        $apiKey = $this->getApiKeyFromHeader();

        // Verifica la validità dell'API key
        try {
            $user = $this->apiKeyManager->validateApiKey($apiKey);

            if (!$user) {
                $this->errorResponse('API key non valida', 401);
                return;
            }
        } catch (\Exception $e) {
            $this->errorResponse($e->getMessage(), 403);
            return;
        }

        // Ottieni i dettagli dell'abbonamento
        $subscription = $this->planManager->getUserSubscription($user['id']);

        if (!$subscription) {
            $this->errorResponse('Nessun abbonamento attivo trovato');
            return;
        }

        // Ottieni le statistiche di utilizzo
        $currentUsage = $this->usageTracker->getCurrentMonthUsage($user['id']);
        $usageStats = $this->usageTracker->getUserUsageStats($user['id']);

        // Ottieni i dettagli del piano
        $planDetails = null;
        if (isset($subscription['plan_details'])) {
            $planDetails = $subscription['plan_details'];
        }

        // Calcola le richieste rimanenti
        $requestsRemaining = null;
        if ($planDetails && $subscription['plan_type'] !== 'premium') {
            $requestsRemaining = max(0, $planDetails['requests_limit'] - $currentUsage);
        }

        // Formatta la risposta
        $response = [
            'status' => 'success',
            'subscription' => [
                'plan_type' => $subscription['plan_type'],
                'status' => $subscription['status'],
                'current_period_start' => $subscription['current_period_start'],
                'current_period_end' => $subscription['current_period_end'],
                'plan_details' => $planDetails
            ],
            'usage' => [
                'current_month' => $currentUsage,
                'requests_remaining' => $requestsRemaining,
                'is_unlimited' => ($subscription['plan_type'] === 'premium'),
                'history' => $usageStats
            ]
        ];

        $this->jsonResponse($response);
    }

    /**
     * Ottiene la chiave API dall'header della richiesta
     *
     * @return string|null
     */
    private function getApiKeyFromHeader() {
        // Cerca prima nell'header Authorization
        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];

            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // Cerca nell'header X-API-Key
        if (isset($headers['X-API-Key'])) {
            return $headers['X-API-Key'];
        }

        // Cerca nel parametro api_key della query string
        if (isset($_GET['api_key'])) {
            return $_GET['api_key'];
        }

        return null;
    }
}
