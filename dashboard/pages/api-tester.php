<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Tester API</h5>
    </div>
    <div class="card-body">
        <p>Utilizza questo strumento per testare le tue richieste API direttamente dal browser.</p>

        <div id="apiTester" data-api-key="<?php echo htmlspecialchars($user['api_key']); ?>">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="requestMethod" class="form-label">Metodo</label>
                        <select id="requestMethod" class="form-select">
                            <option value="POST">POST</option>
                            <option value="GET">GET</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="exampleRequest" class="form-label">Esempi</label>
                        <select id="exampleRequest" class="form-select">
                            <option value="">Seleziona un esempio...</option>
                            <option value="metadata">Genera metadati SEO</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="apiUrl" class="form-label">URL API</label>
                <input type="text" id="apiUrl" class="form-control" value="/api/generate-metadata" placeholder="es. /api/generate-metadata">
                <div class="form-text">URL dell'endpoint API da chiamare</div>
            </div>

            <div class="form-group mb-3">
                <label for="apiKey" class="form-label">Chiave API</label>
                <input type="text" id="apiKey" class="form-control" placeholder="La tua chiave API">
                <div class="form-text">Questa chiave verrà inviata nell'header Authorization</div>
            </div>

            <div class="api-tester">
                <!-- Request Section -->
                <div class="api-request">
                    <div class="api-request-header">
                        <i class="fas fa-arrow-up me-1"></i> Richiesta
                    </div>
                    <div class="api-request-body">
                        <div class="form-group mb-3">
                            <label for="jsonRequest" class="form-label">Corpo della richiesta (JSON)</label>
                            <textarea id="jsonRequest" class="form-control" rows="10" placeholder='{
  "url": "https://www.example.com"
}'></textarea>
                        </div>

                        <div class="btn-toolbar">
                            <button type="button" id="formatJson" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="fas fa-code me-1"></i> Formatta JSON
                            </button>
                            <button type="button" id="clearRequest" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="fas fa-trash-alt me-1"></i> Pulisci
                            </button>
                            <button type="button" id="sendRequest" class="btn btn-sm btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Invia Richiesta
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Response Section -->
                <div class="api-response">
                    <div class="api-response-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-arrow-down me-1"></i> Risposta
                            <span id="responseStatus" class="ms-2"></span>
                            <span id="responseTime" class="ms-2 text-muted"></span>
                        </div>
                        <button type="button" id="copyResponse" class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="fas fa-copy me-1"></i> Copia
                        </button>
                    </div>
                    <div class="api-response-body">
                        <div id="loadingIndicator" class="text-center py-3" style="display: none;">
                            <div class="spinner"></div>
                            <div>Elaborazione in corso...</div>
                        </div>
                        <pre><code id="apiResponse" class="language-json"></code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Documentation Quick Reference -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Riferimento Rapido API</h5>
    </div>
    <div class="card-body">
        <h4>Endpoint Principali</h4>

        <div class="mb-4">
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/generate-metadata</span>
            </div>
            <p>Genera metadati SEO ottimizzati per una pagina web.</p>
            <h5>Parametri richiesti:</h5>
            <pre><code class="language-json">{
  "url": "https://www.example.com"
}</code></pre>

            <h5>Esempio di risposta:</h5>
            <pre><code class="language-json">{
  "status": "success",
  "url": "https://www.example.com",
  "optimized_metadata": {
    "title": "Titolo SEO ottimizzato",
    "description": "Descrizione ottimizzata per migliorare il posizionamento...",
    "keywords": ["keyword1", "keyword2", "keyword3"],
    "og_tags": { /* ... */ },
    "twitter_cards": { /* ... */ }
  },
  /* ... altri dati ... */
}</code></pre>
        </div>

        <div class="mb-4">
            <h4>Autenticazione</h4>
            <p>Tutte le richieste API richiedono autenticazione mediante la tua chiave API.</p>

            <h5>Header richiesti:</h5>
            <pre><code>Authorization: Bearer YOUR_API_KEY
Content-Type: application/json</code></pre>
        </div>

        <div>
            <h4>Limiti di Utilizzo</h4>
            <ul>
                <li><strong>Piano Gratuito:</strong> 10 richieste al mese</li>
                <li><strong>Piano Pro:</strong> 1.000 richieste al mese</li>
                <li><strong>Piano Premium:</strong> Richieste illimitate</li>
            </ul>

            <a href="index.php?page=documentation" class="btn btn-outline-primary">
                <i class="fas fa-book me-1"></i> Documentazione Completa
            </a>
        </div>
    </div>
</div>
