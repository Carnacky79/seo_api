<?php

namespace SeoMetadataApi\Config;

class Stripe {
    private static $instance = null;
    private $stripe;

    // Definizione dei piani di abbonamento
    const PLANS = [
        'free' => [
            'name' => 'Gratuito',
            'price' => 0,
            'requests_limit' => 10,
            'stripe_price_id' => null // Piano gratuito, non ha un ID Stripe
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 20,
            'requests_limit' => 1000,
            'stripe_price_id' => 'price_xxxxxxxxxxxxxxx' // Da sostituire con l'ID reale di Stripe
        ],
        'premium' => [
            'name' => 'Premium',
            'price' => 50,
            'requests_limit' => PHP_INT_MAX, // Illimitato
            'stripe_price_id' => 'price_yyyyyyyyyyyyyyy' // Da sostituire con l'ID reale di Stripe
        ]
    ];

    private function __construct() {
        // Carica le variabili d'ambiente da .env
        $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();

        // Inizializzazione della libreria Stripe
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $this->stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY']);
    }

    // Pattern Singleton
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Restituisce il client Stripe
    public function getClient() {
        return $this->stripe;
    }

    // Restituisce i dettagli di un piano
    public static function getPlan($planType) {
        if (!isset(self::PLANS[$planType])) {
            throw new \Exception("Piano non valido: {$planType}");
        }
        return self::PLANS[$planType];
    }

    // Restituisce tutti i piani disponibili
    public static function getAllPlans() {
        return self::PLANS;
    }

    // Genera l'URL per il checkout di Stripe
    public function createCheckoutSession($userId, $planType, $customerEmail) {
        if (!isset(self::PLANS[$planType]) || $planType === 'free') {
            throw new \Exception("Piano non valido per il checkout: {$planType}");
        }

        $plan = self::PLANS[$planType];

        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'customer_email' => $customerEmail,
            'line_items' => [[
                'price' => $plan['stripe_price_id'],
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $_ENV['APP_URL'] . '/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $_ENV['APP_URL'] . '/payment/cancel',
            'metadata' => [
                'user_id' => $userId,
                'plan_type' => $planType
            ]
        ]);

        return $session->url;
    }
}
