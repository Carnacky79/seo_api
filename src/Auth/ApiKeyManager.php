<?php

namespace SeoMetadataApi\Auth;

use SeoMetadataApi\Config\Database;
use SeoMetadataApi\Subscription\UsageTracker;

class ApiKeyManager {
    private $db;
    private $userManager;
    private $usageTracker;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->userManager = new UserManager();
        $this->usageTracker = new UsageTracker();
    }

    /**
     * Valida una chiave API e verifica i limiti di utilizzo
     *
     * @param string $apiKey La chiave API da validare
     * @return array|false I dati dell'utente o false se la chiave non è valida
     */
    public function validateApiKey($apiKey) {
        // Verifica che la chiave API sia valida
        $user = $this->userManager->getUserByApiKey($apiKey);

        if (!$user) {
            return false;
        }

        // Verifica se l'email è stata verificata
        if ($user['email_verified'] == 0) {
            throw new \Exception("Email non verificata. Verifica la tua email prima di utilizzare l'API.");
        }

        // Verifica lo stato dell'account
        if ($user['status'] !== 'active') {
            throw new \Exception("Account " . $user['status'] . ". Contatta l'assistenza.");
        }

        // Ottieni informazioni sull'abbonamento dell'utente
        $sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'";
        $stmt = $this->db->query($sql, [$user['id']]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            throw new \Exception("Nessun abbonamento attivo trovato");
        }

        // Verifica i limiti di utilizzo
        $canMakeRequest = $this->usageTracker->canMakeRequest($user['id'], $subscription['plan_type']);

        if (!$canMakeRequest) {
            throw new \Exception("Limite di richieste mensili raggiunto per il tuo piano");
        }

        return $user;
    }

    /**
     * Registra una richiesta API effettuata
     *
     * @param int $userId ID dell'utente
     * @param string $url URL analizzato
     * @param int $responseCode Codice di risposta HTTP
     * @param float $executionTime Tempo di esecuzione in secondi
     * @return bool
     */
    public function logApiRequest($userId, $url, $responseCode, $executionTime) {
        // Registra la richiesta nel log
        $sql = "INSERT INTO request_logs (user_id, url, response_code, execution_time) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->query($sql, [$userId, $url, $responseCode, $executionTime]);

        // Aggiorna il contatore delle richieste per il mese corrente
        return $this->usageTracker->incrementRequestCount($userId);
    }
}
