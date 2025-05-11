<?php

namespace SeoMetadataApi\Subscription;

use SeoMetadataApi\Config\Database;
use SeoMetadataApi\Config\Stripe;

class UsageTracker {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Verifica se un utente può effettuare una richiesta in base al suo piano
     *
     * @param int $userId ID dell'utente
     * @param string $planType Tipo di piano ('free', 'pro', 'premium')
     * @return bool True se l'utente può effettuare una richiesta, altrimenti False
     */
    public function canMakeRequest($userId, $planType) {
        // Per il piano premium (illimitato), consenti sempre la richiesta
        if ($planType === 'premium') {
            return true;
        }

        // Ottieni il limite mensile per il piano
        try {
            $plan = Stripe::getPlan($planType);
            $monthlyLimit = $plan['requests_limit'];
        } catch (\Exception $e) {
            // Piano non riconosciuto, usiamo il limite più basso
            $monthlyLimit = 10;
        }

        // Ottieni il conteggio attuale per il mese corrente
        $currentCount = $this->getCurrentMonthUsage($userId);

        // Verifica se l'utente ha superato il limite
        return $currentCount < $monthlyLimit;
    }

    /**
     * Ottiene il numero di richieste effettuate nel mese corrente
     *
     * @param int $userId ID dell'utente
     * @return int Numero di richieste effettuate
     */
    public function getCurrentMonthUsage($userId) {
        $currentMonth = date('n'); // 1-12
        $currentYear = date('Y');

        $sql = "SELECT request_count FROM api_usage 
                WHERE user_id = ? AND month = ? AND year = ?";

        $stmt = $this->db->query($sql, [$userId, $currentMonth, $currentYear]);
        $usage = $stmt->fetch();

        if ($usage) {
            return (int) $usage['request_count'];
        }

        // Nessun utilizzo registrato per questo mese
        return 0;
    }

    /**
     * Incrementa il contatore delle richieste per il mese corrente
     *
     * @param int $userId ID dell'utente
     * @return bool True se l'operazione è riuscita
     */
    public function incrementRequestCount($userId) {
        $currentMonth = date('n');
        $currentYear = date('Y');

        // Verifica se esiste già un record per questo mese
        $sql = "SELECT id, request_count FROM api_usage 
                WHERE user_id = ? AND month = ? AND year = ?";

        $stmt = $this->db->query($sql, [$userId, $currentMonth, $currentYear]);
        $usage = $stmt->fetch();

        if ($usage) {
            // Aggiorna il record esistente
            $newCount = $usage['request_count'] + 1;
            $sql = "UPDATE api_usage SET request_count = ? WHERE id = ?";
            $this->db->query($sql, [$newCount, $usage['id']]);
        } else {
            // Crea un nuovo record
            $sql = "INSERT INTO api_usage (user_id, month, year, request_count) 
                    VALUES (?, ?, ?, 1)";
            $this->db->query($sql, [$userId, $currentMonth, $currentYear]);
        }

        return true;
    }

    /**
     * Ottiene le statistiche di utilizzo per tutti i mesi
     *
     * @param int $userId ID dell'utente
     * @return array Dati di utilizzo
     */
    public function getUserUsageStats($userId) {
        $sql = "SELECT month, year, request_count FROM api_usage 
                WHERE user_id = ? ORDER BY year DESC, month DESC";

        $stmt = $this->db->query($sql, [$userId]);
        return $stmt->fetchAll();
    }
}
