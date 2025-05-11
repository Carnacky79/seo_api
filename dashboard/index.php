<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Verifica che l'utente sia autenticato
requireLogin();

// Determina quale pagina mostrare
$page = isset($_GET['page']) ? sanitize($_GET['page']) : 'dashboard';

// Validazione del nome della pagina (evita path traversal)
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $page)) {
    $page = 'dashboard';
}

// Percorso del file della pagina
$pageFile = 'pages/' . $page . '.php';

// Verifica che il file esista
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = 'pages/dashboard.php';
}

// Include l'header
include 'includes/header.php';

// Include la pagina richiesta
include $pageFile;

// Include il footer
include 'includes/footer.php';
