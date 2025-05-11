<?php
// Ottieni i dettagli dell'abbonamento corrente
$pdo = getDbConnection();

// Ottieni le statistiche di utilizzo del mese corrente
$currentMonth = date('n');
$currentYear = date('Y');

$sql = "SELECT request_count FROM api_usage WHERE user_id = ? AND month = ? AND year = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id'], $currentMonth, $currentYear]);
$currentUsage = $stmt->fetch();
$requestCount = $currentUsage ? (int)$currentUsage['request_count'] : 0;

// Formatta la data di scadenza dell'abbonamento
$subscriptionEnd = $subscription ? date('d/m/Y', strtotime($subscription['current_period_end'])) : 'N/A';

// Definisci i dettagli dei piani disponibili
$plans = [
    'free' => [
        'name' => 'Gratuito',
        'price' => '0€',
        'price_value' => 0,
        'requests' => '10',
        'features' => [
            'Generazione metadati SEO',
            'Titoli e descrizioni ottimizzati',
            '10 richieste al mese',
            'Aggiornamenti mensili'
        ],
        'badge_class' => 'badge-free',
        'button_class' => 'btn-secondary'
    ],
    'pro' => [
        'name' => 'Pro',
        'price' => '20€',
        'price_value' => 20,
        'requests' => '1.000',
        'features' => [
            'Tutto del piano Gratuito',
            '1.000 richieste al mese',
            'Supporto email',
            'Tag social OpenGraph',
            'Meta keywords ottimizzate',
            'Twitter Cards'
        ],
        'badge_class' => 'badge-pro',
        'button_class' => 'btn-primary'
    ],
    'premium' => [
        'name' => 'Premium',
        'price' => '50€',
        'price_value' => 50,
        'requests' => 'Illimitate',
        'features' => [
            'Tutto del piano Pro',
            'Richieste illimitate',
            'Supporto prioritario',
            'Dati strutturati JSON-LD',
            'Suggerimenti avanzati per SEO',
            'Analisi del contenuto'
        ],
        'badge_class' => 'badge-premium',
        'button_class' => 'btn-warning'
    ]
];
?>

<?php if ($subscription): ?>
    <!-- Current Subscription -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Il tuo abbonamento attuale</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <span class="text-muted">Piano:</span>
                        <span class="ms-2 subscription-badge <?php echo $planBadgeClass; ?>"><?php echo $planName; ?></span>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted">Stato:</span>
                        <span class="ms-2 badge bg-success">Attivo</span>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted">Scadenza:</span>
                        <span class="ms-2"><?php echo $subscriptionEnd; ?></span>
                    </div>

                    <?php if ($planType !== 'premium'): ?>
                        <div class="mb-3">
                            <span class="text-muted">Richieste utilizzate:</span>
                            <span class="ms-2"><?php echo number_format($requestCount); ?> / <?php echo $planType === 'free' ? '10' : '1.000'; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <span class="text-muted">Richieste utilizzate:</span>
                            <span class="ms-2"><?php echo number_format($requestCount); ?> (Piano illimitato)</span>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <a href="index.php?page=payment-history" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-receipt me-1"></i> Storia pagamenti
                        </a>
                    </div>
                </div>

                <?php if ($planType !== 'free'): ?>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Fatturazione</h5>

                                <?php if ($subscription['stripe_subscription_id']): ?>
                                    <p>Il tuo abbonamento si rinnoverà automaticamente il <?php echo $subscriptionEnd; ?>.</p>

                                    <form action="api/cancel-subscription.php" method="post" class="ajax-form" data-reload="true">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="subscription_id" value="<?php echo htmlspecialchars($subscription['id']); ?>">

                                        <button type="submit" class="btn btn-danger btn-sm mt-2">
                                            <i class="fas fa-times-circle me-1"></i> Annulla abbonamento
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <p>Il tuo abbonamento scadrà il <?php echo $subscriptionEnd; ?> e passerai automaticamente al piano Gratuito.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Subscription Plans -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Piani di abbonamento</h5>
    </div>
    <div class="card-body">
        <p>Scegli il piano più adatto alle tue esigenze:</p>

        <div class="subscription-plans">
            <?php foreach ($plans as $planId => $plan): ?>
                <div class="plan-card <?php echo $planType === $planId ? 'selected current-plan' : ''; ?>" data-plan="<?php echo $planId; ?>">
                    <div class="plan-header">
                        <span class="subscription-badge <?php echo $plan['badge_class']; ?>"><?php echo $plan['name']; ?></span>
                        <h3 class="plan-name"><?php echo $plan['name']; ?></h3>
                        <div class="plan-price">
                            <?php echo $plan['price']; ?> <span class="period">/mese</span>
                        </div>
                        <div><strong><?php echo $plan['requests']; ?></strong> richieste al mese</div>
                    </div>

                    <ul class="plan-features">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li><i class="fas fa-check"></i> <?php echo $feature; ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="plan-footer">
                        <?php if ($planType === $planId): ?>
                            <button class="btn <?php echo $plan['button_class']; ?> w-100" disabled>Piano Attuale</button>
                        <?php else: ?>
                            <button class="btn <?php echo $plan['button_class']; ?> w-100">Seleziona <?php echo $plan['name']; ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <form action="api/update-subscription.php" method="post" class="ajax-form" data-reload="true">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="plan_type" id="selected_plan" value="<?php echo $planType; ?>">

            <div class="text-center mt-4">
                <button type="submit" id="subscribeButton" class="btn btn-lg btn-primary" <?php echo $planType ? 'disabled' : ''; ?>>
                    <?php echo $planType ? 'Piano Attuale' : 'Seleziona Piano'; ?>
                </button>

                <div class="text-muted mt-2">
                    <i class="fas fa-lock me-1"></i> Pagamento sicuro tramite Stripe
                </div>
            </div>
        </form>
    </div>
</div>

<!-- FAQ -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Domande Frequenti</h5>
    </div>
    <div class="card-body">
        <div class="mb-4">
            <h5>Come funziona la fatturazione?</h5>
            <p>Gli abbonamenti vengono addebitati mensilmente. Quando ti abboni, il pagamento verrà elaborato immediatamente e il tuo abbonamento sarà attivo per 30 giorni. Riceverai una notifica via email prima del rinnovo.</p>
        </div>

        <div class="mb-4">
            <h5>Posso annullare in qualsiasi momento?</h5>
            <p>Sì, puoi annullare il tuo abbonamento in qualsiasi momento. Continuerai ad avere accesso al piano a pagamento fino alla fine del periodo di fatturazione corrente.</p>
        </div>

        <div class="mb-4">
            <h5>Cosa succede se supero il limite di richieste?</h5>
            <p>Se raggiungi il limite di richieste del tuo piano, non potrai effettuare ulteriori richieste fino al prossimo ciclo di fatturazione. Puoi sempre passare a un piano superiore in qualsiasi momento per ottenere più richieste.</p>
        </div>

        <div class="mb-4">
            <h5>Come funziona il pagamento?</h5>
            <p>Utilizziamo Stripe per elaborare tutti i pagamenti in modo sicuro. Accettiamo tutte le principali carte di credito e di debito.</p>
        </div>

        <div>
            <h5>Posso ricevere una fattura?</h5>
            <p>Sì, tutte le fatture sono disponibili nella sezione <a href="index.php?page=payment-history">Storia pagamenti</a>. Puoi scaricarle in formato PDF in qualsiasi momento.</p>
        </div>
    </div>
</div>
