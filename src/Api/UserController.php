<?php

namespace SeoMetadataApi\Api;

use SeoMetadataApi\Auth\ApiKeyManager;
use SeoMetadataApi\Auth\UserManager;
use SeoMetadataApi\Subscription\PlanManager;
use SeoMetadataApi\Subscription\UsageTracker;

class UserController extends BaseController {
    private $apiKeyManager;
    private $userManager;
    private $planManager;
    private $usageTracker;

    public function __construct() {
        $this->apiKeyManager = new ApiKeyManager();
        $this->userManager = new UserManager();
        $this->planManager = new PlanManager();
        $this->usageTracker = new UsageTracker();
    }

    /**
     * Gestisce la registrazione di un nuovo utente
     */
    public function register() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('POST')) {
            return;
        }

        // Ottieni i dati della richiesta
        $data = $this->getJsonInput();

        // Verifica i parametri richiesti
        if (!$this->validateRequiredParams($data, ['email', 'password', 'first_name', 'last_name', 'fiscal_code'])) {
            return;
        }

        // Valida email e password
        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->errorResponse('Indirizzo email non valido');
            return;
        }

        if (strlen($data['password']) < 8) {
            $this->errorResponse('La password deve contenere almeno 8 caratteri');
            return;
        }

        // Valida nome e cognome
        if (strlen($data['first_name']) < 2) {
            $this->errorResponse('Il nome deve contenere almeno 2 caratteri');
            return;
        }

        if (strlen($data['last_name']) < 2) {
            $this->errorResponse('Il cognome deve contenere almeno 2 caratteri');
            return;
        }

        // Prepara i campi opzionali
        $phone = isset($data['phone']) ? $data['phone'] : null;
        $company = isset($data['company']) ? $data['company'] : null;
        $vatNumber = isset($data['vat_number']) ? $data['vat_number'] : null;

        try {
            // Registra l'utente
            $user = $this->userManager->register(
                $email,
                $data['password'],
                $data['first_name'],
                $data['last_name'],
                $data['fiscal_code'],
                $phone,
                $company,
                $vatNumber
            );

            // Invia la risposta
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Registrazione completata con successo',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'api_key' => $user['api_key']
                ]
            ]);
        } catch (\Exception $e) {
            $this->errorResponse('Errore durante la registrazione: ' . $e->getMessage());
        }
    }

    /**
     * Gestisce il login di un utente
     */
    public function login() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('POST')) {
            return;
        }

        // Ottieni i dati della richiesta
        $data = $this->getJsonInput();

        // Verifica i parametri richiesti
        if (!$this->validateRequiredParams($data, ['email', 'password'])) {
            return;
        }

        try {
            // Autentica l'utente
            $user = $this->userManager->login($data['email'], $data['password']);

            // Verifica lo stato dell'utente
            $userDetails = $this->userManager->getUserById($user['id']);
            if ($userDetails['status'] !== 'active') {
                $this->errorResponse('Questo account è ' . $userDetails['status'] . '. Contatta l\'assistenza.', 403);
                return;
            }

            // Ottieni i dettagli dell'abbonamento
            $subscription = $this->planManager->getUserSubscription($user['id']);
            $planType = $subscription ? $subscription['plan_type'] : 'free';

            // Ottieni le statistiche di utilizzo
            $currentUsage = $this->usageTracker->getCurrentMonthUsage($user['id']);

            // Invia la risposta
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Login effettuato con successo',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $userDetails['first_name'],
                    'last_name' => $userDetails['last_name'],
                    'api_key' => $user['api_key'],
                    'plan_type' => $planType,
                    'current_usage' => $currentUsage
                ]
            ]);
        } catch (\Exception $e) {
            $this->errorResponse('Errore durante il login: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Gestisce la visualizzazione e l'aggiornamento del profilo utente
     */
    public function profile() {
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

        // Gestisci la richiesta in base al metodo
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                // Visualizza il profilo
                $this->getProfile($user['id']);
                break;

            case 'POST':
            case 'PUT':
                // Aggiorna il profilo
                $this->updateProfile($user['id']);
                break;

            default:
                $this->errorResponse('Metodo non consentito', 405);
        }
    }

    /**
     * Visualizza il profilo dell'utente
     */
    private function getProfile($userId) {
        // Ottieni i dati dell'utente
        $user = $this->userManager->getUserById($userId);

        // Ottieni i dettagli dell'abbonamento
        $subscription = $this->planManager->getUserSubscription($userId);

        // Ottieni le statistiche di utilizzo
        $currentUsage = $this->usageTracker->getCurrentMonthUsage($userId);
        $usageStats = $this->usageTracker->getUserUsageStats($userId);

        // Prepara i dati del profilo
        $profile = [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'fiscal_code' => $user['fiscal_code'],
            'phone' => $user['phone'],
            'company' => $user['company'],
            'vat_number' => $user['vat_number'],
            'email_verified' => (bool) $user['email_verified'],
            'status' => $user['status'],
            'api_key' => $user['api_key'],
            'created_at' => $user['created_at'],
            'subscription' => $subscription ? [
                'plan_type' => $subscription['plan_type'],
                'status' => $subscription['status'],
                'current_period_start' => $subscription['current_period_start'],
                'current_period_end' => $subscription['current_period_end']
            ] : null,
            'usage' => [
                'current_month' => $currentUsage,
                'history' => $usageStats
            ]
        ];

        // Invia la risposta
        $this->jsonResponse([
            'status' => 'success',
            'profile' => $profile
        ]);
    }

    /**
     * Aggiorna il profilo dell'utente
     */
    private function updateProfile($userId) {
        // Ottieni i dati della richiesta
        $data = $this->getJsonInput();

        // Gestisci la rigenerazione della chiave API
        if (isset($data['regenerate_api_key']) && $data['regenerate_api_key'] === true) {
            try {
                // Rigenera la chiave API
                $newApiKey = $this->userManager->regenerateApiKey($userId);

                // Invia la risposta
                $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Chiave API rigenerata con successo',
                    'api_key' => $newApiKey
                ]);
            } catch (\Exception $e) {
                $this->errorResponse('Errore durante la rigenerazione della chiave API: ' . $e->getMessage());
            }
            return;
        }

        // Gestisci il cambio password
        if (isset($data['current_password']) && isset($data['new_password'])) {
            try {
                // Ottieni l'utente corrente
                $user = $this->userManager->getUserById($userId);

                // Verifica la password corrente
                if (!password_verify($data['current_password'], $user['password'])) {
                    $this->errorResponse('La password corrente non è valida');
                    return;
                }

                // Verifica la nuova password
                if (strlen($data['new_password']) < 8) {
                    $this->errorResponse('La nuova password deve contenere almeno 8 caratteri');
                    return;
                }

                // Aggiorna la password
                $this->updateUserPassword($userId, $data['new_password']);

                // Invia la risposta
                $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Password aggiornata con successo'
                ]);
            } catch (\Exception $e) {
                $this->errorResponse('Errore durante l\'aggiornamento della password: ' . $e->getMessage());
            }
            return;
        }

        // Aggiorna i dati del profilo
        $updatableFields = ['phone', 'company', 'vat_number'];
        $updates = [];
        $params = [];

        foreach ($updatableFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            $this->errorResponse('Nessun campo valido da aggiornare. I campi aggiornabili sono: ' . implode(', ', $updatableFields));
            return;
        }

        // Aggiungi l'ID utente ai parametri
        $params[] = $userId;

        try {
            // Esegui l'aggiornamento
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $this->db->query($sql, $params);

            // Invia la risposta
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Profilo aggiornato con successo'
            ]);
        } catch (\Exception $e) {
            $this->errorResponse('Errore durante l\'aggiornamento del profilo: ' . $e->getMessage());
        }
    }

    /**
     * Aggiorna la password di un utente
     *
     * @param int $userId ID dell'utente
     * @param string $newPassword Nuova password
     * @return bool True se l'operazione è riuscita
     */
    private function updateUserPassword($userId, $newPassword) {
        // Hash della nuova password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Aggiorna la password nel database
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $this->db->query($sql, [$hashedPassword, $userId]);

        return true;
    }

    /**
     * Gestisce la verifica dell'email
     */
    public function verifyEmail() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('GET')) {
            return;
        }

        // Ottieni il token dalla query string
        $token = isset($_GET['token']) ? $_GET['token'] : null;

        if (!$token) {
            $this->errorResponse('Token di verifica mancante');
            return;
        }

        // Verifica il token
        $result = $this->userManager->verifyEmail($token);

        if (!$result) {
            $this->errorResponse('Token di verifica non valido o scaduto');
            return;
        }

        // Prepara la risposta
        $successHtml = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Verificata con Successo</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    text-align: center;
                }
                .container {
                    padding: 20px;
                    border: 1px solid #e1e1e1;
                    border-radius: 5px;
                    margin-top: 40px;
                }
                .success-icon {
                    font-size: 48px;
                    color: #4CAF50;
                    margin-bottom: 20px;
                }
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #4CAF50;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="success-icon">✓</div>
                <h2>Email Verificata con Successo!</h2>
                <p>Ciao ' . htmlspecialchars($result['first_name']) . ',</p>
                <p>Il tuo indirizzo email è stato verificato con successo.</p>
                <p>Ora puoi accedere al tuo account e iniziare a utilizzare la nostra API per la generazione automatica di metadati SEO.</p>
                <a href="' . $_ENV['APP_URL'] . '/login" class="btn">Accedi al tuo account</a>
            </div>
        </body>
        </html>';

        // Invia la risposta HTML
        header('Content-Type: text/html; charset=utf-8');
        echo $successHtml;
        exit;
    }

    /**
     * Gestisce il reinvio dell'email di verifica
     */
    public function resendVerificationEmail() {
        // Verifica il metodo della richiesta
        if (!$this->validateRequestMethod('POST')) {
            return;
        }

        // Ottieni i dati della richiesta
        $data = $this->getJsonInput();

        // Verifica i parametri richiesti
        if (!$this->validateRequiredParams($data, ['email'])) {
            return;
        }

        // Valida email
        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->errorResponse('Indirizzo email non valido');
            return;
        }

        // Reinvia l'email di verifica
        $result = $this->userManager->resendVerificationEmail($email);

        if (!$result) {
            $this->errorResponse('Impossibile reinviare l\'email di verifica. L\'email potrebbe essere già verificata o non esistere.');
            return;
        }

        // Invia la risposta
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'Email di verifica inviata con successo. Controlla la tua casella di posta.'
        ]);
    }
}
