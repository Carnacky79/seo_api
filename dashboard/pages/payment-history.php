<?php
// Ottieni la cronologia dei pagamenti dell'utente
$pdo = getDbConnection();

// Imposta il numero di transazioni per pagina
$perPage = 10;

// Determina la pagina corrente
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $perPage;

// Ottieni il totale delle transazioni
$sql = "SELECT COUNT(*) as total FROM payment_transactions WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$totalRow = $stmt->fetch();
$totalTransactions = $totalRow ? (int)$totalRow['total'] : 0;

// Calcola il numero totale di pagine
$totalPages = ceil($totalTransactions / $perPage);

// Ottieni le transazioni per la pagina corrente
$sql = "SELECT * FROM payment_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id'], $perPage, $offset]);
$transactions = $stmt->fetchAll();

// Ottieni le fatture associate
$invoiceIds = array_map(function($transaction) {
    return $transaction['invoice_id'];
}, $transactions);

$invoices = [];
if (!empty($invoiceIds)) {
    $placeholders = str_repeat('?,', count($invoiceIds) - 1) . '?';
    $sql = "SELECT * FROM invoices WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($invoiceIds);
    $invoiceResults = $stmt->fetchAll();

    // Indicizza le fatture per id
    foreach ($invoiceResults as $invoice) {
        $invoices[$invoice['id']] = $invoice;
    }
}

// Funzione per formattare l'importo
function formatAmount($amount, $currency = 'EUR') {
    return number_format($amount / 100, 2, ',', '.') . ' €';
}

// Funzione per ottenere lo stato della transazione
function getStatusBadge($status) {
    switch ($status) {
        case 'completed':
            return '<span class="payment-status status-paid">Pagato</span>';
        case 'pending':
            return '<span class="payment-status status-pending">In attesa</span>';
        case 'failed':
            return '<span class="payment-status status-failed">Fallito</span>';
        case 'refunded':
            return '<span class="payment-status status-paid">Rimborsato</span>';
        default:
            return '<span class="payment-status">' . ucfirst($status) . '</span>';
    }
}
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Storico Pagamenti</h5>
        <?php if ($planType !== 'free'): ?>
            <a href="index.php?page=subscription" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-gem me-1"></i> Gestisci Abbonamento
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Non hai ancora effettuato pagamenti.
                <?php if ($planType === 'free'): ?>
                    <a href="index.php?page=subscription" class="alert-link">Passa a un piano a pagamento</a> per sbloccare funzionalità avanzate.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="payment-table">
                    <thead>
                    <tr>
                        <th>ID Transazione</th>
                        <th>Data</th>
                        <th>Descrizione</th>
                        <th>Importo</th>
                        <th>Stato</th>
                        <th>Fattura</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                            <td>
                                <?php
                                $description = htmlspecialchars($transaction['description']);
                                echo $description ?: 'Abbonamento ' . ucfirst($transaction['plan_type']);
                                ?>
                            </td>
                            <td><?php echo formatAmount($transaction['amount']); ?></td>
                            <td><?php echo getStatusBadge($transaction['status']); ?></td>
                            <td>
                                <?php if ($transaction['invoice_id'] && isset($invoices[$transaction['invoice_id']])): ?>
                                    <a href="api/download-invoice.php?id=<?php echo $transaction['invoice_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-file-pdf me-1"></i> PDF
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        Mostrando <?php echo ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalTransactions); ?> di <?php echo $totalTransactions; ?> transazioni
                    </div>
                    <nav aria-label="Navigazione pagine">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=payment-history&p=<?php echo ($page - 1); ?>" aria-label="Precedente">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            // Determina quali numeri di pagina mostrare
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            // Assicurati di mostrare almeno 5 pagine se disponibili
                            if ($endPage - $startPage + 1 < 5) {
                                if ($startPage == 1) {
                                    $endPage = min($totalPages, $startPage + 4);
                                } elseif ($endPage == $totalPages) {
                                    $startPage = max(1, $endPage - 4);
                                }
                            }

                            // Mostra link "..." se necessario
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=payment-history&p=1">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            // Mostra le pagine
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="page-item' . ($page == $i ? ' active' : '') . '">';
                                echo '<a class="page-link" href="?page=payment-history&p=' . $i . '">' . $i . '</a>';
                                echo '</li>';
                            }

                            // Mostra link "..." finale se necessario
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=payment-history&p=' . $totalPages . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=payment-history&p=<?php echo ($page + 1); ?>" aria-label="Successivo">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Informazioni di fatturazione -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Informazioni di Fatturazione</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="mb-3">Dati di Fatturazione</h6>

                <div class="mb-3">
                    <span class="text-muted">Nome:</span>
                    <span class="ms-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                </div>

                <div class="mb-3">
                    <span class="text-muted">Codice Fiscale:</span>
                    <span class="ms-2"><?php echo htmlspecialchars($user['fiscal_code']); ?></span>
                </div>

                <?php if (!empty($user['company'])): ?>
                    <div class="mb-3">
                        <span class="text-muted">Azienda:</span>
                        <span class="ms-2"><?php echo htmlspecialchars($user['company']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($user['vat_number'])): ?>
                    <div class="mb-3">
                        <span class="text-muted">Partita IVA:</span>
                        <span class="ms-2"><?php echo htmlspecialchars($user['vat_number']); ?></span>
                    </div>
                <?php endif; ?>

                <a href="index.php?page=profile" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit me-1"></i> Modifica Informazioni
                </a>
            </div>

            <div class="col-md-6">
                <h6 class="mb-3">Metodo di Pagamento</h6>

                <?php
                // Ottieni il metodo di pagamento predefinito dell'utente
                $sql = "SELECT * FROM payment_methods WHERE user_id = ? AND is_default = 1 LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user['id']]);
                $paymentMethod = $stmt->fetch();
                ?>

                <?php if ($paymentMethod): ?>
                    <div class="mb-3">
                        <span class="text-muted">Tipo:</span>
                        <span class="ms-2">
                            <?php if ($paymentMethod['type'] === 'card'): ?>
                                <i class="fab fa-cc-<?php echo strtolower($paymentMethod['card_brand']); ?>"></i>
                                Carta <?php echo ucfirst($paymentMethod['card_brand']); ?>
                            <?php else: ?>
                                <?php echo ucfirst($paymentMethod['type']); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ($paymentMethod['type'] === 'card'): ?>
                        <div class="mb-3">
                            <span class="text-muted">Numero:</span>
                            <span class="ms-2">•••• <?php echo htmlspecialchars($paymentMethod['last4']); ?></span>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted">Scadenza:</span>
                            <span class="ms-2"><?php echo htmlspecialchars($paymentMethod['exp_month'] . '/' . $paymentMethod['exp_year']); ?></span>
                        </div>
                    <?php endif; ?>

                    <a href="index.php?page=subscription" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-credit-card me-1"></i> Gestisci Metodi di Pagamento
                    </a>
                <?php elseif ($planType !== 'free'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Nessun metodo di pagamento registrato.
                    </div>

                    <a href="index.php?page=subscription" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Aggiungi Metodo di Pagamento
                    </a>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Stai utilizzando il piano gratuito. Passa a un piano a pagamento per aggiungere un metodo di pagamento.
                    </div>

                    <a href="index.php?page=subscription" class="btn btn-sm btn-primary">
                        <i class="fas fa-arrow-up me-1"></i> Passa a un Piano a Pagamento
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="divider mt-4 mb-4"></div>

        <div class="alert alert-info mb-0">
            <i class="fas fa-file-invoice me-2"></i> <strong>Nota:</strong> Le fatture vengono generate automaticamente dopo ogni pagamento riuscito e sono disponibili per il download nella tabella sopra.
        </div>
    </div>
</div>
