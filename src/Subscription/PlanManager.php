<?php

namespace SeoMetadataApi\Subscription;

use SeoMetadataApi\Config\Database;
use SeoMetadataApi\Config\Stripe;

class PlanManager {
    private $db;
    private $stripeConfig;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->stripeConfig = Stripe::getInstance();
    }

    /**
     * Crea un abbonamento gratuito per un nuovo utente
     */
    public function createFreeSubscription($userId) {
        $currentDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));

        $sql = "INSERT INTO subscriptions (user_id, plan_type, status, current_period_start, current_period_end) 
                VALUES (?, 'free', 'active', ?, ?)";

        $this->db->query($sql, [$userId, $currentDate, $endDate]);
        return true;
    }

    /**
     * Ottiene i dettagli dell'abbonamento attivo di un utente
     */
    public function getUserSubscription($userId) {
        $sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'";
        $stmt = $this->db->query($sql, [$userId]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            return false;
        }

        // Aggiungi i dettagli del piano
        try {
            $planDetails = Stripe::getPlan($subscription['plan_type']);
            $subscription['plan_details'] = $planDetails;
        } catch (\Exception $e) {
            $subscription['plan_details'] = null;
        }

        return $subscription;
    }

    /**
     * Aggiorna lo stato dell'abbonamento
     */
    public function updateSubscriptionStatus($userId, $stripeSubscriptionId, $status) {
        $sql = "UPDATE subscriptions SET status = ? WHERE user_id = ? AND stripe_subscription_id = ?";
        $this->db->query($sql, [$status, $userId, $stripeSubscriptionId]);
        return true;
    }

    /**
     * Gestisce l'aggiornamento a un piano a pagamento
     */
    public function upgradeToPaidPlan($userId, $planType, $stripeCustomerId, $stripeSubscriptionId) {
        // Verifica che il piano sia valido
        if (!in_array($planType, ['pro', 'premium'])) {
            throw new \Exception("Piano non valido");
        }

        // Ottieni l'abbonamento corrente
        $sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'";
        $stmt = $this->db->query($sql, [$userId]);
        $currentSubscription = $stmt->fetch();

        $currentDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));

        if ($currentSubscription) {
            // Aggiorna l'abbonamento esistente
            $sql = "UPDATE subscriptions 
                    SET plan_type = ?, 
                        stripe_customer_id = ?, 
                        stripe_subscription_id = ?,
                        status = 'active',
                        current_period_start = ?,
                        current_period_end = ?
                    WHERE id = ?";

            $this->db->query($sql, [
                $planType,
                $stripeCustomerId,
                $stripeSubscriptionId,
                $currentDate,
                $endDate,
                $currentSubscription['id']
            ]);
        } else {
            // Crea un nuovo abbonamento
            $sql = "INSERT INTO subscriptions 
                    (user_id, plan_type, stripe_customer_id, stripe_subscription_id, status, current_period_start, current_period_end) 
                    VALUES (?, ?, ?, ?, 'active', ?, ?)";

            $this->db->query($sql, [
                $userId,
                $planType,
                $stripeCustomerId,
                $stripeSubscriptionId,
                $currentDate,
                $endDate
            ]);
        }

        return true;
    }

    /**
     * Aggiorna le date del periodo di abbonamento
     */
    public function updateSubscriptionPeriod($stripeSubscriptionId, $startDate, $endDate) {
        $sql = "UPDATE subscriptions 
                SET current_period_start = ?, current_period_end = ? 
                WHERE stripe_subscription_id = ?";

        $this->db->query($sql, [$startDate, $endDate, $stripeSubscriptionId]);
        return true;
    }

    /**
     * Ottiene tutti i piani disponibili
     */
    public function getAllPlans() {
        return Stripe::getAllPlans();
    }

    /**
     * Genera un URL per il checkout di Stripe
     */
    public function createCheckoutSession($userId, $planType, $email) {
        return $this->stripeConfig->createCheckoutSession($userId, $planType, $email);
    }
}
