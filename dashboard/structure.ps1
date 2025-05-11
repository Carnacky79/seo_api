# Imposta la directory di lavoro corrente
$basePath = Get-Location

# Definisci la struttura delle cartelle
$folders = @(
    "assets/css",
    "assets/js",
    "assets/img/icons",
    "includes",
    "api",
    "pages"
)

# Crea le cartelle
foreach ($folder in $folders) {
    $fullPath = Join-Path $basePath $folder
    if (-not (Test-Path $fullPath)) {
        New-Item -Path $fullPath -ItemType Directory -Force | Out-Null
    }
}

# Definisci i file da creare con i relativi percorsi
$files = @(
    "assets/css/bootstrap.min.css",
    "assets/css/dashboard.css",
    "assets/css/mobile.css",
    "assets/js/bootstrap.bundle.min.js",
    "assets/js/jquery.min.js",
    "assets/js/dashboard.js",
    "assets/js/api-tester.js",
    "assets/img/logo.png",
    "includes/config.php",
    "includes/auth.php",
    "includes/header.php",
    "includes/footer.php",
    "includes/sidebar.php",
    "includes/functions.php",
    "api/profile.php",
    "api/subscription.php",
    "api/payments.php",
    "api/test-api.php",
    "pages/dashboard.php",
    "pages/profile.php",
    "pages/api-key.php",
    "pages/subscription.php",
    "pages/payment-history.php",
    "pages/usage-stats.php",
    "pages/api-tester.php",
    "pages/documentation.php",
    "login.php",
    "register.php",
    "forgot-password.php",
    "verify-email.php",
    "logout.php",
    "index.php"
)

# Crea i file
foreach ($file in $files) {
    $fullFilePath = Join-Path $basePath $file
    if (-not (Test-Path $fullFilePath)) {
        New-Item -Path $fullFilePath -ItemType File -Force | Out-Null
    }
}
