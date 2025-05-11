<?php

namespace SeoMetadataApi\Api;

use SeoMetadataApi\Auth\ApiKeyManager;
use SeoMetadataApi\Subscription\UsageTracker;
use SeoMetadataApi\Subscription\PlanManager;

class UsageController extends BaseController {
    private $apiKeyManager;
    private $usageTracker;
    private $planManager;

    public function __construct() {
        $this->apiKeyManager = new ApiKeyManager();
        $this->usageTracker = new UsageTracker();
        $this->planManager = new PlanManager();
    }

    /**
     * Ottiene le statistiche di utilizzo dell'API
     */
    public function getStats() {
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

        // Ottieni l'utilizzo corrente
        $currentUsage = $this->usageTracker->getCurrentMonthUsage($user['id']);

        // Ottieni la cronologia di utilizzo
        $usageHistory = $this->usageTracker->getUserUsageStats($user['id']);

        // Ottieni i dettagli dell'abbonamento
        $subscription = $this->planManager->getUserSubscription($user['id']);

        if (!$subscription) {
            $this->errorResponse('Nessun abbonamento attivo trovato');
            return;
        }

        // Ottieni i dettagli del piano
        $planDetails = null;
        $requestsRemaining = null;

        if (isset($subscription['plan_details'])) {
            $planDetails = $subscription['plan_details'];

            // Calcola le richieste rimanenti (tranne per il piano premium illimitato)
            if ($subscription['plan_type'] !== 'premium') {
                $requestsRemaining = max(0, $planDetails['requests_limit'] - $currentUsage);
            }
        }

        // Formatta la cronologia di utilizzo per mese
        $formattedHistory = [];
        foreach ($usageHistory as $usage) {
            $monthName = date('F Y', strtotime($usage['year'] . '-' . $usage['month'] . '-01'));
            $formattedHistory[] = [
                'month' => $monthName,
                'requests' => (int) $usage['request_count']
            ];
        }

        // Invia la risposta
        $this->jsonResponse([
            'status' => 'success',
            'current_month' => [
                'requests_used' => $currentUsage,
                'requests_limit' => $subscription['plan_type'] === 'premium' ? 'Illimitato' : $planDetails['requests_limit'],
                'requests_remaining' => $subscription['plan_type'] === 'premium' ? 'Illimitato' : $requestsRemaining,
                'percentage_used' => $subscription['plan_type'] === 'premium' ? 0 : min(100, round(($currentUsage / $planDetails['requests_limit']) * 100, 1))
            ],
            'plan' => [
                'type' => $subscription['plan_type'],
                'name' => $planDetails['name'] ?? $subscription['plan_type'],
                'price' => $planDetails['price'] ?? 0
            ],
            'subscription' => [
                'status' => $subscription['status'],
                'current_period_start' => $subscription['current_period_start'],
                'current_period_end' => $subscription['current_period_end']
            ],
            'history' => $formattedHistory
        ]);
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
