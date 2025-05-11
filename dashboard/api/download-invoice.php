<?php
/**
 * API per il download delle fatture
 *
 * Questo script gestisce il download sicuro delle fatture degli utenti
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verifica che l'utente sia autenticato
requireLogin();

// Ottieni l'ID della fattura dal parametro
$invoiceId = $_GET['id'] ?? '';
if (empty($invoiceId)) {
    // Fattura non specificata
    header('HTTP/1.1 400 Bad Request');
    echo 'ID fattura mancante';
    exit;
}

// Ottieni il database
$pdo = getDbConnection();

// Verifica che la fattura appartenga all'utente corrente
$sql = "SELECT * FROM invoices WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$invoiceId, $_SESSION['user_id']]);
$invoice = $stmt->fetch();

if (!$invoice) {
    // Fattura non trovata o non autorizzata
    header('HTTP/1.1 403 Forbidden');
    echo 'Fattura non trovata o non autorizzata';
    exit;
}

// Verifica che la fattura abbia un URL PDF
if (empty($invoice['invoice_pdf'])) {
    // URL PDF mancante
    header('HTTP/1.1 404 Not Found');
    echo 'PDF fattura non disponibile';
    exit;
}

// Determina il nome del file da scaricare
$fileName = 'Fattura_' . date('Y-m-d', strtotime($invoice['created_at'])) . '.pdf';

// Prova a scaricare il PDF da Stripe
$pdfUrl = $invoice['invoice_pdf'];

// Usa cURL per recuperare il PDF
$ch = curl_init($pdfUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$pdfContent = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Verifica che il download sia riuscito
if ($httpCode != 200 || empty($pdfContent)) {
    // Errore durante il download
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Errore durante il download della fattura';
    exit;
}

// Registra il download nel log
$sql = "INSERT INTO event_logs (event_type, event_data, created_at) VALUES (?, ?, NOW())";
$stmt = $pdo->prepare($sql);
$data = json_encode([
    'user_id' => $_SESSION['user_id'],
    'invoice_id' => $invoice['id'],
    'action' => 'download_invoice'
]);
$stmt->execute(['invoice.download', $data]);

// Imposta gli header per il download del PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdfContent));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Invia il PDF al browser
echo $pdfContent;
exit;
