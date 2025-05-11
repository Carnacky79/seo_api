<?php

namespace SeoMetadataApi\Auth;

use SeoMetadataApi\Config\Database;
use SeoMetadataApi\Subscription\PlanManager;

class UserManager {
    private $db;
    private $planManager;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->planManager = new PlanManager();
    }

    /**
     * Registra un nuovo utente
     *
     * @param string $email Email dell'utente
     * @param string $password Password dell'utente
     * @param string $firstName Nome dell'utente
     * @param string $lastName Cognome dell'utente
     * @param string $fiscalCode Codice fiscale dell'utente
     * @param string|null $phone Numero di telefono (opzionale)
     * @param string|null $company Nome azienda (opzionale)
     * @param string|null $vatNumber Partita IVA (opzionale)
     * @return array Dati dell'utente registrato
     */
    public function register($email, $password, $firstName, $lastName, $fiscalCode, $phone = null, $company = null, $vatNumber = null) {
        // Verifica se l'email è già in uso
        $existingUser = $this->getUserByEmail($email);
        if ($existingUser) {
            throw new \Exception("Email già registrata");
        }

        // Verifica se il codice fiscale è già in uso
        $existingFiscalCode = $this->getUserByFiscalCode($fiscalCode);
        if ($existingFiscalCode) {
            throw new \Exception("Codice fiscale già registrato");
        }

        // Valida il codice fiscale
        if (!$this->validateItalianFiscalCode($fiscalCode)) {
            throw new \Exception("Codice fiscale non valido");
        }

        // Hash della password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Genera una chiave API unica
        $apiKey = $this->generateApiKey();

        // Genera un token di verifica
        $verificationToken = $this->generateVerificationToken();
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Inserisci utente nel database
        $sql = "INSERT INTO users (email, password, first_name, last_name, fiscal_code, phone, company, vat_number, api_key, verification_token, verification_token_expires) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->query($sql, [
            $email,
            $hashedPassword,
            $firstName,
            $lastName,
            strtoupper($fiscalCode),
            $phone,
            $company,
            $vatNumber,
            $apiKey,
            $verificationToken,
            $tokenExpires
        ]);

        $userId = $this->db->getConnection()->lastInsertId();

        // Crea abbonamento gratuito predefinito
        $this->planManager->createFreeSubscription($userId);

        // Invia email di verifica
        $emailManager = new EmailManager();
        $emailManager->sendVerificationEmail($email, $firstName, $verificationToken);

        return [
            'id' => $userId,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'fiscal_code' => strtoupper($fiscalCode),
            'api_key' => $apiKey,
            'email_verified' => false
        ];
    }

    /**
     * Autentica un utente
     */
    public function login($email, $password) {
        $user = $this->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            throw new \Exception("Credenziali non valide");
        }

        // Verifica se l'email è stata verificata
        if ($user['email_verified'] == 0) {
            throw new \Exception("Email non verificata. Controlla la tua casella di posta o richiedi un nuovo link di verifica.");
        }

        // Verifica se l'account è attivo
        if ($user['status'] !== 'active') {
            throw new \Exception("Account " . $user['status'] . ". Contatta l'assistenza.");
        }

        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'api_key' => $user['api_key'],
            'email_verified' => (bool) $user['email_verified']
        ];
    }

    /**
     * Ottiene un utente tramite email
     */
    public function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->db->query($sql, [$email]);
        return $stmt->fetch();
    }

    /**
     * Ottiene un utente tramite codice fiscale
     */
    public function getUserByFiscalCode($fiscalCode) {
        $sql = "SELECT * FROM users WHERE fiscal_code = ?";
        $stmt = $this->db->query($sql, [strtoupper($fiscalCode)]);
        return $stmt->fetch();
    }

    /**
     * Ottiene un utente tramite ID
     */
    public function getUserById($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }

    /**
     * Valida il formato di un codice fiscale italiano
     *
     * @param string $fiscalCode Il codice fiscale da validare
     * @return bool True se il codice fiscale è valido, altrimenti False
     */
    private function validateItalianFiscalCode($fiscalCode) {
        // Rimuovi spazi e converti in maiuscolo
        $fiscalCode = strtoupper(str_replace(' ', '', $fiscalCode));

        // Verifica la lunghezza (16 caratteri)
        if (strlen($fiscalCode) != 16) {
            return false;
        }

        // Verifica il formato base: 6 lettere + 2 numeri + 1 lettera + 2 numeri + 1 lettera + 3 numeri + 1 lettera
        if (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $fiscalCode)) {
            return false;
        }

        // Valida il carattere di controllo (implementazione semplificata)
        $oddSum = 0;
        $evenSum = 0;

        $conversionTable = [
            '0' => 1,  '1' => 0,  '2' => 5,  '3' => 7,  '4' => 9,  '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
            'A' => 1,  'B' => 0,  'C' => 5,  'D' => 7,  'E' => 9,  'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
            'K' => 2,  'L' => 4,  'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3,  'Q' => 6,  'R' => 8,  'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23
        ];

        // Calcola i valori per ogni posizione
        for ($i = 0; $i < 15; $i++) {
            $char = $fiscalCode[$i];
            if ($i % 2 === 0) {  // Posizioni dispari (0-based)
                $oddSum += $conversionTable[$char];
            } else {  // Posizioni pari (0-based)
                $evenSum += (is_numeric($char)) ? (int)$char : (ord($char) - ord('A'));
            }
        }

        $totalSum = $oddSum + $evenSum;
        $checkChar = chr(($totalSum % 26) + ord('A'));

        // Verifica che il carattere di controllo sia corretto
        return $checkChar === $fiscalCode[15];
    }

    /**
     * Genera un token di verifica unico
     *
     * @return string Token di verifica
     */
    private function generateVerificationToken() {
        return md5(uniqid() . time() . rand(1000, 9999));
    }

    /**
     * Verifica un token di verifica e attiva l'account
     *
     * @param string $token Token di verifica
     * @return bool|array False se il token non è valido, altrimenti i dati dell'utente
     */
    public function verifyEmail($token) {
        // Cerca il token nel database
        $sql = "SELECT * FROM users 
                WHERE verification_token = ? 
                AND verification_token_expires > NOW() 
                AND email_verified = 0";

        $stmt = $this->db->query($sql, [$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        // Attiva l'account
        $sql = "UPDATE users 
                SET email_verified = 1, 
                    verification_token = NULL, 
                    verification_token_expires = NULL 
                WHERE id = ?";

        $this->db->query($sql, [$user['id']]);

        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ];
    }

    /**
     * Invia un nuovo token di verifica
     *
     * @param string $email Email dell'utente
     * @return bool True se l'email è stata inviata, altrimenti False
     */
    public function resendVerificationEmail($email) {
        // Cerca l'utente nel database
        $user = $this->getUserByEmail($email);

        if (!$user) {
            return false;
        }

        // Verifica se l'email è già verificata
        if ($user['email_verified'] == 1) {
            return false;
        }

        // Genera un nuovo token
        $verificationToken = $this->generateVerificationToken();
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Aggiorna il token nel database
        $sql = "UPDATE users 
                SET verification_token = ?, 
                    verification_token_expires = ? 
                WHERE id = ?";

        $this->db->query($sql, [$verificationToken, $tokenExpires, $user['id']]);

        // Invia email di verifica
        $emailManager = new EmailManager();
        return $emailManager->sendVerificationEmail($user['email'], $user['first_name'], $verificationToken);
    }

    /**
     * Aggiorna la chiave API di un utente
     */
    public function regenerateApiKey($userId) {
        $newApiKey = $this->generateApiKey();

        $sql = "UPDATE users SET api_key = ? WHERE id = ?";
        $this->db->query($sql, [$newApiKey, $userId]);

        return $newApiKey;
    }
}
