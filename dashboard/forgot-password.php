<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Se l'utente è già loggato, reindirizza alla dashboard
if (isLoggedIn()) {
    redirect('index.php');
}

$message = '';
$status = '';

// Gestione richiesta di reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        $status = 'danger';
        $message = 'Inserisci un indirizzo email valido.';
    } else {
        $result = generatePasswordResetToken($email);

        // Per sicurezza, non rivelare se l'email esiste o meno
        $status = 'success';
        $message = 'Se l\'indirizzo email esiste nel nostro database, riceverai un link per reimpostare la password.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recupero Password - SEO Metadata API</title>
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
        .forgot-container {
            width: 100%;
            max-width: 450px;
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
        .forgot-footer {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--text-muted);
            text-align: center;
        }
    </style>
</head>
<body>
<div class="forgot-container">
    <div class="text-center mb-4">
        <img src="assets/img/logo.png" alt="SEO Metadata API" class="logo">
    </div>

    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 mb-4 text-center">Recupera la tua password</h1>

            <?php if (!empty($status)): ?>
                <div class="alert alert-<?php echo $status; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <p class="mb-4">Inserisci l'indirizzo email associato al tuo account e ti invieremo un link per reimpostare la password.</p>

            <form method="post" action="">
                <div class="form-group mb-4">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Inserisci la tua email" required>
                    </div>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary">Invia link di recupero</button>
                </div>

                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Torna al login
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="forgot-footer">
        <p>&copy; <?php echo date('Y'); ?> SEO Metadata API. Tutti i diritti riservati.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
