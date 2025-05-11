<?php
// Ottieni le statistiche dal database
$pdo = getDbConnection();

// Conteggio utenti totali
$sql = "SELECT COUNT(*) as total FROM users";
$stmt = $pdo->query($sql);
$totalUsers = $stmt->fetch()['total'];

// Conteggio utenti attivi
$sql = "SELECT COUNT(*) as active FROM users WHERE status = 'active'";
$stmt = $pdo->query($sql);
$activeUsers = $stmt->fetch()['active'];

// Conteggio richieste API totali
$sql = "SELECT SUM(request_count) as total FROM api_usage";
$stmt = $pdo->query($sql);
$totalRequests = $stmt->fetch()['total'] ?? 0;

// Conteggio abbonamenti per tipo
$sql = "SELECT plan_type, COUNT(*) as count FROM subscriptions WHERE status = 'active' GROUP BY plan_type";
$stmt = $pdo->query($sql);
$subscriptions = $stmt->fetchAll();

// Formatta i dati degli abbonamenti per uso più semplice
$subscriptionCounts = [
    'free' => 0,
    'pro' => 0,
    'premium' => 0
];

foreach ($subscriptions as $sub) {
    $subscriptionCounts[$sub['plan_type']] = $sub['count'];
}

// Ottieni le registrazioni recenti
$sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.created_at, u.status, u.email_verified 
        FROM users u 
        ORDER BY u.created_at DESC 
        LIMIT 5";
$stmt = $pdo->query($sql);
$recentUsers = $stmt->fetchAll();

// Ottieni le ultime richieste API
$sql = "SELECT r.id, r.url, r.execution_time, r.created_at, u.email 
        FROM request_logs r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC 
        LIMIT 5";
$stmt = $pdo->query($sql);
$recentRequests = $stmt->fetchAll();
?>

<!-- Schede di riepilogo -->
<div class="row">
    <!-- Utenti totali -->
    <div class="col-md-3 mb-4">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Utenti Totali</h5>
                        <h2 class="mb-0"><?php echo number_format($totalUsers); ?></h2>
                    </div>
                    <div class="card-icon bg-primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <p class="card-text text-muted mt-3">
                    <i class="fas fa-check-circle me-1"></i> <?php echo number_format($activeUsers); ?> attivi
                </p>
            </div>
        </div>
    </div>

    <!-- Richieste API -->
    <div class="col-md-3 mb-4">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Richieste API</h5>
                        <h2 class="mb-0"><?php echo number_format($totalRequests); ?></h2>
                    </div>
                    <div class="card-icon bg-success">
                        <i class="fas fa-server"></i>
                    </div>
                </div>
                <p class="card-text text-muted mt-3">
                    <i class="fas fa-chart-line me-1"></i> Totale richieste
                </p>
            </div>
        </div>
    </div>

    <!-- Abbonamenti Pro -->
    <div class="col-md-3 mb-4">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Abbonamenti Pro</h5>
                        <h2 class="mb-0"><?php echo number_format($subscriptionCounts['pro']); ?></h2>
                    </div>
                    <div class="card-icon bg-info">
                        <i class="fas fa-gem"></i>
                    </div>
                </div>
                <p class="card-text text-muted mt-3">
                    <i class="fas fa-euro-sign me-1"></i> <?php echo $subscriptionCounts['pro'] * 20; ?> € / mese
                </p>
            </div>
        </div>
    </div>

    <!-- Abbonamenti Premium -->
    <div class="col-md-3 mb-4">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Abbonamenti Premium</h5>
                        <h2 class="mb-0"><?php echo number_format($subscriptionCounts['premium']); ?></h2>
                    </div>
                    <div class="card-icon bg-warning">
                        <i class="fas fa-crown"></i>
                    </div>
                </div>
                <p class="card-text text-muted mt-3">
                    <i class="fas fa-euro-sign me-1"></i> <?php echo $subscriptionCounts['premium'] * 50; ?> € / mese
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Grafici -->
<div class="row mb-4">
    <!-- Grafico registrazioni -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                Nuove Registrazioni (ultimi 30 giorni)
            </div>
            <div class="card-body">
                <canvas id="registrationsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafico abbonamenti -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                Distribuzione Abbonamenti
            </div>
            <div class="card-body">
                <canvas id="subscriptionsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Richieste API negli ultimi 7 giorni -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                Richieste API (ultimi 7 giorni)
            </div>
            <div class="card-body">
                <canvas id="apiRequestsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tabelle di dati recenti -->
<div class="row">
    <!-- Utenti recenti -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Utenti Recenti</span>
                <a href="<?php echo ADMIN_URL; ?>/?page=users" class="btn btn-sm btn-outline-primary">Vedi tutti</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Stato</th>
                            <th>Registrato il</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                    <?php if ($user['email_verified'] == 1): ?>
                                        <i class="fas fa-check-circle text-success ms-1" title="Email verificata"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-danger ms-1" title="Email non verificata"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php elseif ($user['status'] === 'inactive'): ?>
                                        <span class="badge bg-secondary">Inattivo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Sospeso</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <a href="<?php echo ADMIN_URL; ?>/?page=user-details&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentUsers)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Nessun utente trovato</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Richieste API recenti -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Richieste API Recenti</span>
                <a href="<?php echo ADMIN_URL; ?>/?page=logs" class="btn btn-sm btn-outline-primary">Vedi tutte</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                        <tr>
                            <th>URL</th>
                            <th>Utente</th>
                            <th>Tempo</th>
                            <th>Data</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentRequests as $request): ?>
                            <tr>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($request['url']); ?>">
                                        <?php echo htmlspecialchars($request['url']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($request['email']); ?></td>
                                <td><?php echo round($request['execution_time'], 2); ?> sec</td>
                                <td><?php echo formatDate($request['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentRequests)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Nessuna richiesta trovata</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
