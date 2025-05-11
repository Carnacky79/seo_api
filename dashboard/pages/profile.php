<!-- Informazioni profilo -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Informazioni Personali</h5>
    </div>
    <div class="card-body">
        <form action="api/update-profile.php" method="post" class="ajax-form" data-reload="true">
            <input type="hidden" name="action" value="update_profile">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="first_name" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="last_name" class="form-label">Cognome</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        <div class="form-text">L'indirizzo email non può essere modificato.</div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="fiscal_code" class="form-label">Codice Fiscale</label>
                        <input type="text" class="form-control" id="fiscal_code" value="<?php echo htmlspecialchars($user['fiscal_code']); ?>" readonly>
                        <div class="form-text">Il codice fiscale non può essere modificato.</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="phone" class="form-label">Telefono</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="company" class="form-label">Azienda</label>
                        <input type="text" class="form-control" id="company" name="company" value="<?php echo htmlspecialchars($user['company'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="vat_number" class="form-label">Partita IVA</label>
                <input type="text" class="form-control" id="vat_number" name="vat_number" value="<?php echo htmlspecialchars($user['vat_number'] ?? ''); ?>">
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Account creato il</label>
                <div class="form-control bg-light"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Salva modifiche
                </button>

                <?php if (!$user['email_verified']): ?>
                    <a href="verify-email.php" class="btn btn-warning">
                        <i class="fas fa-envelope me-2"></i> Verifica Email
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Cambio password -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Cambia Password</h5>
    </div>
    <div class="card-body">
        <form action="api/update-password.php" method="post" class="ajax-form password-form">
            <input type="hidden" name="action" value="update_password">

            <div class="form-group mb-3">
                <label for="current_password" class="form-label">Password attuale</label>
                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Inserisci la password attuale" required>
            </div>

            <div class="form-group mb-3">
                <label for="password" class="form-label">Nuova password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Inserisci una nuova password" required>
                <div class="form-text">Almeno 8 caratteri.</div>
            </div>

            <div class="form-group mb-3">
                <label for="password_confirm" class="form-label">Conferma nuova password</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Conferma la nuova password" required>
            </div>

            <button type="submit" class="btn btn-warning">
                <i class="fas fa-key me-2"></i> Cambia Password
            </button>
        </form>
    </div>
</div>

<!-- Impostazioni account -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Impostazioni Account</h5>
    </div>
    <div class="card-body">
        <div class="mb-4">
            <h6 class="mb-3">Eliminazione Account</h6>
            <p>L'eliminazione del tuo account è un'azione permanente e comporterà la perdita di tutti i dati e le impostazioni.</p>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                <i class="fas fa-trash-alt me-2"></i> Elimina Account
            </button>
        </div>

        <div class="divider"></div>

        <div>
            <h6 class="mb-3">Esportazione Dati</h6>
            <p>Puoi esportare tutti i tuoi dati personali e le statistiche di utilizzo in formato JSON.</p>
            <a href="api/export-data.php?format=json" class="btn btn-outline-primary">
                <i class="fas fa-file-export me-2"></i> Esporta Dati
            </a>
        </div>
    </div>
</div>

<!-- Modal Eliminazione Account -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAccountModalLabel">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler eliminare il tuo account? Questa azione non può essere annullata.</p>
                <p>Se procedi:</p>
                <ul>
                    <li>Tutti i tuoi dati personali saranno eliminati</li>
                    <li>Non potrai più accedere all'API</li>
                    <li>Eventuali abbonamenti a pagamento saranno cancellati</li>
                </ul>

                <form id="deleteAccountForm" action="api/delete-account.php" method="post" class="ajax-form">
                    <input type="hidden" name="action" value="delete_account">

                    <div class="form-group mb-3">
                        <label for="delete_confirmation" class="form-label">Per confermare, digita "ELIMINA" nel campo sottostante</label>
                        <input type="text" class="form-control" id="delete_confirmation" name="delete_confirmation" placeholder="ELIMINA" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="delete_password" class="form-label">Inserisci la tua password</label>
                        <input type="password" class="form-control" id="delete_password" name="password" placeholder="Password" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-danger" id="confirmDelete" disabled>Elimina Account</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Gestione conferma eliminazione account
    document.addEventListener('DOMContentLoaded', function() {
        const deleteConfirmationInput = document.getElementById('delete_confirmation');
        const confirmDeleteButton = document.getElementById('confirmDelete');
        const deleteAccountForm = document.getElementById('deleteAccountForm');

        if (deleteConfirmationInput && confirmDeleteButton) {
            deleteConfirmationInput.addEventListener('input', function() {
                confirmDeleteButton.disabled = this.value !== 'ELIMINA';
            });

            confirmDeleteButton.addEventListener('click', function() {
                if (deleteConfirmationInput.value === 'ELIMINA') {
                    deleteAccountForm.submit();
                }
            });
        }
    });
</script>
