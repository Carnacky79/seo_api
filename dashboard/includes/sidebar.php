<nav class="dashboard-sidebar">
    <div class="sidebar-header">
        <div class="user-info">
            <div class="font-semibold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
            <div class="text-muted text-xs"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>
        <div class="subscription-badge <?php echo $planBadgeClass; ?>">
            <?php echo $planName; ?>
        </div>
    </div>

    <ul class="nav-menu">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="index.php?page=api-key" class="nav-link <?php echo $page === 'api-key' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> Chiave API
            </a>
        </li>
        <li class="nav-item">
            <a href="index.php?page=api-tester" class="nav-link <?php echo $page === 'api-tester' ? 'active' : ''; ?>">
                <i class="fas fa-code"></i> Test API
            </a>
        </li>

        <div class="nav-divider"></div>

        <li class="nav-item">
            <a href="index.php?page=subscription" class="nav-link <?php echo $page === 'subscription' ? 'active' : ''; ?>">
                <i class="fas fa-gem"></i> Abbonamento
            </a>
        </li>
        <li class="nav-item">
            <a href="index.php?page=usage-stats" class="nav-link <?php echo $page === 'usage-stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Utilizzo
            </a>
        </li>
        <li class="nav-item">
            <a href="index.php?page=payment-history" class="nav-link <?php echo $page === 'payment-history' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Pagamenti
            </a>
        </li>

        <div class="nav-divider"></div>

        <li class="nav-item">
            <a href="index.php?page=profile" class="nav-link <?php echo $page === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> Profilo
            </a>
        </li>
        <li class="nav-item">
            <a href="index.php?page=documentation" class="nav-link <?php echo $page === 'documentation' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Documentazione
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="version">v1.0.0</div>
        <div>&copy; <?php echo date('Y'); ?> SEO Metadata API</div>
    </div>
</nav>

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay"></div>
