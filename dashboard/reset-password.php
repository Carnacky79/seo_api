<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Se l'utente è già loggato, reindirizza alla dashboard
if (isLoggedIn()) {
    redirect('index.php');
}

$token = $_GET['token'] ?? '';
$message = '';
$status = '';
$validToken = false;
$userId = null;

// Verifica token
if (empty($token)) {
    $status = 'danger';
    $message = 'Token di recupero mancante.';
} else {
    $user = verifyPasswordResetToken($token);

    if ($user) {
        $validToken = true;
        $userId = $user['id'];
    } else {
        $status = 'danger';
        $message = 'Token di recupero non valido o scaduto.';
    }
}

// Gestione reimpostazione password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (empty($password)) {
        $status = 'danger';
        $message = 'Inserisci una nuova password.';
    } elseif (strlen($password) < 8) {
        $status = 'danger';
        $message = 'La password deve contenere almeno 8 caratteri.';
    } elseif ($password !== $passwordConfirm) {
        $status = 'danger';
        $message = 'Le password non corrispondono.';
    } else {
        $result = resetPassword($userId, $password);

        if ($result) {
            $status = 'success';
            $message = 'Password reimpostata con successo! Ora puoi <a href="login.php">accedere</a> al tuo account.';
            $validToken = false; // Nasconde il form
        } else {
            $status = 'danger';
            $message = 'Errore durante la reimpostazione della password. Riprova più tardi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimposta Password - SEO Metadata API</title>
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
        .reset-container {
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
        .reset-footer {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--text-muted);
            text-align: center;
        }
    </style>
</head>
<body>
<div class="reset-container">
    <div class="text-center mb-4">
        <img src="assets/img/logo.png" alt="SEO Metadata API" class="logo">
    </div>

    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 mb-4 text-center">Reimposta la tua password</h1>

            <?php if (!empty($status)): ?>
                <div class="alert alert-<?php echo $status; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <p class="mb-4">Inserisci una nuova password per il tuo account.</p>

                <form method="post" action="">
                    <div class="form-group mb-3">
                        <label for="password" class="form-label">Nuova Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Inserisci una nuova password" required>
                        </div>
                        <div class="form-text">Almeno 8 caratteri.</div>
                    </div>

                    <div class="form-group mb-4">
                        <label for="password_confirm" class="form-label">Conferma Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Conferma la nuova password" required>
                        </div>
                    </div>

                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary">Reimposta Password</button>
                    </div>
                </form>
            <?php else: ?>
                <?php if ($status !== 'success'): ?>
                    <div class="text-center">
                        <p>Il link per reimpostare la password non è valido o è scaduto.</p>
                        <p>Puoi richiedere un nuovo link dalla pagina di <a href="forgot-password.php">recupero password</a>.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Torna al login
                </a>
            </div>
        </div>
    </div>

    <div class="reset-footer">
        <p>&copy; <?php echo date('Y'); ?> SEO Metadata API. Tutti i diritti riservati.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Validazione form
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            const password = document.getElementById('password');
            const passwordConfirm = document.getElementById('password_confirm');

            form.addEventListener('submit', function(event) {
                if (password.value !== passwordConfirm.value) {
                    event.preventDefault();
                    alert('Le password non corrispondono.');
                }
            });
        }
    });
</script>
</body>
</html>
