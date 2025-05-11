<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'users' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/?page=users">
                    <i class="fas fa-users me-2"></i>
                    Gestione Utenti
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'subscriptions' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/?page=subscriptions">
                    <i class="fas fa-credit-card me-2"></i>
                    Gestione Abbonamenti
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'logs' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/?page=logs">
                    <i class="fas fa-history me-2"></i>
                    Log Richieste API
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Amministrazione</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <?php if (isSuperAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page === 'admin-users' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/?page=admin-users">
                        <i class="fas fa-user-shield me-2"></i>
                        Amministratori
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/?page=settings">
                    <i class="fas fa-cog me-2"></i>
                    Impostazioni
                </a>
            </li>
        </ul>
    </div>
</nav>
