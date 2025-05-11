<?php
// Parametri paginazione
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 20;
if ($perPage <= 0) $perPage = 20;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $perPage;

// Filtri di ricerca
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$verified = isset($_GET['verified']) ? (int)$_GET['verified'] : -1;
$planType = isset($_GET['plan_type']) ? sanitize($_GET['plan_type']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$sortField = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'id';
$sortDirection = isset($_GET['dir']) ? sanitize($_GET['dir']) : 'desc';

// Valida i campi di ordinamento permessi
$allowedSortFields = ['id', 'email', 'first_name', 'created_at', 'status'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'id';
}

// Valida la direzione di ordinamento
if (!in_array($sortDirection, ['asc', 'desc'])) {
    $sortDirection = 'desc';
}

// Costruisci la query SQL con i filtri
$sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.fiscal_code, 
               u.created_at, u.status, u.email_verified, u.company, u.phone,
               s.plan_type
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active' 
        WHERE 1=1";

$countSql = "SELECT COUNT(u.id) as total
             FROM users u
             LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
             WHERE 1=1";

$params = [];
$countParams = [];

// Applica i filtri
if (!empty($search)) {
    $searchCondition = " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.fiscal_code LIKE ?)";
    $sql .= $searchCondition;
    $countSql .= $searchCondition;
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($status)) {
    $statusCondition = " AND u.status = ?";
    $sql .= $statusCondition;
    $countSql .= $statusCondition;
    $params[] = $status;
    $countParams[] = $status;
}

if ($verified !== -1) {
    $verifiedCondition = " AND u.email_verified = ?";
    $sql .= $verifiedCondition;
    $countSql .= $verifiedCondition;
    $params[] = $verified;
    $countParams[] = $verified;
}

if (!empty($planType)) {
    $planCondition = " AND s.plan_type = ?";
    $sql .= $planCondition;
    $countSql .= $planCondition;
    $params[] = $planType;
    $countParams[] = $planType;
}

if (!empty($dateFrom)) {
    $dateFromCondition = " AND u.created_at >= ?";
    $sql .= $dateFromCondition;
    $countSql .= $dateFromCondition;
    $params[] = $dateFrom . ' 00:00:00';
    $countParams[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $dateToCondition = " AND u.created_at <= ?";
    $sql .= $dateToCondition;
    $countSql .= $dateToCondition;
    $params[] = $dateTo . ' 23:59:59';
    $countParams[] = $dateTo . ' 23:59:59';
}

// Aggiungi ordinamento
$sql .= " ORDER BY u.$sortField $sortDirection";

// Aggiungi paginazione
$sql .= " LIMIT $perPage OFFSET $offset";

// Esegui le query
$pdo = getDbConnection();

// Query per il conteggio totale
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalCount = $countStmt->fetch()['total'];

// Calcola il numero totale di pagine
$totalPages = ceil($totalCount / $perPage);

// Query per i dati degli utenti
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Query per le statistiche generali
$statsSql = "SELECT
             (SELECT COUNT(*) FROM users) as total_users,
             (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
             (SELECT COUNT(*) FROM users WHERE email_verified = 1) as verified_users,
             (SELECT COUNT(*) FROM users u JOIN subscriptions s ON u.id = s.user_id WHERE s.plan_type IN ('pro', 'premium') AND s.status = 'active') as paid_users,
             (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_users";

$statsStmt = $pdo->query($statsSql);
$stats = $statsStmt->fetch();

// Funzione per generare l'URL con i parametri di ordinamento
function getSortUrl($field) {
    global $sortField, $sortDirection;

    $newDirection = 'asc';
    if ($field === $sortField && $sortDirection === 'asc') {
        $newDirection = 'desc';
    }

    $params = $_GET;
    $params['sort'] = $field;
    $params['dir'] = $newDirection;

    return '?' . http_build_query($params);
}

// Funzione per generare l'URL con i parametri di paginazione
function getPaginationUrl($pageNum) {
    $params = $_GET;
    $params['p'] = $pageNum;

    return '?' . http_build_query($params);
}

// Funzione per mostrare l'icona di ordinamento
function getSortIcon($field) {
    global $sortField, $sortDirection;

    if ($field === $sortField) {
        return $sortDirection === 'asc' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
    }

    return '<i class="fas fa-sort ms-1 text-muted"></i>';
}
?>

<!-- Alert per notifiche -->
<div id="alertContainer"></div>

<!-- Pannello statistiche -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Utenti totali</h5>
                <h2 class="mb-0"><?php echo number_format($stats['total_users']); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Utenti attivi</h5>
                <h2 class="mb-0"><?php echo number_format($stats['active_users']); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Email verificate</h5>
                <h2 class="mb-0"><?php echo number_format($stats['verified_users']); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Abbonamenti</h5>
                <h2 class="mb-0"><?php echo number_format($stats['paid_users']); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Nuovi utenti (30 giorni)</h5>
                <h2 class="mb-0"><?php echo number_format($stats['new_users']); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Filtri di ricerca -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter me-1"></i> Filtri di ricerca
    </div>
    <div class="card-body">
        <form method="get" action="" id="filterForm">
            <input type="hidden" name="page" value="users">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortField); ?>">
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars($sortDirection); ?>">

            <div class="row g-3">
                <!-- Prima riga di filtri -->
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Cerca (email, nome, codice fiscale)"
                               name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-2">
                    <select class="form-select" name="status" onchange="document.getElementById('filterForm').submit()">
                        <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>Tutti gli stati</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Attivi</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inattivi</option>
                        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Sospesi</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select" name="verified" onchange="document.getElementById('filterForm').submit()">
                        <option value="-1" <?php echo $verified === -1 ? 'selected' : ''; ?>>Verifica email</option>
                        <option value="1" <?php echo $verified === 1 ? 'selected' : ''; ?>>Verificati</option>
                        <option value="0" <?php echo $verified === 0 ? 'selected' : ''; ?>>Non verificati</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select" name="plan_type" onchange="document.getElementById('filterForm').submit()">
                        <option value="" <?php echo $planType === '' ? 'selected' : ''; ?>>Tutti i piani</option>
                        <option value="free" <?php echo $planType === 'free' ? 'selected' : ''; ?>>Gratuito</option>
                        <option value="pro" <?php echo $planType === 'pro' ? 'selected' : ''; ?>>Pro</option>
                        <option value="premium" <?php echo $planType === 'premium' ? 'selected' : ''; ?>>Premium</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select" name="perPage" onchange="document.getElementById('filterForm').submit()">
                        <option value="20" <?php echo $perPage === 20 ? 'selected' : ''; ?>>20 per pagina</option>
                        <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50 per pagina</option>
                        <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100 per pagina</option>
                    </select>
                </div>

                <!-- Seconda riga di filtri (data) -->
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text">Dal</span>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text">Al</span>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Applica filtri</button>
                </div>

                <div class="col-md-2">
                    <a href="<?php echo ADMIN_URL; ?>/?page=users" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-1"></i> Reset
                    </a>
                </div>

                <div class="col-md-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-primary w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog me-1"></i> Azioni di massa
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#bulkVerifyModal">
                                    <i class="fas fa-envelope-open-text me-2"></i> Verifica email in bulk
                                </a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#bulkChangeStatusModal">
                                    <i class="fas fa-user-tag me-2"></i> Cambia stato in bulk
                                </a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#bulkChangePlanModal">
                                    <i class="fas fa-gem me-2"></i> Cambia piano in bulk
                                </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#exportUsersModal">
                                    <i class="fas fa-file-export me-2"></i> Esporta utenti
                                </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Visualizzazione risultati -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-users me-1"></i>
            Utenti trovati: <strong><?php echo $totalCount; ?></strong>
            <?php if (!empty($search) || !empty($status) || $verified !== -1 || !empty($planType) || !empty($dateFrom) || !empty($dateTo)): ?>
                <span class="badge bg-info ms-2">Filtri attivi</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Nessun utente trovato con i criteri di ricerca specificati.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <th style="width: 40px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </div>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('id'); ?>" class="text-decoration-none text-dark">
                                ID <?php echo getSortIcon('id'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('first_name'); ?>" class="text-decoration-none text-dark">
                                Nome <?php echo getSortIcon('first_name'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('email'); ?>" class="text-decoration-none text-dark">
                                Email <?php echo getSortIcon('email'); ?>
                            </a>
                        </th>
                        <th>Codice Fiscale</th>
                        <th>Contatti</th>
                        <th>Piano</th>
                        <th>
                            <a href="<?php echo getSortUrl('status'); ?>" class="text-decoration-none text-dark">
                                Stato <?php echo getSortIcon('status'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('created_at'); ?>" class="text-decoration-none text-dark">
                                Registrato <?php echo getSortIcon('created_at'); ?>
                            </a>
                        </th>
                        <th style="width: 120px;">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input user-checkbox" type="checkbox" value="<?php echo $user['id']; ?>">
                                </div>
                            </td>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white bg-secondary text-center me-2" style="width: 36px; height: 36px; line-height: 36px;">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['email']); ?>
                                <?php if ($user['email_verified'] == 1): ?>
                                    <i class="fas fa-check-circle text-success ms-1" title="Email verificata"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger ms-1" title="Email non verificata"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['fiscal_code']); ?></td>
                            <td>
                                <?php if (!empty($user['phone'])): ?>
                                    <small class="text-muted"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($user['phone']); ?></small><br>
                                <?php endif; ?>
                                <?php if (!empty($user['company'])): ?>
                                    <small class="text-muted"><i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($user['company']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['plan_type'] === 'free'): ?>
                                    <span class="badge bg-secondary">Gratuito</span>
                                <?php elseif ($user['plan_type'] === 'pro'): ?>
                                    <span class="badge bg-info">Pro</span>
                                <?php elseif ($user['plan_type'] === 'premium'): ?>
                                    <span class="badge bg-warning text-dark">Premium</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark">Nessuno</span>
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
                                <div class="btn-group">
                                    <a href="<?php echo ADMIN_URL; ?>/?page=user-details&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary" title="Visualizza dettagli">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <li><a class="dropdown-item text-warning" href="#" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'suspended'); return false;">
                                                    <i class="fas fa-ban me-2"></i> Sospendi
                                                </a></li>
                                        <?php elseif ($user['status'] === 'suspended'): ?>
                                            <li><a class="dropdown-item text-success" href="#" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'active'); return false;">
                                                    <i class="fas fa-check me-2"></i> Riattiva
                                                </a></li>
                                        <?php endif; ?>
                                        <?php if ($user['email_verified'] == 0): ?>
                                            <li><a class="dropdown-item" href="#" onclick="verifyEmail(<?php echo $user['id']; ?>); return false;">
                                                    <i class="fas fa-envelope-open-text me-2"></i> Verifica Email
                                                </a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="#" onclick="showChangeSubscription(<?php echo $user['id']; ?>, '<?php echo $user['plan_type']; ?>'); return false;">
                                                <i class="fas fa-exchange-alt me-2"></i> Cambia Piano
                                            </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'inactive'); return false;">
                                                <i class="fas fa-trash me-2"></i> Disattiva
                                            </a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Paginazione" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo getPaginationUrl($page - 1); ?>" tabindex="-1" aria-disabled="<?php echo ($page <= 1) ? 'true' : 'false'; ?>">Precedente</a>
                        </li>

                        <?php
                        // Determina l'intervallo di pagine da mostrare
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        // Mostra sempre la prima pagina
                        if ($startPage > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl(1) . '">1</a></li>';
                            if ($startPage > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        // Mostra le pagine nell'intervallo
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="' . getPaginationUrl($i) . '">' . $i . '</a></li>';
                        }

                        // Mostra sempre l'ultima pagina
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl($totalPages) . '">' . $totalPages . '</a></li>';
                        }
                        ?>

                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo getPaginationUrl($page + 1); ?>" aria-disabled="<?php echo ($page >= $totalPages) ? 'true' : 'false'; ?>">Successiva</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

            <div class="text-center text-muted mt-3">
                Mostrando <?php echo count($users); ?> su <?php echo $totalCount; ?> utenti totali
                (Pagina <?php echo $page; ?> di <?php echo $totalPages; ?>)
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modali per azioni bulk -->
<!-- Modal per verifica email in bulk -->
<div class="modal fade" id="bulkVerifyModal" tabindex="-1" aria-labelledby="bulkVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkVerifyModalLabel">Verifica Email in Bulk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler verificare manualmente tutte le email degli utenti selezionati?</p>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Questa azione salterà il normale processo di verifica dell'email.
                </p>
                <div id="bulkVerifyUsers" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="confirmBulkVerify">Conferma</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per cambiare stato in bulk -->
<div class="modal fade" id="bulkChangeStatusModal" tabindex="-1" aria-labelledby="bulkChangeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkChangeStatusModalLabel">Cambia Stato in Bulk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="bulkStatus" class="form-label">Nuovo Stato</label>
                    <select class="form-select" id="bulkStatus">
                        <option value="active">Attivo</option>
                        <option value="suspended">Sospeso</option>
                        <option value="inactive">Inattivo</option>
                    </select>
                </div>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Questa azione cambierà lo stato di tutti gli utenti selezionati.
                </p>
                <div id="bulkStatusUsers" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="confirmBulkStatus">Conferma</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per cambiare piano in bulk -->
<div class="modal fade" id="bulkChangePlanModal" tabindex="-1" aria-labelledby="bulkChangePlanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkChangePlanModalLabel">Cambia Piano in Bulk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="bulkPlan" class="form-label">Nuovo Piano</label>
                    <select class="form-select" id="bulkPlan">
                        <option value="free">Gratuito (10 richieste/mese)</option>
                        <option value="pro">Pro (1.000 richieste/mese)</option>
                        <option value="premium">Premium (richieste illimitate)</option>
                    </select>
                </div>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Questa azione cambierà il piano di abbonamento di tutti gli utenti selezionati.
                </p>
                <div id="bulkPlanUsers" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="confirmBulkPlan">Conferma</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per esportazione utenti -->
<div class="modal fade" id="exportUsersModal" tabindex="-1" aria-labelledby="exportUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportUsersModalLabel">Esporta Utenti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="exportFormat" class="form-label">Formato</label>
                    <select class="form-select" id="exportFormat">
                        <option value="csv">CSV</option>
                        <option value="json">JSON</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="exportSelection" checked>
                        <label class="form-check-label" for="exportSelection">
                            Esporta solo utenti selezionati
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="exportAll">
                        <label class="form-check-label" for="exportAll">
                            Esporta tutti i dati (inclusi dettagli sensibili)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="confirmExport">Esporta</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per cambiare piano singolo utente -->
<div class="modal fade" id="changeSubscriptionModal" tabindex="-1" aria-labelledby="changeSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeSubscriptionModalLabel">Cambia Piano Abbonamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="changeSubscriptionForm">
                    <input type="hidden" id="subscriptionUserId" name="user_id">
                    <div class="mb-3">
                        <label for="subscriptionPlanType" class="form-label">Nuovo Piano</label>
                        <select class="form-select" id="subscriptionPlanType" name="plan_type">
                            <option value="free">Gratuito (10 richieste/mese)</option>
                            <option value="pro">Pro (1.000 richieste/mese)</option>
                            <option value="premium">Premium (richieste illimitate)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="confirmChangeSubscription">Salva</button>
            </div>
        </div>
    </div>
</div>

<!-- Script JavaScript per la pagina -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestione checkbox "seleziona tutti"
        const selectAllCheckbox = document.getElementById('selectAll');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }

        // Funzione per mostrare alert
        window.showAlert = function(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

            alertContainer.appendChild(alertDiv);

            // Auto-chiusura dopo 5 secondi
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => {
                    alertContainer.removeChild(alertDiv);
                }, 150);
            }, 5000);
        };

        // Funzione per ottenere gli ID degli utenti selezionati
        function getSelectedUserIds() {
            const selected = [];
            userCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selected.push(parseInt(checkbox.value));
                }
            });
            return selected;
        }

        // Preparazione modali per azioni bulk
        const bulkVerifyModal = document.getElementById('bulkVerifyModal');
        if (bulkVerifyModal) {
            bulkVerifyModal.addEventListener('show.bs.modal', function (event) {
                const selectedIds = getSelectedUserIds();
                const bulkVerifyUsers = document.getElementById('bulkVerifyUsers');

                if (selectedIds.length === 0) {
                    bulkVerifyUsers.innerHTML = '<div class="alert alert-warning mb-0">Nessun utente selezionato</div>';
                    document.getElementById('confirmBulkVerify').disabled = true;
                } else {
                    bulkVerifyUsers.innerHTML = `<div class="alert alert-info mb-0">Verifica ${selectedIds.length} utenti selezionati</div>`;
                    document.getElementById('confirmBulkVerify').disabled = false;
                }
            });

            document.getElementById('confirmBulkVerify').addEventListener('click', function() {
                const selectedIds = getSelectedUserIds();

                if (selectedIds.length === 0) return;

                // Chiudi il modal
                bootstrap.Modal.getInstance(bulkVerifyModal).hide();

                // Esegui l'azione per ogni ID utente
                let completedCount = 0;
                let errorCount = 0;

                selectedIds.forEach(userId => {
                    fetch('api/users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'verify_email',
                            user_id: userId
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                completedCount++;
                            } else {
                                errorCount++;
                            }

                            // Quando tutte le richieste sono completate
                            if (completedCount + errorCount === selectedIds.length) {
                                showAlert(`Email verificate con successo: ${completedCount}. Errori: ${errorCount}.`, errorCount ? 'warning' : 'success');
                                if (completedCount > 0) {
                                    setTimeout(() => { window.location.reload(); }, 2000);
                                }
                            }
                        })
                        .catch(error => {
                            errorCount++;
                            if (completedCount + errorCount === selectedIds.length) {
                                showAlert(`Email verificate con successo: ${completedCount}. Errori: ${errorCount}.`, 'warning');
                                if (completedCount > 0) {
                                    setTimeout(() => { window.location.reload(); }, 2000);
                                }
                            }
                        });
                });
            });
        }

        // Modal per cambio stato in bulk
        const bulkChangeStatusModal = document.getElementById('bulkChangeStatusModal');
        if (bulkChangeStatusModal) {
            bulkChangeStatusModal.addEventListener('show.bs.modal', function (event) {
                const selectedIds = getSelectedUserIds();
                const bulkStatusUsers = document.getElementById('bulkStatusUsers');

                if (selectedIds.length === 0) {
                    bulkStatusUsers.innerHTML = '<div class="alert alert-warning mb-0">Nessun utente selezionato</div>';
                    document.getElementById('confirmBulkStatus').disabled = true;
                } else {
                    bulkStatusUsers.innerHTML = `<div class="alert alert-info mb-0">Modifica stato di ${selectedIds.length} utenti selezionati</div>`;
                    document.getElementById('confirmBulkStatus').disabled = false;
                }
            });

            document.getElementById('confirmBulkStatus').addEventListener('click', function() {
                const selectedIds = getSelectedUserIds();
                const newStatus = document.getElementById('bulkStatus').value;

                if (selectedIds.length === 0) return;

                // Chiudi il modal
                bootstrap.Modal.getInstance(bulkChangeStatusModal).hide();

                // Esegui l'azione per ogni ID utente
                let completedCount = 0;
                let errorCount = 0;

                selectedIds.forEach(userId => {
                    fetch('api/users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'change_status',
                            user_id: userId,
                            status: newStatus
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                completedCount++;
                            } else {
                                errorCount++;
                            }

                            // Quando tutte le richieste sono completate
                            if (completedCount + errorCount === selectedIds.length) {
                                showAlert(`Stato aggiornato con successo per ${completedCount} utenti. Errori: ${errorCount}.`, errorCount ? 'warning' : 'success');
                                if (completedCount > 0) {
                                    setTimeout(() => { window.location.reload(); }, 2000);
                                }
                            }
                        })
                        .catch(error => {
                            errorCount++;
                            if (completedCount + errorCount === selectedIds.length) {
                                showAlert(`Stato aggiornato con successo per ${completedCount} utenti. Errori: ${errorCount}.`, 'warning');
                                if (completedCount > 0) {
                                    setTimeout(() => { window.location.reload(); }, 2000);
                                }
                            }
                        });
                });
            });
        }

        // Modal per cambio piano in bulk
        const bulkChangePlanModal = document.getElementById('bulkChangePlanModal');
        if (bulkChangePlanModal) {
            bulkChangePlanModal.addEventListener('show.bs.modal', function (event) {
                const selectedIds = getSelectedUserIds();
                const bulkPlanUsers = document.getElementById('bulkPlanUsers');

                if (selectedIds.length === 0) {
                    bulkPlanUsers.innerHTML = '<div class="alert alert-warning mb-0">Nessun utente selezionato</div>';
                    document.getElementById('confirmBulkPlan').disabled = true;
                } else {
                    bulkPlanUsers.innerHTML = `<div class="alert alert-info mb-0">Modifica piano di ${selectedIds.length} utenti selezionati</div>`;
                    document.getElementById('confirmBulkPlan').disabled = false;
                }
            });

            document.getElementById('confirmBulkPlan').addEventListener('click', function() {
                const selectedIds = getSelectedUserIds();
                const newPlan = document.getElementById('bulkPlan').value;

                if (selectedIds.length === 0) return;

                // Chiudi il modal
                bootstrap.Modal.getInstance(bulkChangePlanModal).hide();

                // Esegui l'azione per ogni ID utente
                let completedCount = 0;
                let errorCount = 0;

                selectedIds.forEach(userId => {
                    fetch('api/subscriptions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'update_subscription',
                            user_id: userId,
                            plan_type: newPlan
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                completedCount++;
                            } else {
                                errorCount++;
                            }

                            // Quando tutte le richieste sono completate
                            if (completedCount + errorCount === selectedIds.length) {
                                showAlert(`Piano aggiornato con successo per ${completedCount} utenti. Errori: ${errorCount}.`, errorCount ? 'warning' : 'success');
                                if (completedCount > 0) {
                                    setTimeout(() => { window.location.reload(); }, 2000);
                                }
                            }
                        })
                        .catch(error => {
                            errorCount++;
                            if (completedCount + errorCount === selectedIds.length) {
                                showAlert(`Piano aggiornato con successo per ${completedCount} utenti. Errori: ${errorCount}.`, 'warning');
                                if (completedCount > 0) {
                                    setTimeout(() => { window.location.reload(); }, 2000);
                                }
                            }
                        });
                });
            });
        }

        // Modal per cambio abbonamento singolo utente
        window.showChangeSubscription = function(userId, currentPlan) {
            const modal = new bootstrap.Modal(document.getElementById('changeSubscriptionModal'));
            document.getElementById('subscriptionUserId').value = userId;

            // Seleziona il piano corrente
            const planSelect = document.getElementById('subscriptionPlanType');
            for (let i = 0; i < planSelect.options.length; i++) {
                if (planSelect.options[i].value === currentPlan) {
                    planSelect.selectedIndex = i;
                    break;
                }
            }

            modal.show();
        };

        document.getElementById('confirmChangeSubscription').addEventListener('click', function() {
            const userId = document.getElementById('subscriptionUserId').value;
            const planType = document.getElementById('subscriptionPlanType').value;

            fetch('api/subscriptions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_subscription',
                    user_id: userId,
                    plan_type: planType
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showAlert('Piano abbonamento aggiornato con successo', 'success');
                        setTimeout(() => { window.location.reload(); }, 1500);
                    } else {
                        showAlert('Errore durante l\'aggiornamento del piano: ' + data.message, 'danger');
                    }

                    // Chiudi il modal
                    bootstrap.Modal.getInstance(document.getElementById('changeSubscriptionModal')).hide();
                })
                .catch(error => {
                    showAlert('Errore di comunicazione con il server', 'danger');
                    bootstrap.Modal.getInstance(document.getElementById('changeSubscriptionModal')).hide();
                });
        });

        // Esportazione utenti
        document.getElementById('confirmExport').addEventListener('click', function() {
            const format = document.getElementById('exportFormat').value;
            const exportSelection = document.getElementById('exportSelection').checked;
            const exportAll = document.getElementById('exportAll').checked;

            let userIds = [];
            if (exportSelection) {
                userIds = getSelectedUserIds();
                if (userIds.length === 0) {
                    showAlert('Nessun utente selezionato per l\'esportazione', 'warning');
                    return;
                }
            }

            // Costruisci URL per l'esportazione
            const params = new URLSearchParams();
            params.append('action', 'export_users');
            params.append('format', format);
            params.append('all_data', exportAll ? '1' : '0');

            if (exportSelection && userIds.length > 0) {
                params.append('user_ids', JSON.stringify(userIds));
            }

            // Usa i filtri correnti
            if (document.querySelector('input[name="search"]').value) {
                params.append('search', document.querySelector('input[name="search"]').value);
            }
            if (document.querySelector('select[name="status"]').value) {
                params.append('status', document.querySelector('select[name="status"]').value);
            }
            if (document.querySelector('select[name="verified"]').value != -1) {
                params.append('verified', document.querySelector('select[name="verified"]').value);
            }
            if (document.querySelector('select[name="plan_type"]').value) {
                params.append('plan_type', document.querySelector('select[name="plan_type"]').value);
            }
            if (document.querySelector('input[name="date_from"]').value) {
                params.append('date_from', document.querySelector('input[name="date_from"]').value);
            }
            if (document.querySelector('input[name="date_to"]').value) {
                params.append('date_to', document.querySelector('input[name="date_to"]').value);
            }

            // Reindirizza all'endpoint di esportazione
            window.location.href = 'api/export.php?' + params.toString();

            // Chiudi il modal
            bootstrap.Modal.getInstance(document.getElementById('exportUsersModal')).hide();
        });
    });

    // Funzioni globali per le azioni sugli utenti
    function changeUserStatus(userId, newStatus) {
        if (!confirm('Sei sicuro di voler cambiare lo stato dell\'utente?')) {
            return;
        }

        fetch('api/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'change_status',
                user_id: userId,
                status: newStatus
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('Stato dell\'utente aggiornato con successo', 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showAlert('Errore: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Errore durante la comunicazione con il server', 'danger');
            });
    }

    function verifyEmail(userId) {
        if (!confirm('Sei sicuro di voler verificare manualmente l\'email di questo utente?')) {
            return;
        }

        fetch('api/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'verify_email',
                user_id: userId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('Email verificata con successo', 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showAlert('Errore: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Errore durante la comunicazione con il server', 'danger');
            });
    }
</script>
