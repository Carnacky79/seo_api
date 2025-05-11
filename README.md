# API per la Generazione Automatica di Metadati SEO

Questa API consente di generare automaticamente metadati SEO ottimizzati per qualsiasi pagina web, con un sistema di gestione degli abbonamenti basato su Stripe.

## Piani di Abbonamento

- **Gratuito**: 10 richieste al mese
- **Pro**: 1.000 richieste al mese a 20 € mensili
- **Premium**: richieste illimitate a 50 € mensili

## Architettura

- **Backend**: PHP (stack LAMP)
- **Database**: MySQL
- **Gestione abbonamenti e pagamenti**: Stripe Billing
- **Autenticazione API**: token basati su chiavi API
- **Hosting**: server Apache

## Installazione

1. Clona il repository:
   ```bash
   git clone https://github.com/tuousername/seo-metadata-api.git
   cd seo-metadata-api
   ```

2. Installa le dipendenze con Composer:
   ```bash
   composer install
   ```

3. Crea il database MySQL:
   ```bash
   mysql -u root -p < database/schema.sql
   ```

4. Copia il file `.env.example` in `.env` e aggiorna le variabili d'ambiente:
   ```bash
   cp .env.example .env
   nano .env
   ```

5. Configura il server web per puntare alla directory `public/` come root.

## Configurazione di Stripe

1. Crea un account su [Stripe](https://stripe.com/)
2. Crea due prodotti/piani:
   - Piano Pro: 20 € al mese
   - Piano Premium: 50 € al mese
3. Ottieni le chiavi API e il segreto webhook
4. Aggiungi le chiavi nel file `.env`
5. Configura il webhook di Stripe per puntare a `https://il-tuo-dominio.com/webhook.php`

## Endpoint API

### Generazione Metadati

```
POST /api/metadata/generate
```

**Header richiesti:**
- `X-API-Key`: La tua chiave API
- `Content-Type: application/json`

**Body della richiesta:**
```json
{
   "url": "https://www.esempio.com/pagina"
}
```

**Risposta di esempio:**
```json
{
   "status": "success",
   "url": "https://www.esempio.com/pagina",
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
}
```

### Gestione Utenti

#### Registrazione

```
POST /api/user/register
```

**Body della richiesta:**
```json
{
   "email": "utente@esempio.com",
   "password": "password_sicura",
   "first_name": "Mario",
   "last_name": "Rossi",
   "fiscal_code": "RSSMRA80A01H501U",
   "phone": "+39123456789",       // opzionale
   "company": "Azienda Srl",      // opzionale
   "vat_number": "12345678901"    // opzionale
}
```

**Nota**: Dopo la registrazione, il sistema invia un'email di verifica all'indirizzo fornito. L'account non sarà attivo finché l'email non viene verificata.

#### Verifica Email

```
GET /api/user/verify?token=token_di_verifica
```

Questo endpoint è accessibile tramite il link inviato nell'email di verifica. Una volta verificata l'email, l'utente sarà reindirizzato a una pagina di conferma.

#### Reinvio Email di Verifica

```
POST /api/user/resend-verification
```

**Body della richiesta:**
```json
{
  "email": "utente@esempio.com"
}
```

#### Login

```
POST /api/user/login
```

**Body della richiesta:**
```json
{
  "email": "utente@esempio.com",
  "password": "password_sicura"
}
```

#### Profilo Utente

```
GET /api/user/profile
```

**Header richiesti:**
- `X-API-Key`: La tua chiave API

```
POST /api/user/profile
```

**Header richiesti:**
- `X-API-Key`: La tua chiave API

**Body della richiesta per aggiornare il profilo:**
```json
{
  "phone": "+39987654321",
  "company": "Nuova Azienda Srl",
  "vat_number": "98765432101"
}
```

**Body della richiesta per rigenerare la chiave API:**
```json
{
  "regenerate_api_key": true
}
```

**Body della richiesta per cambiare password:**
```json
{
  "current_password": "password_attuale",
  "new_password": "nuova_password"
}
```

### Gestione Abbonamenti

#### Piani Disponibili

```
GET /api/subscription/plans
```

#### Checkout

```
POST /api/subscription/checkout
```

**Header richiesti:**
- `X-API-Key`: La tua chiave API
- `Content-Type: application/json`

**Body della richiesta:**
```json
{
  "plan_type": "pro"
}
```

#### Stato Abbonamento

```
GET /api/subscription/status
```

**Header richiesti:**
- `X-API-Key`: La tua chiave API

### Statistiche di Utilizzo

```
GET /api/usage/stats
```

**Header richiesti:**
- `X-API-Key`: La tua chiave API

## Distribuire su RapidAPI

Per monetizzare l'API su RapidAPI:

1. Registra un account su [RapidAPI](https://rapidapi.com/)
2. Crea una nuova API
3. Configura i piani tariffari come specificato
4. Aggiungi gli endpoint e la documentazione
5. Pubblica l'API

## Sicurezza

- Tutte le password sono hashate nel database
- Le chiavi API sono generate con entropia sufficiente
- Le richieste API sono limitate in base al piano dell'utente
- Le transazioni con Stripe sono gestite in modo sicuro

## Risoluzione dei Problemi

- Controlla i log di Apache e PHP in caso di errori
- Verifica la configurazione del database in `.env`
- Assicurati che tutte le dipendenze siano installate correttamente
- Verifica che Stripe sia configurato correttamente
