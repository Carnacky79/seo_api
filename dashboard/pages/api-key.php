<?php
// Ottieni i dati di utilizzo
$pdo = getDbConnection();

// Ottieni la data di creazione della chiave
$apiKeyCreated = date('d/m/Y', strtotime($user['created_at']));

// Ottieni l'ultimo utilizzo della chiave
$sql = "SELECT created_at FROM request_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$lastRequest = $stmt->fetch();
$apiKeyLastUsed = $lastRequest ? date('d/m/Y H:i', strtotime($lastRequest['created_at'])) : 'Mai utilizzata';
?>

<!-- API Key -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">La tua chiave API</h5>
    </div>
    <div class="card-body">
        <p class="mb-3">Questa è la tua chiave API personale per autenticare le richieste alla nostra API. Mantienila sicura e non condividerla.</p>

        <div class="api-key-display mb-3">
            <div class="api-key-value" id="user-api-key"><?php echo str_repeat('•', strlen($user['api_key'])); ?></div>
            <div class="api-key-actions">
                <button type="button" class="btn-show" title="Mostra/Nascondi">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn-copy" title="Copia" data-copy="<?php echo htmlspecialchars($user['api_key']); ?>">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="text-muted mb-1">Creata il</div>
                <div><?php echo $apiKeyCreated; ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted mb-1">Ultimo utilizzo</div>
                <div><?php echo $apiKeyLastUsed; ?></div>
            </div>
        </div>

        <form action="api/regenerate-api-key.php" method="post" class="ajax-form" data-reload="true">
            <input type="hidden" name="action" value="regenerate">

            <button type="submit" id="regenerateApiKey" class="btn btn-warning">
                <i class="fas fa-sync me-2"></i> Rigenera chiave API
            </button>

            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Attenzione:</strong> La rigenerazione della chiave API renderà la chiave attuale inutilizzabile.
                Dovrai aggiornare tutte le tue integrazioni con la nuova chiave.
            </div>
        </form>
    </div>
</div>

<!-- How to Use -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Come utilizzare la tua chiave API</h5>
    </div>
    <div class="card-body">
        <p>La tua chiave API deve essere inclusa in tutte le richieste che fai alla nostra API per l'autenticazione.</p>

        <div class="mb-4">
            <h5>Header HTTP:</h5>
            <pre><code>Authorization: Bearer <?php echo htmlspecialchars($user['api_key']); ?></code></pre>
            <div class="text-muted">Aggiungi questo header a tutte le tue richieste HTTP.</div>
        </div>

        <h5>Esempi di utilizzo:</h5>

        <div class="tabs">
            <div class="tab-nav">
                <div class="tab-link active" data-tab="curl-example">cURL</div>
                <div class="tab-link" data-tab="php-example">PHP</div>
                <div class="tab-link" data-tab="js-example">JavaScript</div>
                <div class="tab-link" data-tab="python-example">Python</div>
            </div>

            <div id="curl-example" class="tab-content active">
                <pre><code>curl -H "Authorization: Bearer <?php echo htmlspecialchars($user['api_key']); ?>" \
     -H "Content-Type: application/json" \
     -d '{"url": "https://www.example.com"}' \
     -X POST <?php echo getBaseUrl(); ?>/api/generate-metadata</code></pre>
            </div>

            <div id="php-example" class="tab-content">
                <pre><code>&lt;?php
$apiKey = '<?php echo htmlspecialchars($user['api_key']); ?>';
$url = '<?php echo getBaseUrl(); ?>/api/generate-metadata';
$data = json_encode(['url' => 'https://www.example.com']);

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n" .
                   "Authorization: Bearer " . $apiKey . "\r\n",
        'method' => 'POST',
        'content' => $data
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$response = json_decode($result, true);

print_r($response);
?&gt;</code></pre>
            </div>

            <div id="js-example" class="tab-content">
                <pre><code>const apiKey = '<?php echo htmlspecialchars($user['api_key']); ?>';
const apiUrl = '<?php echo getBaseUrl(); ?>/api/generate-metadata';

fetch(apiUrl, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${apiKey}`
  },
  body: JSON.stringify({ url: 'https://www.example.com' })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>

            <div id="python-example" class="tab-content">
                <pre><code>import requests
import json

api_key = '<?php echo htmlspecialchars($user['api_key']); ?>'
api_url = '<?php echo getBaseUrl(); ?>/api/generate-metadata'

headers = {
    'Content-Type': 'application/json',
    'Authorization': f'Bearer {api_key}'
}

data = {
    'url': 'https://www.example.com'
}

response = requests.post(api_url, headers=headers, json=data)
result = response.json()

print(result)</code></pre>
            </div>
        </div>
    </div>
</div>

<!-- Security Tips -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Consigli per la sicurezza</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <div class="me-3 text-primary">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <div>
                        <h5>Mantieni la chiave privata</h5>
                        <p class="text-muted mb-0">Non condividere mai la tua chiave API in luoghi pubblici.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <div class="me-3 text-primary">
                        <i class="fas fa-sync-alt fa-2x"></i>
                    </div>
                    <div>
                        <h5>Ruota regolarmente</h5>
                        <p class="text-muted mb-0">Rigenera periodicamente la tua chiave per maggiore sicurezza.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <div class="me-3 text-primary">
                        <i class="fas fa-server fa-2x"></i>
                    </div>
                    <div>
                        <h5>Salva in modo sicuro</h5>
                        <p class="text-muted mb-0">Archivia la chiave in variabili d'ambiente, non nel codice.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Se sospetti che la tua chiave API sia stata compromessa, rigenera immediatamente una nuova chiave.
        </div>
    </div>
</div>
