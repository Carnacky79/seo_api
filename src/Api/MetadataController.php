<?php

namespace SeoMetadataApi\Api;

use SeoMetadataApi\Auth\ApiKeyManager;
use SeoMetadataApi\Metadata\MetadataGenerator;
use SeoMetadataApi\Subscription\UsageTracker;

class MetadataController extends BaseController {
    private $apiKeyManager;
    private $metadataGenerator;
    private $usageTracker;

    public function __construct() {
        $this->apiKeyManager = new ApiKeyManager();
        $this->metadataGenerator = new MetadataGenerator();
        $this->usageTracker = new UsageTracker();
    }

    /**
     * Gestisce la richiesta per generare metadati SEO
     */
    public function generateMetadata() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('POST')) {
            return;
        }

        // Ottieni i dati della richiesta
        $data = $this->getJsonInput();

        // Verifica i parametri richiesti
        if (!$this->validateRequiredParams($data, ['url'])) {
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

        // Elabora l'URL
        $url = filter_var($data['url'], FILTER_SANITIZE_URL);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->errorResponse('URL non valido', 400);
            return;
        }

        // Registra l'inizio dell'esecuzione per calcolare il tempo
        $startTime = microtime(true);

        try {
            // Genera i metadati SEO
            $metadata = $this->metadataGenerator->generateMetadata($url);

            // Calcola il tempo di esecuzione
            $executionTime = microtime(true) - $startTime;

            // Registra la richiesta API
            $this->apiKeyManager->logApiRequest($user['id'], $url, 200, $executionTime);

            // Ottieni le statistiche di utilizzo per il mese corrente
            $currentUsage = $this->usageTracker->getCurrentMonthUsage($user['id']);

            // Aggiungi le statistiche di utilizzo alla risposta
            $metadata['usage_stats'] = [
                'current_month_requests' => $currentUsage,
                'execution_time' => round($executionTime, 2) . ' sec'
            ];

            // Invia la risposta
            $this->jsonResponse($metadata);
        } catch (\Exception $e) {
            // Registra l'errore
            $executionTime = microtime(true) - $startTime;
            $this->apiKeyManager->logApiRequest($user['id'], $url, 500, $executionTime);

            // Invia la risposta di errore
            $this->errorResponse('Errore durante la generazione dei metadati: ' . $e->getMessage(), 500);
        }
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
