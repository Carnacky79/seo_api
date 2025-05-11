<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Se l'utente è già loggato, reindirizza alla dashboard
if (isAdminLoggedIn()) {
    redirect(ADMIN_URL . '/index.php');
}

$error = '';

// Gestione login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password';
    } else {
        if (loginAdmin($username, $password)) {
            // Reindirizza all'URL salvato o alla dashboard
            $redirectTo = $_SESSION['redirect_to'] ?? ADMIN_URL . '/index.php';
            unset($_SESSION['redirect_to']);
            redirect($redirectTo);
        } else {
            $error = 'Credenziali non valide';
        }
    }
}

// Gestisci messaggi di errore dalla query string
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_expired':
            $error = 'La sessione è scaduta. Effettua nuovamente il login.';
            break;
        case 'account_inactive':
            $error = 'Account disattivato. Contatta l\'amministratore.';
            break;
        case 'permission_denied':
            $error = 'Non hai i permessi per accedere a questa risorsa.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SEO Metadata API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
        }
        .form-signin {
            width: 100%;
            max-width: 330px;
            padding: 15px;
            margin: auto;
        }
        .form-signin .form-floating:focus-within {
            z-index: 2;
        }
        .form-signin input[type="text"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }
        .form-signin input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        .logo {
            max-width: 200px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="text-center">
<main class="form-signin">
    <form method="post" action="">
        <img class="logo" src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="Logo">
        <h1 class="h3 mb-3 fw-normal">Area Amministrativa</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-floating">
            <input type="text" class="form-control" id="username" name="username" placeholder="Username o Email" required>
            <label for="username">Username o Email</label>
        </div>
        <div class="form-floating">
            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
            <label for="password">Password</label>
        </div>

        <button class="w-100 btn btn-lg btn-primary" type="submit">Accedi</button>
        <p class="mt-5 mb-3 text-muted">&copy; <?php echo date('Y'); ?> SEO Metadata API</p>
    </form>
</main>
</body>
</html>
