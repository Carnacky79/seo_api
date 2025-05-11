<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Documentazione API</h5>
    </div>
    <div class="card-body documentation">
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i> Questa documentazione fornisce tutte le informazioni necessarie per utilizzare l'API di generazione metadati SEO.
        </div>

        <h1>Introduzione</h1>
        <p>L'API SEO Metadata ti permette di generare automaticamente metadati SEO ottimizzati per qualsiasi pagina web. L'API analizza il contenuto della pagina e fornisce suggerimenti per migliorare la visibilità sui motori di ricerca.</p>

        <h2>Endpoint API</h2>
        <p>L'API è accessibile tramite i seguenti endpoint:</p>

        <div class="endpoint">
            <span class="method post">POST</span>
            <span class="path"><?php echo getBaseUrl(); ?>/api/generate-metadata</span>
        </div>
        <p>Genera metadati SEO ottimizzati per una pagina web.</p>

        <h2>Autenticazione</h2>
        <p>Tutte le richieste API richiedono autenticazione. Includi la tua chiave API nell'header <code>Authorization</code> in ogni richiesta.</p>

        <pre><code>Authorization: Bearer TUA_CHIAVE_API</code></pre>

        <div class="alert alert-warning">
            <i class="fas fa-key me-2"></i> Non condividere mai la tua chiave API. Questa chiave fornisce accesso completo al tuo account.
        </div>

        <h2>Limiti di utilizzo</h2>
        <p>Il numero di richieste che puoi effettuare dipende dal tuo piano di abbonamento:</p>
        <ul>
            <li><strong>Piano Gratuito:</strong> 10 richieste al mese</li>
            <li><strong>Piano Pro:</strong> 1.000 richieste al mese</li>
            <li><strong>Piano Premium:</strong> Richieste illimitate</li>
        </ul>

        <h2>Generazione Metadati</h2>
        <h3>Richiesta</h3>
        <div class="endpoint">
            <span class="method post">POST</span>
            <span class="path"><?php echo getBaseUrl(); ?>/api/generate-metadata</span>
        </div>

        <h4>Headers</h4>
        <table>
            <thead>
            <tr>
                <th>Nome</th>
                <th>Obbligatorio</th>
                <th>Descrizione</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Authorization</td>
                <td>Sì</td>
                <td><code>Bearer TUA_CHIAVE_API</code></td>
            </tr>
            <tr>
                <td>Content-Type</td>
                <td>Sì</td>
                <td><code>application/json</code></td>
            </tr>
            </tbody>
        </table>

        <h4>Body della richiesta</h4>
        <pre><code>{
  "url": "https://www.example.com/pagina"
}</code></pre>

        <table>
            <thead>
            <tr>
                <th>Parametro</th>
                <th>Tipo</th>
                <th>Obbligatorio</th>
                <th>Descrizione</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>url</td>
                <td>string</td>
                <td>Sì</td>
                <td>URL della pagina da analizzare</td>
            </tr>
            </tbody>
        </table>

        <h3>Risposta</h3>
        <pre><code>{
  "status": "success",
  "url": "https://www.example.com/pagina",
  "original_metadata": {
    "title": "Titolo originale",
    "description": "Descrizione originale",
    "keywords": ["keyword1", "keyword2"]
  },
  "optimized_metadata": {
    "title": "Titolo SEO ottimizzato",
    "description": "Descrizione SEO ottimizzata per migliorare il posizionamento nei motori di ricerca.",
    "keywords": ["keyword1", "keyword2", "keyword3"],
    "og_tags": {
      "og:title": "Titolo Open Graph",
      "og:description": "Descrizione Open Graph",
      "og:type": "website",
      "og:locale": "it_IT"
    },
    "twitter_cards": {
      "twitter:card": "summary_large_image",
      "twitter:title": "Titolo Twitter Card",
      "twitter:description": "Descrizione Twitter Card"
    }
  },
  "suggestions": [
    "Suggerimento per migliorare il SEO della pagina",
    "Altro suggerimento utile"
  ],
  "metadata_html": "<!-- Codice HTML dei metadati -->"
}</code></pre>

        <table>
            <thead>
            <tr>
                <th>Proprietà</th>
                <th>Tipo</th>
                <th>Descrizione</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>status</td>
                <td>string</td>
                <td>"success" o "error"</td>
            </tr>
            <tr>
                <td>url</td>
                <td>string</td>
                <td>URL della pagina analizzata</td>
            </tr>
            <tr>
                <td>original_metadata</td>
                <td>object</td>
                <td>Metadati originali trovati nella pagina</td>
            </tr>
            <tr>
                <td>optimized_metadata</td>
                <td>object</td>
                <td>Metadati SEO ottimizzati generati dall'API</td>
            </tr>
            <tr>
                <td>suggestions</td>
                <td>array</td>
                <td>Suggerimenti per migliorare il SEO della pagina</td>
            </tr>
            <tr>
                <td>metadata_html</td>
                <td>string</td>
                <td>Codice HTML dei metadati pronto per essere copiato</td>
            </tr>
            </tbody>
        </table>

        <h3>Codici di stato</h3>
        <table>
            <thead>
            <tr>
                <th>Codice</th>
                <th>Descrizione</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>200</td>
                <td>Successo</td>
            </tr>
            <tr>
                <td>400</td>
                <td>Richiesta non valida (parametri mancanti o non validi)</td>
            </tr>
            <tr>
                <td>401</td>
                <td>Autenticazione non riuscita (chiave API mancante o non valida)</td>
            </tr>
            <tr>
                <td>403</td>
                <td>Limite di richieste superato</td>
            </tr>
            <tr>
                <td>404</td>
                <td>URL non trovato o non accessibile</td>
            </tr>
            <tr>
                <td>429</td>
                <td>Troppe richieste (rate limiting)</td>
            </tr>
            <tr>
                <td>500</td>
                <td>Errore interno del server</td>
            </tr>
            </tbody>
        </table>

        <h2>Esempi di utilizzo</h2>
        <div class="tabs">
            <div class="tab-nav">
                <div class="tab-link active" data-tab="curl-example">cURL</div>
                <div class="tab-link" data-tab="php-example">PHP</div>
                <div class="tab-link" data-tab="js-example">JavaScript</div>
                <div class="tab-link" data-tab="python-example">Python</div>
            </div>

            <div id="curl-example" class="tab-content active">
                <pre><code>curl -H "Authorization: Bearer YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"url": "https://www.example.com"}' \
     -X POST <?php echo getBaseUrl(); ?>/api/generate-metadata</code></pre>
            </div>

            <div id="php-example" class="tab-content">
                <pre><code>&lt;?php
$apiKey = 'YOUR_API_KEY';
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
                <pre><code>const apiKey = 'YOUR_API_KEY';
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

api_key = 'YOUR_API_KEY'
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

        <h2>Gestione degli errori</h2>
        <p>In caso di errore, l'API restituirà una risposta con un codice di stato HTTP non 2xx e un corpo JSON con dettagli sull'errore:</p>

        <pre><code>{
  "status": "error",
  "error": "error_code",
  "message": "Descrizione dettagliata dell'errore"
}</code></pre>

        <h3>Codici di errore comuni</h3>
        <table>
            <thead>
            <tr>
                <th>Codice</th>
                <th>Descrizione</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>invalid_url</td>
                <td>L'URL fornito non è valido o non è accessibile</td>
            </tr>
            <tr>
                <td>authentication_failed</td>
                <td>Autenticazione non riuscita (chiave API mancante o non valida)</td>
            </tr>
            <tr>
                <td>quota_exceeded</td>
                <td>Limite di richieste mensile superato</td>
            </tr>
            <tr>
                <td>rate_limited</td>
                <td>Troppe richieste in un breve periodo di tempo</td>
            </tr>
            <tr>
                <td>internal_error</td>
                <td>Errore interno del server</td>
            </tr>
            </tbody>
        </table>

        <h2>Contatti e supporto</h2>
        <p>Se hai domande o hai bisogno di assistenza con l'API, puoi contattarci:</p>
        <ul>
            <li>Email: support@seometadata-api.com</li>
            <li>Gli utenti dei piani Pro e Premium hanno accesso a supporto prioritario</li>
        </ul>

        <div class="alert alert-primary mt-4">
            <i class="fas fa-code me-2"></i> <strong>Suggerimento:</strong> Utilizza il nostro <a href="index.php?page=api-tester">API Tester</a> integrato per testare le tue richieste API direttamente dal browser.
        </div>
    </div>
</div>
