/**
 * Dashboard functionality for SEO Metadata API
 */
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    setupMobileMenu();

    // User dropdown menu
    setupUserDropdown();

    // Tab navigation
    setupTabs();

    // API key actions
    setupApiKeyActions();

    // Form validations and submissions
    setupForms();

    // Subscription plan selection
    setupPlanSelection();

    // Chart initializations (if any charts are present)
    setupCharts();

    // Notifications
    setupNotifications();
});

/**
 * Setup mobile menu toggle for responsive design
 */
function setupMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const body = document.body;

    if (!menuToggle) return;

    // Create overlay for closing sidebar
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    body.appendChild(overlay);

    // Toggle sidebar
    menuToggle.addEventListener('click', function() {
        body.classList.toggle('sidebar-visible');
    });

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', function() {
        body.classList.remove('sidebar-visible');
    });

    // Close sidebar when clicking a menu item (on mobile)
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                body.classList.remove('sidebar-visible');
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            body.classList.remove('sidebar-visible');
        }
    });
}

/**
 * Setup user dropdown menu in header
 */
function setupUserDropdown() {
    const userMenu = document.querySelector('.user-menu');
    const userDropdown = document.querySelector('.user-dropdown');

    if (!userMenu || !userDropdown) return;

    userMenu.addEventListener('click', function(e) {
        e.preventDefault();
        userDropdown.classList.toggle('open');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!userDropdown.contains(e.target) && !userMenu.contains(e.target)) {
            userDropdown.classList.remove('open');
        }
    });
}

/**
 * Setup tabbed navigation
 */
function setupTabs() {
    const tabLinks = document.querySelectorAll('.tab-link');

    if (tabLinks.length === 0) return;

    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Get the tab container
            const tabContainer = this.closest('.tabs');
            const tabId = this.getAttribute('data-tab');

            // Remove active class from all tabs and content
            tabContainer.querySelectorAll('.tab-link').forEach(tabLink => {
                tabLink.classList.remove('active');
            });

            tabContainer.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Add active class to current tab and content
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Activate first tab by default (if none is active)
    const activeTab = document.querySelector('.tab-link.active');
    if (!activeTab && tabLinks.length > 0) {
        tabLinks[0].click();
    }
}

/**
 * Setup API key display and actions
 */
function setupApiKeyActions() {
    const apiKeyDisplay = document.querySelector('.api-key-display');

    if (!apiKeyDisplay) return;

    const apiKeyValue = apiKeyDisplay.querySelector('.api-key-value');
    const copyButton = apiKeyDisplay.querySelector('.btn-copy');
    const showButton = apiKeyDisplay.querySelector('.btn-show');
    const regenerateButton = document.getElementById('regenerateApiKey');

    // Copy API key to clipboard
    if (copyButton && apiKeyValue) {
        copyButton.addEventListener('click', function() {
            const text = apiKeyValue.textContent || apiKeyValue.innerText;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text)
                    .then(() => {
                        showNotification('Chiave API copiata negli appunti', 'success');

                        // Visual feedback
                        copyButton.innerHTML = '<i class="fas fa-check"></i>';
                        setTimeout(() => {
                            copyButton.innerHTML = '<i class="fas fa-copy"></i>';
                        }, 1500);
                    })
                    .catch(err => {
                        showNotification('Impossibile copiare la chiave API: ' + err, 'error');
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
                    showNotification('Chiave API copiata negli appunti', 'success');

                    // Visual feedback
                    copyButton.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(() => {
                        copyButton.innerHTML = '<i class="fas fa-copy"></i>';
                    }, 1500);
                } catch (err) {
                    showNotification('Impossibile copiare la chiave API: ' + err, 'error');
                }

                document.body.removeChild(textArea);
            }
        });
    }

    // Show/hide API key
    if (showButton && apiKeyValue) {
        const originalText = apiKeyValue.textContent;
        const maskedText = '•'.repeat(originalText.length);
        apiKeyValue.textContent = maskedText;

        let isVisible = false;

        showButton.addEventListener('click', function() {
            isVisible = !isVisible;

            if (isVisible) {
                apiKeyValue.textContent = originalText;
                showButton.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                apiKeyValue.textContent = maskedText;
                showButton.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    }

    // Regenerate API key
    if (regenerateButton) {
        regenerateButton.addEventListener('click', function(e) {
            e.preventDefault();

            if (!confirm('Sei sicuro di voler rigenerare la tua chiave API? Questo renderà la vecchia chiave inutilizzabile.')) {
                return;
            }

            const form = this.closest('form');
            const formData = new FormData(form);

            // Show loading state
            regenerateButton.disabled = true;
            regenerateButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rigenerazione in corso...';

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification('Chiave API rigenerata con successo', 'success');

                        // Update the API key value
                        if (apiKeyValue && data.api_key) {
                            apiKeyValue.textContent = !isVisible ? '•'.repeat(data.api_key.length) : data.api_key;
                        }

                        // Reload page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotification('Errore: ' + data.message, 'error');
                        regenerateButton.disabled = false;
                        regenerateButton.innerHTML = '<i class="fas fa-sync"></i> Rigenera chiave API';
                    }
                })
                .catch(error => {
                    showNotification('Errore di comunicazione con il server', 'error');
                    regenerateButton.disabled = false;
                    regenerateButton.innerHTML = '<i class="fas fa-sync"></i> Rigenera chiave API';
                });
        });
    }
}

/**
 * Setup form validations and AJAX submissions
 */
function setupForms() {
    const ajaxForms = document.querySelectorAll('form.ajax-form');

    if (ajaxForms.length === 0) return;

    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Basic validation
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');

                    // Add error message if not exists
                    const errorContainer = field.parentNode.querySelector('.invalid-feedback');
                    if (!errorContainer) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Questo campo è obbligatorio';
                        field.parentNode.appendChild(errorDiv);
                    }
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                showNotification('Compila tutti i campi obbligatori', 'error');
                return;
            }

            // Disable submit button and show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;

            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Invio in corso...';

            // Submit form via AJAX
            const formData = new FormData(form);

            fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification(data.message || 'Operazione completata con successo', 'success');

                        // Reset form if specified
                        if (form.dataset.reset === 'true') {
                            form.reset();
                        }

                        // Redirect if specified
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        }

                        // Reload page if specified
                        if (form.dataset.reload === 'true') {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        showNotification('Errore: ' + (data.message || 'Si è verificato un errore'), 'error');
                    }

                    // Restore button state
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                })
                .catch(error => {
                    showNotification('Errore di comunicazione con il server', 'error');

                    // Restore button state
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
        });

        // Clear validation errors on input
        form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');

                // Remove error message
                const errorContainer = this.parentNode.querySelector('.invalid-feedback');
                if (errorContainer) {
                    errorContainer.remove();
                }
            });
        });
    });

    // Special case: Profile form with password confirmation
    const passwordForms = document.querySelectorAll('form.password-form');

    passwordForms.forEach(form => {
        const passwordConfirm = form.querySelector('input[name="password_confirm"]');
        const password = form.querySelector('input[name="password"]');

        if (passwordConfirm && password) {
            passwordConfirm.addEventListener('input', function() {
                if (this.value !== password.value) {
                    this.classList.add('is-invalid');

                    // Add error message if not exists
                    const errorContainer = this.parentNode.querySelector('.invalid-feedback');
                    if (!errorContainer) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Le password non corrispondono';
                        this.parentNode.appendChild(errorDiv);
                    }
                } else {
                    this.classList.remove('is-invalid');

                    // Remove error message
                    const errorContainer = this.parentNode.querySelector('.invalid-feedback');
                    if (errorContainer) {
                        errorContainer.remove();
                    }
                }
            });
        }
    });
}

/**
 * Setup subscription plan selection
 */
function setupPlanSelection() {
    const planCards = document.querySelectorAll('.plan-card');
    const planInput = document.getElementById('selected_plan');

    if (planCards.length === 0 || !planInput) return;

    planCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            planCards.forEach(c => c.classList.remove('selected'));

            // Add selected class to clicked card
            this.classList.add('selected');

            // Update hidden input value
            planInput.value = this.dataset.plan;

            // Update subscription button
            const subscribeButton = document.getElementById('subscribeButton');
            if (subscribeButton) {
                subscribeButton.disabled = false;

                const planName = this.querySelector('.plan-name').textContent;
                const isCurrentPlan = this.classList.contains('current-plan');

                if (isCurrentPlan) {
                    subscribeButton.textContent = 'Piano Attuale';
                    subscribeButton.disabled = true;
                } else {
                    subscribeButton.textContent = 'Seleziona ' + planName;
                }
            }
        });
    });

    // Select current plan by default
    const currentPlan = document.querySelector('.plan-card.current-plan');
    if (currentPlan) {
        currentPlan.click();
    } else if (planCards.length > 0) {
        planCards[0].click();
    }
}

/**
 * Setup charts for usage statistics
 */
function setupCharts() {
    // Usage chart
    const usageChartCanvas = document.getElementById('usageChart');

    if (usageChartCanvas && typeof Chart !== 'undefined') {
        const ctx = usageChartCanvas.getContext('2d');

        // Get data from data attributes
        const labels = JSON.parse(usageChartCanvas.dataset.labels || '[]');
        const values = JSON.parse(usageChartCanvas.dataset.values || '[]');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Richieste API',
                    data: values,
                    backgroundColor: 'rgba(37, 99, 235, 0.5)',
                    borderColor: 'rgb(37, 99, 235)',
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

/**
 * Setup notification system
 */
function setupNotifications() {
    // Check if there are flash messages from server
    const flashMessages = document.querySelectorAll('.alert-flash');

    flashMessages.forEach(message => {
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            message.classList.remove('show');
            setTimeout(() => {
                message.remove();
            }, 300);
        }, 5000);

        // Dismiss on click
        const closeButton = message.querySelector('.btn-close');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                message.classList.remove('show');
                setTimeout(() => {
                    message.remove();
                }, 300);
            });
        }
    });
}

/**
 * Show a notification message
 *
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, warning, info)
 * @param {number} duration - How long to show the notification in ms
 */
function showNotification(message, type = 'info', duration = 5000) {
    // Convert type to bootstrap alert class
    const typeClass = type === 'error' ? 'danger' : type;

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${typeClass} alert-dismissible fade show`;
    notification.role = 'alert';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    // Add to notification container
    const container = document.getElementById('notificationContainer');
    if (container) {
        container.appendChild(notification);
    } else {
        // Create container if it doesn't exist
        const newContainer = document.createElement('div');
        newContainer.id = 'notificationContainer';
        newContainer.className = 'notification-container';
        newContainer.style.position = 'fixed';
        newContainer.style.top = '20px';
        newContainer.style.right = '20px';
        newContainer.style.zIndex = '1050';
        newContainer.style.maxWidth = '350px';
        document.body.appendChild(newContainer);
        newContainer.appendChild(notification);
    }

    // Auto-dismiss after duration
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, duration);

    // Dismiss on click
    const closeButton = notification.querySelector('.btn-close');
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        });
    }
}

/**
 * Check server status
 * Useful for showing maintenance messages
 */
function checkServerStatus() {
    fetch('/api/status.php')
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'ok') {
                showNotification(data.message || 'Il server è in manutenzione. Alcune funzionalità potrebbero non essere disponibili.', 'warning', 0); // 0 = don't auto-dismiss
            }
        })
        .catch(() => {
            // Ignore errors
        });
}

// Call checkServerStatus on page load
document.addEventListener('DOMContentLoaded', function() {
    checkServerStatus();
});
