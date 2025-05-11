<?php
// Ottieni le statistiche di utilizzo
$pdo = getDbConnection();

// Ottieni statistiche per il mese corrente
$currentMonth = date('n');
$currentYear = date('Y');

$sql = "SELECT request_count FROM api_usage WHERE user_id = ? AND month = ? AND year = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id'], $currentMonth, $currentYear]);
$currentUsage = $stmt->fetch();
$requestCount = $currentUsage ? (int)$currentUsage['request_count'] : 0;

// Ottieni i limiti in base al piano
$requestLimit = 0;
switch ($planType) {
    case 'free':
        $requestLimit = 10;
        break;
    case 'pro':
        $requestLimit = 1000;
        break;
    case 'premium':
        $requestLimit = PHP_INT_MAX; // Illimitato
        break;
}

// Calcola la percentuale di utilizzo
$percentUsed = $planType !== 'premium' ? min(100, ($requestCount / $requestLimit) * 100) : 0;
$requestsRemaining = $planType !== 'premium' ? max(0, $requestLimit - $requestCount) : 'Illimitato';

// Ottieni la data di scadenza dell'abbonamento
$subscriptionEnds = $subscription ? date('d/m/Y', strtotime($subscription['current_period_end'])) : 'N/A';

// Ottieni le ultime richieste API
$sql = "SELECT * FROM request_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$recentRequests = $stmt->fetchAll();
?>

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stats-card">
            <div class="card-icon icon-primary">
                <i class="fas fa-server"></i>
            </div>
            <div class="card-title">Richieste API</div>
            <div class="card-value"><?php echo number_format($requestCount); ?></div>
            <div class="text-muted">questo mese</div>
        </div>

        <div class="stats-card">
            <div class="card-icon icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-title">Richieste Rimanenti</div>
            <div class="card-value"><?php echo is_numeric($requestsRemaining) ? number_format($requestsRemaining) : $requestsRemaining; ?></div>
            <div class="text-muted">fino al <?php echo $subscriptionEnds; ?></div>
        </div>

        <div class="stats-card">
            <div class="card-icon icon-warning">
                <i class="fas fa-gem"></i>
            </div>
            <div class="card-title">Piano Attuale</div>
            <div class="card-value"><?php echo $planName; ?></div>
            <div class="text-muted">
                <?php if ($planType !== 'premium'): ?>
                    <a href="index.php?page=subscription" class="text-primary">Aggiorna</a>
                <?php else: ?>
                    Piano premium
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-card">
            <div class="card-icon icon-info">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="card-title">Abbonamento</div>
            <div class="card-value"><?php echo $subscriptionEnds; ?></div>
            <div class="text-muted">data di scadenza</div>
        </div>
    </div>

    <!-- Usage Progress -->
<?php if ($planType !== 'premium'): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Utilizzo API</h5>
        </div>
        <div class="card-body">
            <div class="usage-stats">
                <div class="progress">
                    <div class="progress-bar <?php
                    if ($percentUsed < 70) echo 'progress-bar-success';
                    elseif ($percentUsed < 90) echo 'progress-bar-warning';
                    else echo 'progress-bar-danger';
                    ?>" style="width: <?php echo $percentUsed; ?>%"></div>
                </div>
                <div class="usage-details">
                    <div>
                        <span class="font-semibold"><?php echo number_format($requestCount); ?></span> di <?php echo number_format($requestLimit); ?> richieste
                    </div>
                    <div>
                        <?php echo number_format($percentUsed, 1); ?>%
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <a href="index.php?page=usage-stats" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-chart-bar me-1"></i> Vedi statistiche complete
                </a>
                <a href="index.php?page=subscription" class="btn btn-sm btn-outline-primary ms-2">
                    <i class="fas fa-gem me-1"></i> Modifica piano
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Utilizzo API</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-success mb-0">
                <i class="fas fa-infinity me-2"></i> Il tuo piano <strong>Premium</strong> include richieste API illimitate!
            </div>
            <div class="mt-3">
                <a href="index.php?page=usage-stats" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-chart-bar me-1"></i> Vedi statistiche complete
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

    <!-- Quick Access Cards -->
    <div class="row">
        <!-- API Key Card -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Chiave API</h5>
                    <a href="index.php?page=api-key" class="btn btn-sm btn-outline-primary">Gestisci</a>
                </div>
                <div class="card-body">
                    <div class="api-key-display">
                        <div class="api-key-value"><?php echo str_repeat('•', strlen($user['api_key'])); ?></div>
                        <div class="api-key-actions">
                            <button type="button" class="btn-show" title="Mostra/Nascondi">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn-copy" title="Copia" data-copy="<?php echo htmlspecialchars($user['api_key']); ?>">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-muted">
                        <i class="fas fa-info-circle me-1"></i> Questa chiave è necessaria per autenticare le richieste all'API
                    </div>
                </div>
            </div>
        </div>

        <!-- API Tester Card -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Test API</h5>
                    <a href="index.php?page=api-tester" class="btn btn-sm btn-outline-primary">Apri tester</a>
                </div>
                <div class="card-body">
                    <p>Testa rapidamente le funzionalità dell'API direttamente dal tuo browser.</p>
                    <div class="code-example">
                    <pre><code>curl -H "Authorization: Bearer <?php echo substr($user['api_key'], 0, 10); ?>..." \
     -H "Content-Type: application/json" \
     -d '{"url": "https://www.example.com"}' \
     -X POST <?php echo getBaseUrl(); ?>/api/generate-metadata</code></pre>
                    </div>
                    <a href="index.php?page=documentation" class="btn btn-sm btn-outline-secondary mt-2">
                        <i class="fas fa-book me-1"></i> Documentazione
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent API Requests -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Richieste API Recenti</h5>
            <a href="index.php?page=usage-stats" class="btn btn-sm btn-outline-primary">Visualizza tutte</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentRequests)): ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i> Non hai ancora effettuato richieste API.
                    <a href="index.php?page=api-tester" class="alert-link">Prova l'API ora!</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                        <tr>
                            <th>URL</th>
                            <th>Stato</th>
                            <th>Tempo</th>
                            <th>Data</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentRequests as $request): ?>
                            <tr>
                                <td>
                                    <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($request['url']); ?>">
                                        <?php echo htmlspecialchars($request['url']); ?>
                                    </div>
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
                                <td><?php echo number_format($request['execution_time'], 2); ?> sec</td>
                                <td><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upgrade Banner -->
<?php if ($planType === 'free'): ?>
    <div class="card bg-primary text-white">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title text-white mb-1">Passa al piano Pro</h5>
                <p class="mb-0">Ottieni 1.000 richieste API al mese e sblocca funzionalità avanzate.</p>
            </div>
            <a href="index.php?page=subscription" class="btn btn-light">
                <i class="fas fa-arrow-up me-2"></i> Aggiorna ora
            </a>
        </div>
    </div>
<?php elseif ($planType === 'pro'): ?>
    <div class="card bg-warning text-dark">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title text-dark mb-1">Passa al piano Premium</h5>
                <p class="mb-0">Ottieni richieste API illimitate e supporto prioritario.</p>
            </div>
            <a href="index.php?page=subscription" class="btn btn-dark">
                <i class="fas fa-crown me-2"></i> Diventa Premium
            </a>
        </div>
    </div>
<?php endif; ?>
