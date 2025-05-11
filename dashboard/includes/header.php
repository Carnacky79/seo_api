<?php
// Ottieni i dati dell'utente corrente
$user = getCurrentUser();
$subscription = getCurrentSubscription();

// Determina il tipo di piano
$planType = $subscription ? $subscription['plan_type'] : 'free';
$planName = '';
$planBadgeClass = '';

switch ($planType) {
    case 'free':
        $planName = 'Gratuito';
        $planBadgeClass = 'badge-free';
        break;
    case 'pro':
        $planName = 'Pro';
        $planBadgeClass = 'badge-pro';
        break;
    case 'premium':
        $planName = 'Premium';
        $planBadgeClass = 'badge-premium';
        break;
}

// Imposta il titolo della pagina
$pageTitle = 'Dashboard';
switch ($page) {
    case 'profile':
        $pageTitle = 'Profilo Utente';
        break;
    case 'api-key':
        $pageTitle = 'Chiave API';
        break;
    case 'subscription':
        $pageTitle = 'Abbonamento';
        break;
    case 'payment-history':
        $pageTitle = 'Storico Pagamenti';
        break;
    case 'usage-stats':
        $pageTitle = 'Statistiche di Utilizzo';
        break;
    case 'api-tester':
        $pageTitle = 'Test API';
        break;
    case 'documentation':
        $pageTitle = 'Documentazione';
        break;
}

// Prepara l'avatar dell'utente (prime lettere di nome e cognome)
$avatarInitials = mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1);
$avatarInitials = mb_strtoupper($avatarInitials);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - SEO Metadata API</title>

    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/mobile.css">

    <?php if ($page === 'api-tester'): ?>
        <!-- Syntax highlighting per API Tester -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <?php endif; ?>

    <?php if ($page === 'usage-stats'): ?>
        <!-- Chart.js per le statistiche -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body>
<!-- Contenitore notifiche -->
<div id="notificationContainer" class="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 1050; max-width: 350px;"></div>

<!-- Header -->
<header class="dashboard-header">
    <button class="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <a href="index.php" class="logo">
        <img src="assets/img/logo.png" alt="SEO Metadata API">
        <span>SEO Metadata API</span>
    </a>

    <div class="header-content">
        <div class="user-dropdown">
            <button class="user-menu" type="button">
                <div class="user-avatar"><?php echo $avatarInitials; ?></div>
                <span><?php echo htmlspecialchars($user['first_name']); ?></span>
                <i class="fas fa-chevron-down ms-2"></i>
            </button>

            <div class="dropdown-menu">
                <a href="index.php?page=profile" class="dropdown-item">
                    <i class="fas fa-user"></i> Profilo
                </a>
                <a href="index.php?page=api-key" class="dropdown-item">
                    <i class="fas fa-key"></i> Chiave API
                </a>
                <a href="index.php?page=subscription" class="dropdown-item">
                    <i class="fas fa-gem"></i> Abbonamento
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main content -->
    <main class="dashboard-content">
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show alert-flash" role="alert">
                <?php echo $_SESSION['flash_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <h1 class="page-title"><?php echo $pageTitle; ?></h1>
