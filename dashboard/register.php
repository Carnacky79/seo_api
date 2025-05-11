<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Se l'utente è già loggato, reindirizza alla dashboard
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Gestione registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $fiscalCode = sanitize($_POST['fiscal_code'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $company = sanitize($_POST['company'] ?? '');
    $vatNumber = sanitize($_POST['vat_number'] ?? '');

    // Validazioni
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName) || empty($fiscalCode)) {
        $error = 'Tutti i campi obbligatori devono essere compilati';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida';
    } elseif (strlen($password) < 8) {
        $error = 'La password deve contenere almeno 8 caratteri';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Le password non corrispondono';
    } else {
        try {
            // Registra l'utente
            $userData = [
                'email' => $email,
                'password' => $password,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'fiscal_code' => $fiscalCode,
                'phone' => $phone,
                'company' => $company,
                'vat_number' => $vatNumber
            ];

            $user = registerUser($userData);

            // Registrazione riuscita
            $success = 'Registrazione completata con successo! Ti abbiamo inviato un\'email di verifica.';

            // Reindirizza alla verifica email
            $_SESSION['registered_email'] = $email;
            redirect('verify-email.php?new=1');
        } catch (\Exception $e) {
            $error = 'Errore durante la registrazione: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrati - SEO Metadata API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/mobile.css">
    <style>
        body {
            background-color: var(--bg-light);
            padding: 40px 0;
            min-height: 100vh;
        }
        .register-container {
            width: 100%;
            max-width: 700px;
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
        .register-footer {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--text-muted);
            text-align: center;
        }
        .form-label .required {
            color: #dc3545;
            margin-left: 3px;
        }
    </style>
</head>
<body>
<div class="register-container">
    <div class="text-center mb-4">
        <img src="assets/img/logo.png" alt="SEO Metadata API" class="logo">
    </div>

    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 mb-4 text-center">Crea un nuovo account</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post" action="" class="needs-validation" novalidate>
                <div class="row">
                    <!-- Dati personali -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Dati personali</h5>

                        <div class="form-group mb-3">
                            <label for="first_name" class="form-label">Nome<span class="required">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Inserisci il tuo nome" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="last_name" class="form-label">Cognome<span class="required">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Inserisci il tuo cognome" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="fiscal_code" class="form-label">Codice Fiscale<span class="required">*</span></label>
                            <input type="text" class="form-control" id="fiscal_code" name="fiscal_code" placeholder="Inserisci il tuo codice fiscale" value="<?php echo isset($_POST['fiscal_code']) ? htmlspecialchars($_POST['fiscal_code']) : ''; ?>" required>
                            <div class="form-text">Il codice fiscale è necessario per la fatturazione.</div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="phone" class="form-label">Telefono</label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="Inserisci il tuo numero di telefono" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Dati aziendali e account -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Dati account</h5>

                        <div class="form-group mb-3">
                            <label for="email" class="form-label">Email<span class="required">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Inserisci la tua email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="password" class="form-label">Password<span class="required">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Crea una password" required>
                            <div class="form-text">Almeno 8 caratteri.</div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="password_confirm" class="form-label">Conferma Password<span class="required">*</span></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Conferma la password" required>
                        </div>

                        <h5 class="mb-3 mt-4">Dati aziendali (opzionali)</h5>

                        <div class="form-group mb-3">
                            <label for="company" class="form-label">Azienda</label>
                            <input type="text" class="form-control" id="company" name="company" placeholder="Nome azienda" value="<?php echo isset($_POST['company']) ? htmlspecialchars($_POST['company']) : ''; ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label for="vat_number" class="form-label">Partita IVA</label>
                            <input type="text" class="form-control" id="vat_number" name="vat_number" placeholder="Partita IVA" value="<?php echo isset($_POST['vat_number']) ? htmlspecialchars($_POST['vat_number']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-check mb-3 mt-3">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        Accetto i <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Termini di Servizio</a> e la <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a><span class="required">*</span>
                    </label>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary">Registrati</button>
                </div>

                <div class="text-center">
                    Hai già un account? <a href="login.php" class="text-decoration-none">Accedi</a>
                </div>
            </form>
        </div>
    </div>

    <div class="register-footer">
        <p>&copy; <?php echo date('Y'); ?> SEO Metadata API. Tutti i diritti riservati.</p>
    </div>
</div>

<!-- Modal Termini di Servizio -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Termini di Servizio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4>1. Accettazione dei Termini</h4>
                <p>Utilizzando il servizio SEO Metadata API, accetti i presenti Termini di Servizio. Se non accetti questi termini, non puoi utilizzare il servizio.</p>

                <h4>2. Descrizione del Servizio</h4>
                <p>SEO Metadata API è un servizio di API che genera metadati SEO ottimizzati per pagine web. Il servizio è fornito "così com'è" e potrebbe essere soggetto a modifiche.</p>

                <h4>3. Account</h4>
                <p>Per utilizzare SEO Metadata API, è necessario creare un account. Sei responsabile del mantenimento della sicurezza del tuo account e sei pienamente responsabile di tutte le attività che si verificano sotto il tuo account.</p>

                <h4>4. Pagamenti e Fatturazione</h4>
                <p>Gli abbonamenti a pagamento vengono fatturati in anticipo su base mensile. Non è previsto alcun rimborso per pagamenti parziali o mensilità non utilizzate.</p>

                <h4>5. Cancellazione</h4>
                <p>Puoi annullare il tuo abbonamento in qualsiasi momento. Dopo la cancellazione, il tuo account rimarrà attivo fino alla fine del periodo di fatturazione corrente.</p>

                <h4>6. Modifiche ai Termini</h4>
                <p>Ci riserviamo il diritto di modificare questi termini in qualsiasi momento. Le modifiche ai termini entreranno in vigore immediatamente dopo la pubblicazione.</p>

                <h4>7. Limitazioni di Responsabilità</h4>
                <p>In nessun caso saremo responsabili per danni diretti, indiretti, incidentali, speciali, consequenziali o punitivi derivanti dall'uso del servizio.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Ho capito</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Privacy Policy -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4>1. Raccolta delle Informazioni</h4>
                <p>Raccogliamo informazioni personali come nome, indirizzo email, codice fiscale e, se forniti, informazioni aziendali. Raccogliamo anche informazioni sull'utilizzo del servizio.</p>

                <h4>2. Utilizzo delle Informazioni</h4>
                <p>Utilizziamo le informazioni raccolte per fornire e migliorare il servizio, processare i pagamenti, inviare comunicazioni relative al servizio e per scopi di fatturazione.</p>

                <h4>3. Condivisione delle Informazioni</h4>
                <p>Non vendiamo, scambiamo o trasferiamo a terzi le tue informazioni personali. Questo non include terze parti fidate che ci assistono nel gestire il nostro sito web o servizio, purché accettino di mantenere queste informazioni riservate.</p>

                <h4>4. Sicurezza</h4>
                <p>Implementiamo una varietà di misure di sicurezza per mantenere la sicurezza delle tue informazioni personali. Le password sono hashate e tutte le informazioni sensibili sono trasmesse tramite connessioni sicure.</p>

                <h4>5. Cookie</h4>
                <p>Utilizziamo i cookie per migliorare l'esperienza dell'utente. I cookie sono piccoli file che un sito o il suo fornitore di servizi trasferisce sul disco rigido del tuo computer attraverso il tuo browser Web.</p>

                <h4>6. Diritti dell'Utente</h4>
                <p>Hai il diritto di accedere, correggere o eliminare le tue informazioni personali. Per esercitare questi diritti, contattaci tramite i dettagli forniti sul nostro sito.</p>

                <h4>7. Modifiche alla Privacy Policy</h4>
                <p>Ci riserviamo il diritto di modificare questa privacy policy in qualsiasi momento. Le modifiche entreranno in vigore immediatamente dopo la pubblicazione sul nostro sito.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Ho capito</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Validazione form
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form.needs-validation');
        const password = document.getElementById('password');
        const passwordConfirm = document.getElementById('password_confirm');

        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Verifica se le password corrispondono
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity('Le password non corrispondono');
                event.preventDefault();
                event.stopPropagation();
            } else {
                passwordConfirm.setCustomValidity('');
            }

            form.classList.add('was-validated');
        }, false);

        // Reset validazione custom quando l'utente modifica l'input
        passwordConfirm.addEventListener('input', function() {
            if (password.value === passwordConfirm.value) {
                passwordConfirm.setCustomValidity('');
            } else {
                passwordConfirm.setCustomValidity('Le password non corrispondono');
            }
        });
    });
</script>
</body>
</html>
