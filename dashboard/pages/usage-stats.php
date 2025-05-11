<?php
// Ottieni i dati dell'utente corrente
$pdo = getDbConnection();

// Determina il periodo di tempo da visualizzare (default: ultimi 6 mesi)
$period = isset($_GET['period']) ? sanitize($_GET['period']) : '6m';

// Calcola le date di inizio e fine in base al periodo selezionato
$endDate = new DateTime();
$startDate = new DateTime();

switch ($period) {
    case '1m':
        $startDate->modify('-1 month');
        $periodLabel = 'Ultimo mese';
        break;
    case '3m':
        $startDate->modify('-3 months');
        $periodLabel = 'Ultimi 3 mesi';
        break;
    case '6m':
        $startDate->modify('-6 months');
        $periodLabel = 'Ultimi 6 mesi';
        break;
    case '1y':
        $startDate->modify('-1 year');
        $periodLabel = 'Ultimo anno';
        break;
    case 'all':
        $startDate->modify('-5 years'); // Un limite ragionevole per "tutti"
        $periodLabel = 'Tutto il periodo';
        break;
    default:
        $startDate->modify('-6 months');
        $periodLabel = 'Ultimi 6 mesi';
        break;
}

// Formatta le date per la query SQL
$startDateStr = $startDate->format('Y-m-d');
$endDateStr = $endDate->format('Y-m-d');

// Calcola i limiti in base al piano di abbonamento
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

// Ottieni statistiche per il mese corrente
$currentMonth = date('n');
$currentYear = date('Y');

$sql = "SELECT request_count FROM api_usage WHERE user_id = ? AND month = ? AND year = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id'], $currentMonth, $currentYear]);
$currentUsage = $stmt->fetch();
$currentMonthRequests = $currentUsage ? (int)$currentUsage['request_count'] : 0;

// Calcola la percentuale di utilizzo
$percentUsed = $planType !== 'premium' ? min(100, ($currentMonthRequests / $requestLimit) * 100) : 0;
$requestsRemaining = $planType !== 'premium' ? max(0, $requestLimit - $currentMonthRequests) : 'Illimitato';

// Statistiche di utilizzo mensile negli ultimi mesi
$sql = "SELECT month, year, request_count 
        FROM api_usage 
        WHERE user_id = ? 
        AND (year > ? OR (year = ? AND month >= ?)) 
        AND (year < ? OR (year = ? AND month <= ?)) 
        ORDER BY year ASC, month ASC";

$startMonth = (int)$startDate->format('n');
$startYear = (int)$startDate->format('Y');
$endMonth = (int)$endDate->format('n');
$endYear = (int)$endDate->format('Y');

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $user['id'],
    $startYear, $startYear, $startMonth,
    $endYear, $endYear, $endMonth
]);
$monthlyUsage = $stmt->fetchAll();

// Prepara i dati per il grafico
$chartLabels = [];
$chartValues = [];
$monthNames = [
    1 => 'Gen', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mag', 6 => 'Giu',
    7 => 'Lug', 8 => 'Ago', 9 => 'Set', 10 => 'Ott', 11 => 'Nov', 12 => 'Dic'
];

// Inizializza un array con tutti i mesi nel periodo selezionato
$currentDate = clone $startDate;
while ($currentDate <= $endDate) {
    $month = (int)$currentDate->format('n');
    $year = (int)$currentDate->format('Y');
    $monthYear = $monthNames[$month] . ' ' . $year;

    $chartLabels[] = $monthYear;
    $chartValues[$monthYear] = 0;

    $currentDate->modify('+1 month');
}

// Popola i valori effettivi
foreach ($monthlyUsage as $usage) {
    $month = (int)$usage['month'];
    $year = (int)$usage['year'];
    $monthYear = $monthNames[$month] . ' ' . $year;

    if (isset($chartValues[$monthYear])) {
        $chartValues[$monthYear] = (int)$usage['request_count'];
    }
}

// Converti in array semplici per il grafico
$chartValues = array_values($chartValues);

// Statistiche aggiuntive
$totalRequests = array_sum($chartValues);
$avgRequestsPerMonth = count($chartValues) > 0 ? round($totalRequests / count($chartValues)) : 0;

// Ottieni le ultime richieste API (più recenti prima)
$sql = "SELECT * FROM request_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$recentRequests = $stmt->fetchAll();

// Preparazione per statistiche sullo stato delle richieste
$statusStats = [
    'success' => 0,  // 200-299
    'redirect' => 0, // 300-399
    'error' => 0,    // 400-599
    'total' => count($recentRequests)
];

// Calcola le metriche dalle richieste recenti
$avgResponseTime = 0;
$totalResponseTime = 0;
$urlStats = [];

foreach ($recentRequests as $request) {
    // Conteggio per stato
    $responseCode = (int)$request['response_code'];
    if ($responseCode >= 200 && $responseCode < 300) {
        $statusStats['success']++;
    } elseif ($responseCode >= 300 && $responseCode < 400) {
        $statusStats['redirect']++;
    } else {
        $statusStats['error']++;
    }

    // Tempo di risposta
    $totalResponseTime += (float)$request['execution_time'];

    // Statistiche URL
    $url = $request['url'];
    if (!isset($urlStats[$url])) {
        $urlStats[$url] = 0;
    }
    $urlStats[$url]++;
}

// Calcola tempo medio di risposta
$avgResponseTime = $statusStats['total'] > 0 ? round($totalResponseTime / $statusStats['total'], 2) : 0;

// Ordina le URL per frequenza
arsort($urlStats);
$topUrls = array_slice($urlStats, 0, 5, true);
?>

<!-- Stats Cards -->
<div class="stats-cards">
    <div class="stats-card">
        <div class="card-icon icon-primary">
            <i class="fas fa-server"></i>
        </div>
        <div class="card-title">Richieste Totali</div>
        <div class="card-value"><?php echo number_format($totalRequests); ?></div>
        <div class="text-muted"><?php echo $periodLabel; ?></div>
    </div>

    <div class="stats-card">
        <div class="card-icon icon-success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="card-title">Richieste Questo Mese</div>
        <div class="card-value"><?php echo number_format($currentMonthRequests); ?></div>
        <div class="text-muted"><?php echo $planType !== 'premium' ? number_format($requestsRemaining) . ' rimanenti' : 'Piano illimitato'; ?></div>
    </div>

    <div class="stats-card">
        <div class="card-icon icon-info">
            <i class="fas fa-clock"></i>
        </div>
        <div class="card-title">Tempo Medio Risposta</div>
        <div class="card-value"><?php echo $avgResponseTime; ?> sec</div>
        <div class="text-muted">nelle ultime 50 richieste</div>
    </div>

    <div class="stats-card">
        <div class="card-icon icon-warning">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="card-title">Media Mensile</div>
        <div class="card-value"><?php echo number_format($avgRequestsPerMonth); ?></div>
        <div class="text-muted">richieste/mese</div>
    </div>
</div>

<!-- Filtri periodo -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Statistiche di Utilizzo</h5>
        <div class="btn-group">
            <a href="?page=usage-stats&period=1m" class="btn btn-sm <?php echo $period === '1m' ? 'btn-primary' : 'btn-outline-primary'; ?>">1 Mese</a>
            <a href="?page=usage-stats&period=3m" class="btn btn-sm <?php echo $period === '3m' ? 'btn-primary' : 'btn-outline-primary'; ?>">3 Mesi</a>
            <a href="?page=usage-stats&period=6m" class="btn btn-sm <?php echo $period === '6m' ? 'btn-primary' : 'btn-outline-primary'; ?>">6 Mesi</a>
            <a href="?page=usage-stats&period=1y" class="btn btn-sm <?php echo $period === '1y' ? 'btn-primary' : 'btn-outline-primary'; ?>">1 Anno</a>
            <a href="?page=usage-stats&period=all" class="btn btn-sm <?php echo $period === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">Tutto</a>
        </div>
    </div>
    <div class="card-body">
        <!-- Grafico di utilizzo mensile -->
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="usageChart"
                    data-labels='<?php echo json_encode($chartLabels); ?>'
                    data-values='<?php echo json_encode($chartValues); ?>'></canvas>
        </div>
    </div>
</div>

<!-- Utilizzo attuale (solo per piani con limite) -->
<?php if ($planType !== 'premium'): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Utilizzo Mensile Attuale</h5>
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
                        <span class="font-semibold"><?php echo number_format($currentMonthRequests); ?></span> di <?php echo number_format($requestLimit); ?> richieste
                    </div>
                    <div>
                        <?php echo number_format($percentUsed, 1); ?>%
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <?php if ($percentUsed > 80 && $planType !== 'premium'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Stai per raggiungere il limite mensile. Considera di passare a un piano superiore.
                    </div>
                    <a href="index.php?page=subscription" class="btn btn-warning">
                        <i class="fas fa-arrow-up me-1"></i> Aggiorna piano
                    </a>
                <?php elseif ($percentUsed > 95 && $planType !== 'premium'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> Hai quasi raggiunto il limite mensile!
                    </div>
                    <a href="index.php?page=subscription" class="btn btn-danger">
                        <i class="fas fa-arrow-up me-1"></i> Aggiorna ora
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Statistiche di Stato -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Stato delle Richieste</h5>
            </div>
            <div class="card-body">
                <?php if ($statusStats['total'] > 0): ?>
                    <div class="mb-4">
                        <canvas id="statusChart" style="max-height: 250px;"></canvas>
                    </div>
                    <div class="d-flex justify-content-around text-center">
                        <div>
                            <div class="text-success font-semibold"><?php echo $statusStats['success']; ?></div>
                            <div class="text-muted">Successo</div>
                        </div>
                        <div>
                            <div class="text-warning font-semibold"><?php echo $statusStats['redirect']; ?></div>
                            <div class="text-muted">Redirect</div>
                        </div>
                        <div>
                            <div class="text-danger font-semibold"><?php echo $statusStats['error']; ?></div>
                            <div class="text-muted">Errore</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Non ci sono dati sufficienti per generare statistiche.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- URL più richiesti -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">URL più Richiesti</h5>
            </div>
            <div class="card-body">
                <?php if (count($topUrls) > 0): ?>
                    <div class="mb-3">
                        <canvas id="urlChart" style="max-height: 250px;"></canvas>
                    </div>
                    <div class="text-center text-muted">
                        Basato sulle ultime 50 richieste
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Non ci sono dati sufficienti per generare statistiche.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Storico richieste recenti -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Richieste API Recenti</h5>
        <button class="btn btn-sm btn-outline-primary" id="refreshRequests">
            <i class="fas fa-sync me-1"></i> Aggiorna
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($recentRequests)): ?>
            <div class="alert alert-info">
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

            <div class="mt-3">
                <a href="api/export-logs.php?format=csv" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-file-csv me-1"></i> Esporta CSV
                </a>
                <a href="api/export-logs.php?format=json" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="fas fa-file-code me-1"></i> Esporta JSON
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Inizializza grafici quando il DOM è pronto
    document.addEventListener('DOMContentLoaded', function() {
        // Grafico di utilizzo mensile
        const usageChartCanvas = document.getElementById('usageChart');
        if (usageChartCanvas) {
            const ctx = usageChartCanvas.getContext('2d');
            const labels = JSON.parse(usageChartCanvas.dataset.labels || '[]');
            const values = JSON.parse(usageChartCanvas.dataset.values || '[]');

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Richieste API',
                        data: values,
                        backgroundColor: 'rgba(37, 99, 235, 0.5)',
                        borderColor: 'rgb(37, 99, 235)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Grafico stato richieste
        const statusChartCanvas = document.getElementById('statusChart');
        if (statusChartCanvas) {
            const ctx = statusChartCanvas.getContext('2d');

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Successo', 'Redirect', 'Errore'],
                    datasets: [{
                        data: [
                            <?php echo $statusStats['success']; ?>,
                            <?php echo $statusStats['redirect']; ?>,
                            <?php echo $statusStats['error']; ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.6)',
                            'rgba(245, 158, 11, 0.6)',
                            'rgba(239, 68, 68, 0.6)'
                        ],
                        borderColor: [
                            'rgb(16, 185, 129)',
                            'rgb(245, 158, 11)',
                            'rgb(239, 68, 68)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Grafico URL più richiesti
        const urlChartCanvas = document.getElementById('urlChart');
        if (urlChartCanvas) {
            const ctx = urlChartCanvas.getContext('2d');

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php
                        foreach (array_keys($topUrls) as $url) {
                            echo "'" . substr(htmlspecialchars($url), 0, 30) . (strlen($url) > 30 ? '...' : '') . "',";
                        }
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php
                            foreach ($topUrls as $count) {
                                echo $count . ',';
                            }
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(37, 99, 235, 0.6)',
                            'rgba(59, 130, 246, 0.6)',
                            'rgba(96, 165, 250, 0.6)',
                            'rgba(147, 197, 253, 0.6)',
                            'rgba(191, 219, 254, 0.6)'
                        ],
                        borderColor: [
                            'rgb(37, 99, 235)',
                            'rgb(59, 130, 246)',
                            'rgb(96, 165, 250)',
                            'rgb(147, 197, 253)',
                            'rgb(191, 219, 254)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw + ' richieste';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Aggiorna la tabella delle richieste recenti
        document.getElementById('refreshRequests')?.addEventListener('click', function() {
            window.location.reload();
        });
    });
</script>
