<?php
/**
 * Funzioni di autenticazione per la dashboard utente
 */

/**
 * Verifica che l'utente sia autenticato
 * Reindirizza al login se non autenticato
 */
function requireLogin() {
    // Avvia la sessione se non è già attiva
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica se l'utente è loggato
    if (!isLoggedIn()) {
        // Salva l'URL corrente per il redirect dopo il login
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
    }

    // Verifica che l'account sia attivo
    $user = getCurrentUser();

    if (!$user || $user['status'] !== 'active') {
        // L'account è stato disattivato
        session_destroy();
        redirect('login.php?error=account_inactive');
    }

    // Verifica se l'email è verificata
    if (!$user['email_verified'] && !isset($_SESSION['verification_skip'])) {
        redirect('verify-email.php');
    }
}

/**
 * Verifica se l'utente è autenticato
 *
 * @return bool True se l'utente è autenticato
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Ottiene i dati dell'utente corrente
 *
 * @return array|null Dati utente o null se non autenticato
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDbConnection();
    $sql = "SELECT * FROM users WHERE id = ? AND status != 'inactive'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);

    return $stmt->fetch();
}

/**
 * Ottiene l'abbonamento attivo dell'utente corrente
 *
 * @return array|null Dati abbonamento o null se non presente
 */
function getCurrentSubscription() {
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDbConnection();
    $sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY current_period_end DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);

    return $stmt->fetch();
}

/**
 * Autentica un utente
 *
 * @param string $email Email dell'utente
 * @param string $password Password dell'utente
 * @return array|false Dati utente se autenticato, false altrimenti
 */
function loginUser($email, $password) {
    $pdo = getDbConnection();

    $sql = "SELECT * FROM users WHERE email = ? AND status != 'inactive'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verifica che l'utente esista e la password sia corretta
    if ($user && password_verify($password, $user['password'])) {
        // Imposta le variabili di sessione
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];

        // Aggiorna l'ultimo accesso
        updateLastLogin($user['id']);

        return $user;
    }

    return false;
}

/**
 * Registra un nuovo utente
 *
 * @param array $userData Dati utente (email, password, first_name, last_name, fiscal_code, ecc.)
 * @return array|false Dati utente se registrato, false in caso di errore
 */
function registerUser($userData) {
    $pdo = getDbConnection();

    // Verifica che l'email non sia già in uso
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userData['email']]);

    if ($stmt->fetch()) {
        throw new Exception('Indirizzo email già in uso');
    }

    // Verifica che il codice fiscale non sia già in uso
    $sql = "SELECT id FROM users WHERE fiscal_code = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([strtoupper($userData['fiscal_code'])]);

    if ($stmt->fetch()) {
        throw new Exception('Codice fiscale già in uso');
    }

    // Hash della password
    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

    // Genera una chiave API
    $apiKey = generateApiKey();

    // Genera un token di verifica
    $verificationToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Inserisci l'utente nel database
    $sql = "INSERT INTO users (
                email, password, first_name, last_name, fiscal_code, 
                phone, company, vat_number, api_key, 
                verification_token, verification_token_expires
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?
            )";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $userData['email'],
        $hashedPassword,
        $userData['first_name'],
        $userData['last_name'],
        strtoupper($userData['fiscal_code']),
        $userData['phone'] ?? null,
        $userData['company'] ?? null,
        $userData['vat_number'] ?? null,
        $apiKey,
        $verificationToken,
        $tokenExpires
    ]);

    if (!$success) {
        return false;
    }

    $userId = $pdo->lastInsertId();

    // Crea un abbonamento gratuito predefinito
    createFreeSubscription($userId);

    // Invia email di verifica
    sendVerificationEmail($userData['email'], $userData['first_name'], $verificationToken);

    return [
        'id' => $userId,
        'email' => $userData['email'],
        'first_name' => $userData['first_name'],
        'last_name' => $userData['last_name'],
        'api_key' => $apiKey
    ];
}

/**
 * Crea un abbonamento gratuito per un utente
 *
 * @param int $userId ID dell'utente
 * @return bool True se creato con successo
 */
function createFreeSubscription($userId) {
    $pdo = getDbConnection();

    $currentDate = date('Y-m-d H:i:s');
    $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));

    $sql = "INSERT INTO subscriptions (
                user_id, plan_type, status,
                current_period_start, current_period_end
            ) VALUES (
                ?, 'free', 'active',
                ?, ?
            )";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$userId, $currentDate, $endDate]);
}

/**
 * Aggiorna l'ultimo accesso di un utente
 *
 * @param int $userId ID dell'utente
 */
function updateLastLogin($userId) {
    $pdo = getDbConnection();

    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
}

/**
 * Logout dell'utente corrente
 */
function logoutUser() {
    // Distruggi la sessione
    session_unset();
    session_destroy();

    // Reindirizza alla pagina di login
    redirect('login.php');
}

/**
 * Genera una chiave API univoca
 *
 * @return string Chiave API generata
 */
function generateApiKey() {
    $apiKey = bin2hex(random_bytes(32));

    // Verifica che la chiave non sia già in uso
    $pdo = getDbConnection();
    $sql = "SELECT COUNT(*) as count FROM users WHERE api_key = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$apiKey]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        // Se la chiave esiste già, ne genera un'altra
        return generateApiKey();
    }

    return $apiKey;
}

/**
 * Verifica un token di verifica email e attiva l'account
 *
 * @param string $token Token di verifica
 * @return array|false Dati utente se verificato, false altrimenti
 */
function verifyEmailToken($token) {
    $pdo = getDbConnection();

    $sql = "SELECT * FROM users 
            WHERE verification_token = ? 
            AND verification_token_expires > NOW()
            AND email_verified = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    // Aggiorna lo stato di verifica
    $sql = "UPDATE users 
            SET email_verified = 1,
                verification_token = NULL,
                verification_token_expires = NULL
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$user['id']]);

    if (!$success) {
        return false;
    }

    return $user;
}

/**
 * Reimposta il token di verifica email e invia una nuova email
 *
 * @param string $email Email dell'utente
 * @return bool True se inviato con successo
 */
function resendVerificationEmail($email) {
    $pdo = getDbConnection();

    $sql = "SELECT * FROM users 
            WHERE email = ? 
            AND email_verified = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    // Genera un nuovo token
    $verificationToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Aggiorna il token
    $sql = "UPDATE users 
            SET verification_token = ?,
                verification_token_expires = ?
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$verificationToken, $tokenExpires, $user['id']]);

    if (!$success) {
        return false;
    }

    // Invia email
    return sendVerificationEmail($user['email'], $user['first_name'], $verificationToken);
}

/**
 * Invia un'email di verifica
 *
 * @param string $email Email del destinatario
 * @param string $firstName Nome del destinatario
 * @param string $verificationToken Token di verifica
 * @return bool True se inviata con successo
 */
function sendVerificationEmail($email, $firstName, $verificationToken) {
    // Costruisci l'URL di verifica
    $verificationUrl = getBaseUrl() . '/verify-email.php?token=' . $verificationToken;

    // Costruisci il messaggio
    $subject = 'Verifica il tuo indirizzo email - SEO Metadata API';

    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Verifica il tuo indirizzo email</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .container { padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px; }
            .header { text-align: center; padding-bottom: 10px; border-bottom: 1px solid #e1e1e1; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 30px; font-size: 12px; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Verifica il tuo indirizzo email</h2>
            </div>
            <p>Ciao ' . htmlspecialchars($firstName) . ',</p>
            <p>Grazie per esserti registrato alla nostra API per la generazione automatica di metadati SEO!</p>
            <p>Per completare la registrazione e iniziare a utilizzare il servizio, devi verificare il tuo indirizzo email cliccando sul pulsante qui sotto:</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $verificationUrl . '" class="btn">Verifica Email</a>
            </p>
            <p>Oppure copia e incolla questo link nel tuo browser:</p>
            <p>' . $verificationUrl . '</p>
            <p>Se non hai creato un account, puoi ignorare questa email.</p>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' SEO Metadata API. Tutti i diritti riservati.</p>
            </div>
        </div>
    </body>
    </html>';

    // Imposta intestazioni per email HTML
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: SEO Metadata API <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";

    // Invia email
    return mail($email, $subject, $message, $headers);
}

/**
 * Genera un token per il reset della password
 *
 * @param string $email Email dell'utente
 * @return bool True se il token è stato generato e l'email inviata con successo
 */
function generatePasswordResetToken($email) {
    $pdo = getDbConnection();

    // Verifica che l'utente esista
    $sql = "SELECT * FROM users WHERE email = ? AND status != 'inactive'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    // Genera token
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Salva token nel database
    $sql = "UPDATE users
            SET reset_token = ?,
                reset_token_expires = ?
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$resetToken, $tokenExpires, $user['id']]);

    if (!$success) {
        return false;
    }

    // Invia email
    return sendPasswordResetEmail($user['email'], $user['first_name'], $resetToken);
}

/**
 * Invia un'email per il reset della password
 *
 * @param string $email Email del destinatario
 * @param string $firstName Nome del destinatario
 * @param string $resetToken Token di reset
 * @return bool True se inviata con successo
 */
function sendPasswordResetEmail($email, $firstName, $resetToken) {
    // Costruisci l'URL di reset
    $resetUrl = getBaseUrl() . '/reset-password.php?token=' . $resetToken;

    // Costruisci il messaggio
    $subject = 'Ripristino password - SEO Metadata API';

    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Ripristino password</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .container { padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px; }
            .header { text-align: center; padding-bottom: 10px; border-bottom: 1px solid #e1e1e1; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 30px; font-size: 12px; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Ripristino password</h2>
            </div>
            <p>Ciao ' . htmlspecialchars($firstName) . ',</p>
            <p>Abbiamo ricevuto una richiesta di reimpostazione della password per il tuo account.</p>
            <p>Per reimpostare la password, clicca sul pulsante qui sotto:</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $resetUrl . '" class="btn">Reimposta Password</a>
            </p>
            <p>Oppure copia e incolla questo link nel tuo browser:</p>
            <p>' . $resetUrl . '</p>
            <p>Il link scadrà tra un\'ora.</p>
            <p>Se non hai richiesto il reset della password, puoi ignorare questa email.</p>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' SEO Metadata API. Tutti i diritti riservati.</p>
            </div>
        </div>
    </body>
    </html>';

    // Imposta intestazioni per email HTML
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: SEO Metadata API <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";

    // Invia email
    return mail($email, $subject, $message, $headers);
}

/**
 * Verifica un token di reset password
 *
 * @param string $token Token di reset
 * @return array|false Dati utente se verificato, false altrimenti
 */
function verifyPasswordResetToken($token) {
    $pdo = getDbConnection();

    $sql = "SELECT * FROM users 
            WHERE reset_token = ? 
            AND reset_token_expires > NOW()
            AND status != 'inactive'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);

    return $stmt->fetch();
}

/**
 * Reimposta la password di un utente
 *
 * @param int $userId ID dell'utente
 * @param string $newPassword Nuova password
 * @return bool True se aggiornata con successo
 */
function resetPassword($userId, $newPassword) {
    $pdo = getDbConnection();

    // Hash della nuova password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Aggiorna password e rimuovi token
    $sql = "UPDATE users
            SET password = ?,
                reset_token = NULL,
                reset_token_expires = NULL
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([$hashedPassword, $userId]);
}

/**
 * Ottiene l'URL base del sito
 *
 * @return string URL base
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = $path === '/' ? '' : $path;

    return "$protocol://$host$path";
}
