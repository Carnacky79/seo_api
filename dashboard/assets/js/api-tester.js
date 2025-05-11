/**
 * API Tester functionality for SEO Metadata API
 */
document.addEventListener('DOMContentLoaded', function() {
    const apiTester = document.getElementById('apiTester');
    if (!apiTester) return;

    const urlInput = document.getElementById('apiUrl');
    const jsonInput = document.getElementById('jsonRequest');
    const methodSelect = document.getElementById('requestMethod');
    const sendButton = document.getElementById('sendRequest');
    const responseContainer = document.getElementById('apiResponse');
    const apiKeyInput = document.getElementById('apiKey');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const responseStatusElement = document.getElementById('responseStatus');
    const responseTimeElement = document.getElementById('responseTime');
    const copyResponseButton = document.getElementById('copyResponse');

    // Example request templates
    const exampleSelect = document.getElementById('exampleRequest');

    if (exampleSelect) {
        exampleSelect.addEventListener('change', function() {
            if (this.value === '') return;

            const examples = {
                metadata: {
                    method: 'POST',
                    url: '/api/generate-metadata',
                    body: JSON.stringify({
                        url: 'https://www.example.com'
                    }, null, 2)
                }
                // Add more examples as needed
            };

            const example = examples[this.value];
            if (example) {
                methodSelect.value = example.method;
                urlInput.value = example.url;
                jsonInput.value = example.body;
            }

            // Reset select
            this.value = '';
        });
    }

    // Format JSON button
    const formatJsonButton = document.getElementById('formatJson');
    if (formatJsonButton) {
        formatJsonButton.addEventListener('click', function() {
            try {
                const json = JSON.parse(jsonInput.value);
                jsonInput.value = JSON.stringify(json, null, 2);
            } catch (e) {
                showNotification('JSON non valido', 'error');
            }
        });
    }

    // Clear button
    const clearButton = document.getElementById('clearRequest');
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            jsonInput.value = '';
            responseContainer.textContent = '';
            responseStatusElement.textContent = '';
            responseTimeElement.textContent = '';
            copyResponseButton.disabled = true;
        });
    }

    // Send request
    if (sendButton) {
        sendButton.addEventListener('click', function() {
            // Validate URL
            const url = urlInput.value.trim();
            if (!url) {
                showNotification('Inserisci un URL valido', 'error');
                return;
            }

            // Validate JSON if provided
            let requestBody = null;
            if (jsonInput.value.trim()) {
                try {
                    requestBody = JSON.parse(jsonInput.value);
                } catch (e) {
                    showNotification('JSON non valido: ' + e.message, 'error');
                    return;
                }
            }

            // Get API key
            const apiKey = apiKeyInput.value.trim();
            if (!apiKey) {
                showNotification('Inserisci la tua chiave API', 'error');
                return;
            }

            // Prepare request
            const method = methodSelect.value;

            // Show loading state
            loadingIndicator.style.display = 'block';
            sendButton.disabled = true;
            responseContainer.textContent = 'Invio richiesta in corso...';
            responseStatusElement.textContent = '';
            responseTimeElement.textContent = '';

            // Start timer
            const startTime = new Date().getTime();

            // Send request
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiKey}`
                },
                body: requestBody ? JSON.stringify(requestBody) : undefined
            })
                .then(response => {
                    const endTime = new Date().getTime();
                    const timeElapsed = endTime - startTime;

                    // Set status
                    responseStatusElement.textContent = `${response.status} ${response.statusText}`;
                    responseStatusElement.className = response.ok ? 'text-success' : 'text-danger';

                    // Set time
                    responseTimeElement.textContent = `${timeElapsed} ms`;

                    // Enable copy button
                    copyResponseButton.disabled = false;

                    return response.json().catch(() => {
                        // If not JSON, return as text
                        return response.text().then(text => {
                            return { _error: 'Non è un oggetto JSON valido', _text: text };
                        });
                    });
                })
                .then(data => {
                    // Format and display response
                    if (data._error) {
                        responseContainer.textContent = data._text;
                    } else {
                        responseContainer.textContent = JSON.stringify(data, null, 2);
                    }

                    // Syntax highlighting if prism.js is included
                    if (window.Prism) {
                        Prism.highlightElement(responseContainer);
                    }
                })
                .catch(error => {
                    responseContainer.textContent = 'Errore: ' + error.message;
                    responseStatusElement.textContent = 'Errore';
                    responseStatusElement.className = 'text-danger';
                })
                .finally(() => {
                    // Hide loading state
                    loadingIndicator.style.display = 'none';
                    sendButton.disabled = false;
                });
        });
    }

    // Copy response
    if (copyResponseButton) {
        copyResponseButton.addEventListener('click', function() {
            const text = responseContainer.textContent;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text)
                    .then(() => {
                        showNotification('Risposta copiata negli appunti', 'success');
                    })
                    .catch(err => {
                        showNotification('Impossibile copiare: ' + err, 'error');
                    });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = 0;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    document.execCommand('copy');
                    showNotification('Risposta copiata negli appunti', 'success');
                } catch (err) {
                    showNotification('Impossibile copiare: ' + err, 'error');
                }

                document.body.removeChild(textArea);
            }
        });
    }

    // Initialize with API key from data attribute
    if (apiKeyInput && apiTester.dataset.apiKey) {
        apiKeyInput.value = apiTester.dataset.apiKey;
    }
});
