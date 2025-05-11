<?php
// Verifica che sia stato fornito un ID utente valido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID utente non valido</div>';
    echo '<a href="' . ADMIN_URL . '/?page=users" class="btn btn-primary">Torna all\'elenco utenti</a>';
    return;
}

$userId = (int)$_GET['id'];
$pdo = getDbConnection();

// Ottieni i dati dell'utente
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo '<div class="alert alert-danger">Utente non trovato</div>';
    echo '<a href="' . ADMIN_URL . '/?page=users" class="btn btn-primary">Torna all\'elenco utenti</a>';
    return;
}

// Ottieni l'abbonamento attivo
$sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$subscription = $stmt->fetch();

// Ottieni le statistiche di utilizzo
$sql = "SELECT month, year, request_count FROM api_usage WHERE user_id = ? ORDER BY year DESC, month DESC LIMIT 12";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$usageStats = $stmt->fetchAll();

// Ottieni le ultime richieste API
$sql = "SELECT * FROM request_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$apiRequests = $stmt->fetchAll();

// Calcola il totale delle richieste questo mese
$currentMonth = date('n');
$currentYear = date('Y');
$currentMonthUsage = 0;

foreach ($usageStats as $stat) {
    if ($stat['month'] == $currentMonth && $stat['year'] == $currentYear) {
        $currentMonthUsage = $stat['request_count'];
        break;
    }
}

// Ottieni il limite di richieste in base al piano
$requestLimit = 0;
$planName = 'Sconosciuto';

if ($subscription) {
    switch ($subscription['plan_type']) {
        case 'free':
            $requestLimit = 10;
            $planName = 'Gratuito';
            break;
        case 'pro':
            $requestLimit = 1000;
            $planName = 'Pro';
            break;
        case 'premium':
            $requestLimit = PHP_INT_MAX; // Illimitato
            $planName = 'Premium';
            break;
    }
}

// Formatta lo stato dell'utente
$statusClass = '';
$statusText = '';

switch ($user['status']) {
    case 'active':
        $statusClass = 'success';
        $statusText = 'Attivo';
        break;
    case 'inactive':
        $statusClass = 'secondary';
        $statusText = 'Inattivo';
        break;
    case 'suspended':
        $statusClass = 'danger';
        $statusText = 'Sospeso';
        break;
}
?>

<!-- Intestazione profilo utente -->
<div class="user-profile-header mb-4">
    <div class="row">
        <div class="col-md-8">
            <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
            <p class="text-muted mb-2">
                <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($user['email']); ?>
                <?php if ($user['email_verified'] == 1): ?>
                    <span class="badge bg-success ms-2">Email verificata</span>
                <?php else: ?>
                    <span class="badge bg-danger ms-2">Email non verificata</span>
                <?php endif; ?>
            </p>
            <p class="text-muted mb-2">
                <i class="fas fa-id-card me-2"></i> <?php echo htmlspecialchars($user['fiscal_code']); ?>
            </p>
            <?php if (!empty($user['phone'])): ?>
                <p class="text-muted mb-2">
                    <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($user['phone']); ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($user['company'])): ?>
                <p class="text-muted mb-2">
                    <i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($user['company']); ?>
                    <?php if (!empty($user['vat_number'])): ?>
                        (P.IVA: <?php echo htmlspecialchars($user['vat_number']); ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <p class="text-muted">
                <i class="fas fa-calendar-alt me-2"></i> Registrato il <?php echo formatDate($user['created_at']); ?>
            </p>
        </div>
        <div class="col-md-4 text-md-end">
            <span class="badge bg-<?php echo $statusClass; ?> fs-6 mb-3"><?php echo $statusText; ?></span>
            <div>
                <?php if ($subscription): ?>
                    <?php $badgeClass = $subscription['plan_type'] === 'free' ? 'secondary' : ($subscription['plan_type'] === 'pro' ? 'info' : 'warning'); ?>
                    <span class="badge bg-<?php echo $badgeClass; ?> badge-subscription">Piano <?php echo $planName; ?></span>
                <?php else: ?>
                    <span class="badge bg-secondary badge-subscription">Nessun piano attivo</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Azioni utente -->
<div class="user-actions mb-4">
    <div class="btn-group">
        <?php if ($user['status'] === 'active'): ?>
            <button class="btn btn-warning" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'suspended')">
                <i class="fas fa-ban me-2"></i> Sospendi
            </button>
        <?php elseif ($user['status'] === 'suspended'): ?>
            <button class="btn btn-success" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'active')">
                <i class="fas fa-check me-2"></i> Riattiva
            </button>
        <?php elseif ($user['status'] === 'inactive'): ?>
            <button class="btn btn-success" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'active')">
                <i class="fas fa-check me-2"></i> Riattiva
            </button>
        <?php endif; ?>

        <?php if ($user['email_verified'] == 0): ?>
            <button class="btn btn-primary" onclick="verifyEmail(<?php echo $user['id']; ?>)">
                <i class="fas fa-envelope-open-text me-2"></i> Verifica Email
            </button>
        <?php endif; ?>

        <button class="btn btn-secondary" onclick="regenerateApiKey(<?php echo $user['id']; ?>)">
            <i class="fas fa-key me-2"></i> Rigenera Chiave API
        </button>

        <?php if ($user['status'] !== 'inactive'): ?>
            <button class="btn btn-danger" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'inactive')">
                <i class="fas fa-trash me-2"></i> Disattiva Account
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs per le diverse sezioni -->
<ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">Informazioni</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="subscription-tab" data-bs-toggle="tab" data-bs-target="#subscription" type="button" role="tab" aria-controls="subscription" aria-selected="false">Abbonamento</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="usage-tab" data-bs-toggle="tab" data-bs-target="#usage" type="button" role="tab" aria-controls="usage" aria-selected="false">Utilizzo</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api" type="button" role="tab" aria-controls="api" aria-selected="false">Chiave API</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab" aria-controls="logs" aria-selected="false">Log Richieste</button>
    </li>
</ul>

<div class="tab-content" id="userTabsContent">
    <!-- Tab Informazioni -->
    <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
        <div class="row">
            <div class="col-md-6">
                <h4 class="mb-3">Dati personali</h4>
                <dl class="row">
                    <dt class="col-sm-4">Nome</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($user['first_name']); ?></dd>

                    <dt class="col-sm-4">Cognome</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($user['last_name']); ?></dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($user['email']); ?></dd>

                    <dt class="col-sm-4">Codice Fiscale</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($user['fiscal_code']); ?></dd>

                    <dt class="col-sm-4">Telefono</dt>
                    <dd class="col-sm-8"><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<em>Non specificato</em>'; ?></dd>

                    <dt class="col-sm-4">Azienda</dt>
                    <dd class="col-sm-8"><?php echo !empty($user['company']) ? htmlspecialchars($user['company']) : '<em>Non specificata</em>'; ?></dd>

                    <dt class="col-sm-4">Partita IVA</dt>
                    <dd class="col-sm-8"><?php echo !empty($user['vat_number']) ? htmlspecialchars($user['vat_number']) : '<em>Non specificata</em>'; ?></dd>
                </dl>
            </div>

            <div class="col-md-6">
                <h4 class="mb-3">Informazioni account</h4>
                <dl class="row">
                    <dt class="col-sm-4">ID</dt>
                    <dd class="col-sm-8"><?php echo $user['id']; ?></dd>

                    <dt class="col-sm-4">Stato</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </dd>

                    <dt class="col-sm-4">Email verificata</dt>
                    <dd class="col-sm-8">
                        <?php if ($user['email_verified'] == 1): ?>
                            <span class="text-success"><i class="fas fa-check-circle"></i> Sì</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="fas fa-times-circle"></i> No</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Registrato il</dt>
                    <dd class="col-sm-8"><?php echo formatDate($user['created_at']); ?></dd>

                    <dt class="col-sm-4">Ultimo aggiornamento</dt>
                    <dd class="col-sm-8"><?php echo formatDate($user['updated_at']); ?></dd>

                    <dt class="col-sm-4">Piano attuale</dt>
                    <dd class="col-sm-8">
                        <?php if ($subscription): ?>
                            <?php $badgeClass = $subscription['plan_type'] === 'free' ? 'secondary' : ($subscription['plan_type'] === 'pro' ? 'info' : 'warning'); ?>
                            <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $planName; ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Nessun piano attivo</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Tab Abbonamento -->
    <div class="tab-pane fade" id="subscription" role="tabpanel" aria-labelledby="subscription-tab">
        <div class="row mb-4">
            <div class="col-md-6">
                <h4 class="mb-3">Dettagli abbonamento</h4>

                <?php if ($subscription): ?>
                    <dl class="row">
                        <dt class="col-sm-4">Piano</dt>
                        <dd class="col-sm-8">
                            <?php $badgeClass = $subscription['plan_type'] === 'free' ? 'secondary' : ($subscription['plan_type'] === 'pro' ? 'info' : 'warning'); ?>
                            <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $planName; ?></span>
                        </dd>

                        <dt class="col-sm-4">Stato</dt>
                        <dd class="col-sm-8">
                            <?php if ($subscription['status'] === 'active'): ?>
                                <span class="badge bg-success">Attivo</span>
                            <?php elseif ($subscription['status'] === 'canceled'): ?>
                                <span class="badge bg-danger">Cancellato</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">In attesa di pagamento</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Periodo corrente</dt>
                        <dd class="col-sm-8">
                            <?php echo formatDate($subscription['current_period_start'], 'd/m/Y'); ?> -
                            <?php echo formatDate($subscription['current_period_end'], 'd/m/Y'); ?>
                        </dd>

                        <?php if ($subscription['plan_type'] !== 'free'): ?>
                            <dt class="col-sm-4">ID Stripe</dt>
                            <dd class="col-sm-8">
                                <?php echo !empty($subscription['stripe_subscription_id']) ?
                                    htmlspecialchars($subscription['stripe_subscription_id']) : '<em>Non disponibile</em>'; ?>
                            </dd>

                            <dt class="col-sm-4">Cliente Stripe</dt>
                            <dd class="col-sm-8">
                                <?php echo !empty($subscription['stripe_customer_id']) ?
                                    htmlspecialchars($subscription['stripe_customer_id']) : '<em>Non disponibile</em>'; ?>
                            </dd>
                        <?php endif; ?>

                        <dt class="col-sm-4">Creato il</dt>
                        <dd class="col-sm-8"><?php echo formatDate($subscription['created_at']); ?></dd>
                    </dl>
                <?php else: ?>
                    <div class="alert alert-info">Nessun abbonamento attivo trovato.</div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <h4 class="mb-3">Cambia piano</h4>
                <div class="card">
                    <div class="card-body">
                        <p>Piano attuale: <strong><?php echo $planName; ?></strong></p>
                        <form id="updateSubscriptionForm" class="ajax-form" action="api/subscriptions.php" method="post">
                            <input type="hidden" name="action" value="update_subscription">
                            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                            <div class="mb-3">
                                <label for="planType" class="form-label">Nuovo piano</label>
                                <select class="form-select" id="planType" name="plan_type" required>
                                    <option value="">Seleziona un piano</option>
                                    <option value="free" <?php echo ($subscription && $subscription['plan_type'] === 'free') ? 'selected' : ''; ?>>Gratuito (10 richieste/mese)</option>
                                    <option value="pro" <?php echo ($subscription && $subscription['plan_type'] === 'pro') ? 'selected' : ''; ?>>Pro (1.000 richieste/mese)</option>
                                    <option value="premium" <?php echo ($subscription && $subscription['plan_type'] === 'premium') ? 'selected' : ''; ?>>Premium (richieste illimitate)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Aggiorna Piano</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Utilizzo -->
    <div class="tab-pane fade" id="usage" role="tabpanel" aria-labelledby="usage-tab">
        <div class="row mb-4">
            <div class="col-md-6">
                <h4 class="mb-3">Utilizzo corrente</h4>

                <div class="card">
                    <div class="card-body">
                        <p>Richieste utilizzate questo mese: <strong><?php echo $currentMonthUsage; ?></strong></p>

                        <?php if ($subscription && $subscription['plan_type'] !== 'premium'): ?>
                            <p>Limite mensile: <strong><?php echo $requestLimit; ?></strong></p>

                            <?php $percentUsed = min(100, ($currentMonthUsage / $requestLimit) * 100); ?>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar <?php echo $percentUsed > 90 ? 'bg-danger' : ($percentUsed > 70 ? 'bg-warning' : 'bg-success'); ?>"
                                     role="progressbar" style="width: <?php echo $percentUsed; ?>%;"
                                     aria-valuenow="<?php echo $percentUsed; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($percentUsed); ?>%
                                </div>
                            </div>

                            <p>Richieste rimanenti: <strong><?php echo max(0, $requestLimit - $currentMonthUsage); ?></strong></p>
                        <?php elseif ($subscription && $subscription['plan_type'] === 'premium'): ?>
                            <p>Piano Premium con <strong>richieste illimitate</strong>.</p>
                        <?php else: ?>
                            <div class="alert alert-warning">Nessun piano attivo.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <h4 class="mb-3">Statistiche mensili</h4>

                <?php if (!empty($usageStats)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Mese</th>
                                <th>Richieste</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($usageStats as $stat): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($stat['year'] . '-' . $stat['month'] . '-01')); ?></td>
                                    <td><?php echo $stat['request_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Nessuna statistica di utilizzo disponibile.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab Chiave API -->
    <div class="tab-pane fade" id="api" role="tabpanel" aria-labelledby="api-tab">
        <div class="row">
            <div class="col-md-8">
                <h4 class="mb-3">Chiave API</h4>

                <div class="card">
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['api_key']); ?>" id="user-api-key" readonly>
                            <button class="btn btn-outline-secondary btn-copy" type="button" data-copy="<?php echo htmlspecialchars($user['api_key']); ?>">
                                <i class="fas fa-copy"></i> Copia
                            </button>
                        </div>

                        <button class="btn btn-warning" onclick="regenerateApiKey(<?php echo $user['id']; ?>)">
                            <i class="fas fa-key me-2"></i> Rigenera Chiave API
                        </button>
                        <small class="d-block mt-2 text-muted">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            La rigenerazione della chiave API renderà inutilizzabile la chiave precedente.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Log Richieste -->
    <div class="tab-pane fade" id="logs" role="tabpanel" aria-labelledby="logs-tab">
        <h4 class="mb-3">Ultime richieste API</h4>

        <?php if (!empty($apiRequests)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>URL</th>
                        <th>Risposta</th>
                        <th>Tempo (sec)</th>
                        <th>Data</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($apiRequests as $request): ?>
                        <tr>
                            <td><?php echo $request['id']; ?></td>
                            <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 350px;" title="<?php echo htmlspecialchars($request['url']); ?>">
                                        <?php echo htmlspecialchars($request['url']); ?>
                                    </span>
                            </td>
                            <td>
                                <?php if ($request['response_code'] >= 200 && $request['response_code'] < 300): ?>
                                    <span class="badge bg-success"><?php echo $request['response_code']; ?></span>
                                <?php elseif ($request['response_code'] >= 400): ?>
                                    <span class="badge bg-danger"><?php echo $request['response_code']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?php echo $request['response_code']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo round($request['execution_time'], 2); ?></td>
                            <td><?php echo formatDate($request['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Nessuna richiesta API registrata.</div>
        <?php endif; ?>
    </div>
</div>
