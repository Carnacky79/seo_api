<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$token = $_GET['token'] ?? '';
$isNewRegistration = isset($_GET['new']) && $_GET['new'] == 1;
$email = $_SESSION['registered_email'] ?? '';
$message = '';
$status = '';
$verified = false;

// Se l'utente è già loggato e ha già verificato l'email, reindirizza alla dashboard
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user && $user['email_verified'] == 1) {
        redirect('index.php');
    }
}

// Verifica token
if (!empty($token)) {
    $user = verifyEmailToken($token);

    if ($user) {
        $verified = true;
        $status = 'success';
        $message = "Email verificata con successo! Ora puoi <a href='login.php'>accedere</a> al tuo account.";

        // Se l'utente era già loggato, aggiorna la sessione
        if (isLoggedIn() && $_SESSION['user_id'] == $user['id']) {
            $_SESSION['email_verified'] = true;
            // Imposta un flash message
            $_SESSION['flash_message'] = 'Email verificata con successo!';
            $_SESSION['flash_type'] = 'success';

            // Reindirizza alla dashboard
            redirect('index.php');
        }
    } else {
        $status = 'danger';
        $message = 'Token di verifica non valido o scaduto.';
    }
}

// Gestione richiesta di invio nuovo token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resendEmail = sanitize($_POST['email'] ?? '');

    if (empty($resendEmail)) {
        $status = 'danger';
        $message = 'Inserisci un indirizzo email valido.';
    } else {
        $result = resendVerificationEmail($resendEmail);

        if ($result) {
            $status = 'success';
            $message = 'Un nuovo link di verifica è stato inviato al tuo indirizzo email.';
        } else {
            $status = 'danger';
            $message = 'Impossibile inviare il link di verifica. L\'email potrebbe essere già verificata o non esistere.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica Email - SEO Metadata API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/mobile.css">
    <style>
        body {
            background-color: var(--bg-light);
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .verify-container {
            width: 100%;
            max-width: 500px;
            padding: 15px;
            margin: auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        .logo {
            max-width: 180px;
            margin-bottom: 1.5rem;
        }
        .verify-footer {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--text-muted);
            text-align: center;
        }
        .icon-large {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .success-icon {
            color: var(--success-color);
        }
        .pending-icon {
            color: var(--warning-color);
        }
        .error-icon {
            color: var(--danger-color);
        }
    </style>
</head>
<body>
<div class="verify-container">
    <div class="text-center mb-4">
        <img src="assets/img/logo.png" alt="SEO Metadata API" class="logo">
    </div>

    <div class="card">
        <div class="card-body p-4 text-center">
            <?php if ($verified): ?>
                <i class="fas fa-check-circle icon-large success-icon"></i>
                <h1 class="h4 mb-3">Email verificata con successo!</h1>
                <p>Il tuo indirizzo email è stato verificato. Ora puoi accedere al tuo account e utilizzare l'API.</p>
                <a href="login.php" class="btn btn-primary mt-3">Accedi</a>
            <?php elseif ($isNewRegistration): ?>
                <i class="fas fa-envelope icon-large pending-icon"></i>
                <h1 class="h4 mb-3">Verifica il tuo indirizzo email</h1>
                <p>Abbiamo inviato un'email di verifica a <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                <p>Per completare la registrazione, segui il link contenuto nell'email.</p>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i> Se non hai ricevuto l'email, controlla anche nella cartella spam.
                </div>

                <form method="post" action="" class="mt-4">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit" class="btn btn-outline-primary">Invia nuovamente l'email</button>
                </form>
            <?php else: ?>
                <?php if (!empty($status)): ?>
                    <div class="alert alert-<?php echo $status; ?> mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <i class="fas fa-envelope icon-large pending-icon"></i>
                <h1 class="h4 mb-3">Verifica il tuo indirizzo email</h1>
                <p>Se non hai ricevuto l'email di verifica o il link è scaduto, inserisci il tuo indirizzo email per ricevere un nuovo link di verifica.</p>

                <form method="post" action="" class="mt-4">
                    <div class="form-group mb-3">
                        <label for="email" class="form-label visually-hidden">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Inserisci la tua email" required>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Invia link di verifica</button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="mt-4">
                <a href="login.php">Torna alla pagina di login</a>
            </div>
        </div>
    </div>

    <div class="verify-footer">
        <p>&copy; <?php echo date('Y'); ?> SEO Metadata API. Tutti i diritti riservati.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
