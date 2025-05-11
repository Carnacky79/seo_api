<?php
/**
 * Webhook per la gestione degli eventi Stripe
 *
 * Questo script gestisce gli eventi inviati da Stripe, come pagamenti riusciti,
 * abbonamenti creati/aggiornati/cancellati, ecc.
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php'; // Per Stripe PHP SDK

// Configura Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Recupera l'evento dal payload
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    // Verifica la firma dell'evento usando il webhook secret
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sigHeader, STRIPE_WEBHOOK_SECRET
    );
} catch (\UnexpectedValueException $e) {
    // Firma di payload non valida
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Errore di verifica firma
    http_response_code(400);
    exit();
}

// Ottieni la connessione al database
$pdo = getDbConnection();

// Gestisci l'evento in base al tipo
switch ($event->type) {
    case 'checkout.session.completed':
        // Un checkout è stato completato con successo
        handleCheckoutSessionCompleted($event->data->object);
        break;

    case 'customer.subscription.created':
        // Un nuovo abbonamento è stato creato
        handleSubscriptionCreated($event->data->object);
        break;

    case 'customer.subscription.updated':
        // Un abbonamento è stato aggiornato
        handleSubscriptionUpdated($event->data->object);
        break;

    case 'customer.subscription.deleted':
        // Un abbonamento è stato cancellato
        handleSubscriptionDeleted($event->data->object);
        break;

    case 'invoice.paid':
        // Una fattura è stata pagata
        handleInvoicePaid($event->data->object);
        break;

    case 'invoice.payment_failed':
        // Un pagamento è fallito
        handleInvoicePaymentFailed($event->data->object);
        break;
}

// Restituisci una risposta di successo
http_response_code(200);
echo json_encode(['status' => 'success']);

/**
 * Gestisce il completamento di una sessione di checkout
 *
 * @param \Stripe\Checkout\Session $session Sessione di checkout
 */
function handleCheckoutSessionCompleted($session) {
    // La sessione di checkout è stata completata con successo
    // L'abbonamento verrà gestito dall'evento subscription.created

    // Registra l'evento nel log
    logEvent('checkout.session.completed', [
        'session_id' => $session->id,
        'customer_id' => $session->customer,
        'user_id' => $session->metadata->user_id ?? null,
        'plan_type' => $session->metadata->plan_type ?? null
    ]);
}

/**
 * Gestisce la creazione di un nuovo abbonamento
 *
 * @param \Stripe\Subscription $subscription Abbonamento
 */
function handleSubscriptionCreated($subscription) {
    global $pdo;

    // Estrai l'ID utente dai metadati
    $userId = $subscription->metadata->user_id ?? null;
    $planType = $subscription->metadata->plan_type ?? null;

    if (!$userId || !$planType) {
        // Cerca l'utente tramite l'ID cliente Stripe
        $sql = "SELECT id FROM users WHERE stripe_customer_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$subscription->customer]);
        $user = $stmt->fetch();

        if ($user) {
            $userId = $user['id'];
        } else {
            // Non possiamo associare questo abbonamento a un utente
            logEvent('subscription.orphaned', [
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer
            ]);
            return;
        }

        // Determina il tipo di piano dal prezzo
        $priceId = $subscription->items->data[0]->price->id;
        if ($priceId === STRIPE_PRICE_PRO) {
            $planType = 'pro';
        } elseif ($priceId === STRIPE_PRICE_PREMIUM) {
            $planType = 'premium';
        } else {
            $planType = 'unknown';
        }
    }

    // Calcola le date di inizio e fine periodo
    $periodStart = date('Y-m-d H:i:s', $subscription->current_period_start);
    $periodEnd = date('Y-m-d H:i:s', $subscription->current_period_end);

    // Disattiva eventuali abbonamenti attivi esistenti
    $sql = "UPDATE subscriptions SET status = 'inactive' WHERE user_id = ? AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);

    // Inserisci il nuovo abbonamento
    $sql = "INSERT INTO subscriptions (
                user_id, plan_type, status, stripe_subscription_id,
                stripe_customer_id, current_period_start, current_period_end,
                cancel_at_period_end, created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, NOW()
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $userId,
        $planType,
        $subscription->status === 'active' ? 'active' : $subscription->status,
        $subscription->id,
        $subscription->customer,
        $periodStart,
        $periodEnd,
        $subscription->cancel_at_period_end ? 1 : 0
    ]);

    // Registra l'evento nel log
    logEvent('subscription.created', [
        'subscription_id' => $subscription->id,
        'customer_id' => $subscription->customer,
        'user_id' => $userId,
        'plan_type' => $planType
    ]);
}

/**
 * Gestisce l'aggiornamento di un abbonamento
 *
 * @param \Stripe\Subscription $subscription Abbonamento
 */
function handleSubscriptionUpdated($subscription) {
    global $pdo;

    // Cerca l'abbonamento nel database
    $sql = "SELECT id, user_id, plan_type FROM subscriptions WHERE stripe_subscription_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subscription->id]);
    $existingSubscription = $stmt->fetch();

    if (!$existingSubscription) {
        // L'abbonamento non esiste nel nostro database, crealo
        handleSubscriptionCreated($subscription);
        return;
    }

    // Aggiorna le date di periodo
    $periodStart = date('Y-m-d H:i:s', $subscription->current_period_start);
    $periodEnd = date('Y-m-d H:i:s', $subscription->current_period_end);

    // Aggiorna l'abbonamento
    $sql = "UPDATE subscriptions SET
                status = ?,
                current_period_start = ?,
                current_period_end = ?,
                cancel_at_period_end = ?,
                updated_at = NOW()
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $subscription->status === 'active' ? 'active' : $subscription->status,
        $periodStart,
        $periodEnd,
        $subscription->cancel_at_period_end ? 1 : 0,
        $existingSubscription['id']
    ]);

    // Registra l'evento nel log
    logEvent('subscription.updated', [
        'subscription_id' => $subscription->id,
        'customer_id' => $subscription->customer,
        'user_id' => $existingSubscription['user_id'],
        'plan_type' => $existingSubscription['plan_type'],
        'status' => $subscription->status
    ]);
}

/**
 * Gestisce la cancellazione di un abbonamento
 *
 * @param \Stripe\Subscription $subscription Abbonamento
 */
function handleSubscriptionDeleted($subscription) {
    global $pdo;

    // Cerca l'abbonamento nel database
    $sql = "SELECT id, user_id FROM subscriptions WHERE stripe_subscription_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subscription->id]);
    $existingSubscription = $stmt->fetch();

    if (!$existingSubscription) {
        // L'abbonamento non esiste nel nostro database
        return;
    }

    // Aggiorna lo stato dell'abbonamento
    $sql = "UPDATE subscriptions SET 
                status = 'canceled', 
                updated_at = NOW() 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$existingSubscription['id']]);

    // Crea un nuovo abbonamento gratuito per l'utente
    createFreeSubscription($existingSubscription['user_id']);

    // Registra l'evento nel log
    logEvent('subscription.deleted', [
        'subscription_id' => $subscription->id,
        'customer_id' => $subscription->customer,
        'user_id' => $existingSubscription['user_id']
    ]);
}

/**
 * Gestisce il pagamento di una fattura
 *
 * @param \Stripe\Invoice $invoice Fattura
 */
function handleInvoicePaid($invoice) {
    global $pdo;

    // Verifica se è già registrata nel database
    $sql = "SELECT id FROM invoices WHERE stripe_invoice_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$invoice->id]);

    if ($stmt->fetch()) {
        // La fattura è già stata registrata
        return;
    }

    // Cerca l'utente associato a questa fattura
    $customerId = $invoice->customer;
    $sql = "SELECT id FROM users WHERE stripe_customer_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customerId]);
    $user = $stmt->fetch();

    if (!$user) {
        // Non possiamo associare questa fattura a un utente
        logEvent('invoice.orphaned', [
            'invoice_id' => $invoice->id,
            'customer_id' => $customerId
        ]);
        return;
    }

    $userId = $user['id'];

    // Determina il tipo di piano
    $planType = 'unknown';
    foreach ($invoice->lines->data as $line) {
        if ($line->price && $line->price->id) {
            if ($line->price->id === STRIPE_PRICE_PRO) {
                $planType = 'pro';
                break;
            } elseif ($line->price->id === STRIPE_PRICE_PREMIUM) {
                $planType = 'premium';
                break;
            }
        }
    }

    // Inserisci la fattura nel database
    $sql = "INSERT INTO invoices (
                user_id, stripe_invoice_id, stripe_customer_id,
                amount_total, currency, status, invoice_pdf,
                hosted_invoice_url, created_at
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, NOW()
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $userId,
        $invoice->id,
        $invoice->customer,
        $invoice->amount_paid,
        strtoupper($invoice->currency),
        $invoice->status,
        $invoice->invoice_pdf,
        $invoice->hosted_invoice_url
    ]);

    $invoiceId = $pdo->lastInsertId();

    // Registra la transazione di pagamento
    $sql = "INSERT INTO payment_transactions (
                user_id, invoice_id, transaction_id, stripe_customer_id,
                amount, currency, status, description, plan_type,
                created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                NOW()
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $userId,
        $invoiceId,
        $invoice->payment_intent,
        $invoice->customer,
        $invoice->amount_paid,
        strtoupper($invoice->currency),
        'completed',
        'Abbonamento ' . ucfirst($planType),
        $planType
    ]);

    // Invia email di conferma all'utente
    sendInvoiceEmail($userId, $invoiceId);

    // Registra l'evento nel log
    logEvent('invoice.paid', [
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer,
        'user_id' => $userId,
        'amount' => $invoice->amount_paid,
        'currency' => $invoice->currency
    ]);
}

/**
 * Gestisce il fallimento di un pagamento
 *
 * @param \Stripe\Invoice $invoice Fattura
 */
function handleInvoicePaymentFailed($invoice) {
    global $pdo;

    // Cerca l'utente associato a questa fattura
    $customerId = $invoice->customer;
    $sql = "SELECT id, email, first_name FROM users WHERE stripe_customer_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customerId]);
    $user = $stmt->fetch();

    if (!$user) {
        // Non possiamo associare questa fattura a un utente
        return;
    }

    $userId = $user['id'];

    // Registra il fallimento del pagamento
    $sql = "INSERT INTO payment_transactions (
                user_id, transaction_id, stripe_customer_id,
                amount, currency, status, description,
                created_at
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                NOW()
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $userId,
        $invoice->payment_intent,
        $invoice->customer,
        $invoice->amount_due,
        strtoupper($invoice->currency),
        'failed',
        'Pagamento fattura fallito'
    ]);

    // Invio di email di notifica all'utente
    sendPaymentFailedEmail($user['id'], $user['email'], $user['first_name'], $invoice->id);

    // Registra l'evento nel log
    logEvent('invoice.payment_failed', [
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer,
        'user_id' => $userId,
        'amount' => $invoice->amount_due,
        'currency' => $invoice->currency
    ]);
}

/**
 * Registra un evento nel log
 *
 * @param string $eventType Tipo di evento
 * @param array $data Dati aggiuntivi
 */
function logEvent($eventType, $data) {
    global $pdo;

    $sql = "INSERT INTO event_logs (event_type, event_data, created_at) VALUES (?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eventType, json_encode($data)]);
}

/**
 * Invia email di conferma fattura
 *
 * @param int $userId ID dell'utente
 * @param int $invoiceId ID della fattura
 */
function sendInvoiceEmail($userId, $invoiceId) {
    global $pdo;

    // Recupera i dati dell'utente
    $sql = "SELECT email, first_name FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    // Recupera i dati della fattura
    $sql = "SELECT * FROM invoices WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        return;
    }

    // Costruisci il messaggio email
    $subject = 'Conferma pagamento - SEO Metadata API';
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Conferma pagamento</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .container { padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px; }
            .header { text-align: center; padding-bottom: 10px; border-bottom: 1px solid #e1e1e1; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 30px; font-size: 12px; color: #777; text-align: center; }
            .amount { font-size: 24px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Conferma pagamento</h2>
            </div>
            <p>Ciao ' . htmlspecialchars($user['first_name']) . ',</p>
            <p>Grazie per il tuo pagamento. La tua fattura è stata elaborata con successo.</p>
            
            <div style="margin: 30px 0; text-align: center;">
                <p>Importo: <span class="amount">' . number_format($invoice['amount_total'] / 100, 2, ',', '.') . ' ' . $invoice['currency'] . '</span></p>
                <p>Stato: Pagato</p>
                <p>Data: ' . date('d/m/Y', strtotime($invoice['created_at'])) . '</p>
            </div>
            
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($invoice['hosted_invoice_url']) . '" class="btn">Visualizza Fattura</a>
            </p>
            
            <p>Puoi scaricare la fattura in formato PDF dal tuo account o direttamente da questo link:</p>
            <p><a href="' . htmlspecialchars($invoice['invoice_pdf']) . '">Scarica Fattura PDF</a></p>
            
            <p>Se hai domande o dubbi riguardo al tuo pagamento, non esitare a contattarci.</p>
            
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
    mail($user['email'], $subject, $message, $headers);
}

/**
 * Invia email di notifica pagamento fallito
 *
 * @param int $userId ID dell'utente
 * @param string $email Email dell'utente
 * @param string $firstName Nome dell'utente
 * @param string $invoiceId ID fattura Stripe
 */
function sendPaymentFailedEmail($userId, $email, $firstName, $invoiceId) {
    // Costruisci il messaggio email
    $subject = 'Pagamento fallito - SEO Metadata API';
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Pagamento fallito</title>
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
                <h2>Pagamento fallito</h2>
            </div>
            <p>Ciao ' . htmlspecialchars($firstName) . ',</p>
            <p>Abbiamo riscontrato un problema con il tuo recente pagamento per il servizio SEO Metadata API.</p>
            
            <p>Il pagamento per la tua fattura non è andato a buon fine. Questo potrebbe essere dovuto a fondi insufficienti, alla scadenza della carta o a un altro problema con il tuo metodo di pagamento.</p>
            
            <p>Per evitare interruzioni del servizio, ti preghiamo di aggiornare il tuo metodo di pagamento e di riprovare.</p>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . getBaseUrl() . '/index.php?page=subscription" class="btn">Aggiorna metodo di pagamento</a>
            </p>
            
            <p>Se hai domande o dubbi riguardo al tuo pagamento, non esitare a contattarci.</p>
            
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
    mail($email, $subject, $message, $headers);
}
