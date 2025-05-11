<?php

namespace SeoMetadataApi\Subscription;

use SeoMetadataApi\Config\Stripe;
use SeoMetadataApi\Auth\UserManager;

class StripeManager {
    private $stripeConfig;
    private $planManager;
    private $userManager;

    public function __construct() {
        $this->stripeConfig = Stripe::getInstance();
        $this->planManager = new PlanManager();
        $this->userManager = new UserManager();
    }

    /**
     * Gestisce gli eventi webhook di Stripe
     */
    public function handleWebhookEvent($payload, $sigHeader) {
        $endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
            http_response_code(400);
            exit();
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            exit();
        }

        // Gestisci i diversi tipi di eventi
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;

            case 'invoice.paid':
                $this->handleInvoicePaid($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionCanceled($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;
        }

        return true;
    }

    /**
     * Gestisce il checkout completato
     */
    private function handleCheckoutCompleted($session) {
        $userId = $session->metadata->user_id;
        $planType = $session->metadata->plan_type;

        // Recupera il cliente e l'abbonamento da Stripe
        $stripeCustomerId = $session->customer;
        $stripeSubscriptionId = $session->subscription;

        // Aggiorna l'abbonamento nel database
        $this->planManager->upgradeToPaidPlan(
            $userId,
            $planType,
            $stripeCustomerId,
            $stripeSubscriptionId
        );
    }

    /**
     * Gestisce l'evento di fattura pagata (rinnovo abbonamento)
     */
    private function handleInvoicePaid($invoice) {
        $subscription = $this->stripeConfig->getClient()->subscriptions->retrieve(
            $invoice->subscription
        );

        // Aggiorna le date del periodo di abbonamento
        $this->planManager->updateSubscriptionPeriod(
            $invoice->subscription,
            date('Y-m-d H:i:s', $subscription->current_period_start),
            date('Y-m-d H:i:s', $subscription->current_period_end)
        );
    }

    /**
     * Gestisce il fallimento del pagamento
     */
    private function handlePaymentFailed($invoice) {
        // Trova l'utente associato all'abbonamento
        $sql = "SELECT user_id FROM subscriptions WHERE stripe_subscription_id = ?";
        $stmt = $this->db->query($sql, [$invoice->subscription]);
        $subscription = $stmt->fetch();

        if ($subscription) {
            // Imposta lo stato dell'abbonamento su "past_due"
            $this->planManager->updateSubscriptionStatus(
                $subscription['user_id'],
                $invoice->subscription,
                'past_due'
            );
        }
    }

    /**
     * Gestisce la cancellazione dell'abbonamento
     */
    private function handleSubscriptionCanceled($subscription) {
        // Trova l'utente associato all'abbonamento
        $sql = "SELECT user_id FROM subscriptions WHERE stripe_subscription_id = ?";
        $stmt = $this->db->query($sql, [$subscription->id]);
        $result = $stmt->fetch();

        if ($result) {
            // Imposta lo stato dell'abbonamento su "canceled"
            $this->planManager->updateSubscriptionStatus(
                $result['user_id'],
                $subscription->id,
                'canceled'
            );

            // Crea un nuovo abbonamento gratuito che diventerà attivo dopo la fine del periodo corrente
            $this->planManager->createFreeSubscription($result['user_id']);
        }
    }

    /**
     * Gestisce l'aggiornamento dell'abbonamento
     */
    private function handleSubscriptionUpdated($subscription) {
        // Trova l'utente associato all'abbonamento
        $sql = "SELECT user_id FROM subscriptions WHERE stripe_subscription_id = ?";
        $stmt = $this->db->query($sql, [$subscription->id]);
        $result = $stmt->fetch();

        if ($result) {
            // Aggiorna le date del periodo
            $this->planManager->updateSubscriptionPeriod(
                $subscription->id,
                date('Y-m-d H:i:s', $subscription->current_period_start),
                date('Y-m-d H:i:s', $subscription->current_period_end)
            );
        }
    }
}
