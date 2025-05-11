/**
 * File JavaScript per la dashboard amministrativa
 */

// Conferma per le azioni critiche
function confirmAction(message) {
    return confirm(message);
}

// Funzione per modificare lo stato di un utente
function changeUserStatus(userId, newStatus) {
    if (!confirmAction('Sei sicuro di voler cambiare lo stato dell\'utente?')) {
        return false;
    }

    $.ajax({
        url: 'api/users.php',
        type: 'POST',
        data: {
            action: 'change_status',
            user_id: userId,
            status: newStatus
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Aggiorna la UI
                showAlert('Stato dell\'utente aggiornato con successo', 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('Errore: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Errore durante la comunicazione con il server', 'danger');
        }
    });
}

// Funzione per verificare manualmente un'email
function verifyEmail(userId) {
    if (!confirmAction('Sei sicuro di voler verificare manualmente l\'email di questo utente?')) {
        return false;
    }

    $.ajax({
        url: 'api/users.php',
        type: 'POST',
        data: {
            action: 'verify_email',
            user_id: userId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('Email verificata con successo', 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('Errore: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Errore durante la comunicazione con il server', 'danger');
        }
    });
}

// Funzione per rigenerare la chiave API di un utente
function regenerateApiKey(userId) {
    if (!confirmAction('Sei sicuro di voler rigenerare la chiave API? L\'utente dovrà aggiornare tutte le sue integrazioni.')) {
        return false;
    }

    $.ajax({
        url: 'api/users.php',
        type: 'POST',
        data: {
            action: 'regenerate_api_key',
            user_id: userId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('Chiave API rigenerata con successo', 'success');
                // Aggiorna il campo della chiave API nella pagina
                $('#user-api-key').text(response.api_key);
            } else {
                showAlert('Errore: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Errore durante la comunicazione con il server', 'danger');
        }
    });
}

// Funzione per aggiornare l'abbonamento di un utente
function updateSubscription(userId, planType) {
    if (!confirmAction('Sei sicuro di voler cambiare il piano di abbonamento dell\'utente?')) {
        return false;
    }

    $.ajax({
        url: 'api/subscriptions.php',
        type: 'POST',
        data: {
            action: 'update_subscription',
            user_id: userId,
            plan_type: planType
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('Abbonamento aggiornato con successo', 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('Errore: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Errore durante la comunicazione con il server', 'danger');
        }
    });
}

// Funzione per mostrare avvisi
function showAlert(message, type) {
    const alertDiv = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
        '</div>');

    // Aggiungi l'alert all'inizio del contenuto principale
    $('main').prepend(alertDiv);

    // Nascondi automaticamente dopo 5 secondi
    setTimeout(function() {
        alertDiv.alert('close');
    }, 5000);
}

// Funzione per copiare la chiave API negli appunti
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showAlert('Copiato negli appunti!', 'success');
    }, function() {
        showAlert('Impossibile copiare negli appunti', 'danger');
    });
}

// Funzione per inizializzare i grafici nella dashboard
function initCharts() {
    // Se la pagina è la dashboard e l'elemento canvas esiste
    if ($('#registrationsChart').length > 0) {
        // Carica i dati dal server
        $.ajax({
            url: 'api/stats.php',
            type: 'GET',
            data: {
                action: 'registrations_chart'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const ctx = document.getElementById('registrationsChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                label: 'Registrazioni',
                                data: response.data.values,
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1,
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
            }
        });
    }

    // Grafico per la distribuzione degli abbonamenti
    if ($('#subscriptionsChart').length > 0) {
        $.ajax({
            url: 'api/stats.php',
            type: 'GET',
            data: {
                action: 'subscriptions_chart'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const ctx = document.getElementById('subscriptionsChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                data: response.data.values,
                                backgroundColor: [
                                    'rgb(108, 117, 125)',
                                    'rgb(23, 162, 184)',
                                    'rgb(255, 193, 7)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            }
        });
    }

    // Grafico per le richieste API
    if ($('#apiRequestsChart').length > 0) {
        $.ajax({
            url: 'api/stats.php',
            type: 'GET',
            data: {
                action: 'api_requests_chart'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const ctx = document.getElementById('apiRequestsChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                label: 'Richieste API',
                                data: response.data.values,
                                backgroundColor: 'rgba(13, 110, 253, 0.5)',
                                borderColor: 'rgb(13, 110, 253)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
            }
        });
    }
}

// Inizializza i grafici quando il documento è pronto
$(document).ready(function() {
    initCharts();

    // Gestione click sui pulsanti di copia
    $('.btn-copy').on('click', function() {
        const textToCopy = $(this).data('copy');
        copyToClipboard(textToCopy);
    });

    // Gestione dei form con AJAX
    $('.ajax-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const url = form.attr('action');
        const method = form.attr('method') || 'POST';
        const formData = form.serialize();

        $.ajax({
            url: url,
            type: method,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    showAlert(response.message || 'Operazione completata con successo', 'success');

                    // Se è specificato un redirect, eseguilo dopo un breve delay
                    if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1500);
                    }
                } else {
                    showAlert('Errore: ' + (response.message || 'Si è verificato un errore'), 'danger');
                }
            },
            error: function() {
                showAlert('Errore durante la comunicazione con il server', 'danger');
            }
        });
    });
});
