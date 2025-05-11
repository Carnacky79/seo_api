<?php

namespace SeoMetadataApi\Auth;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailManager {
    private $mailer;

    public function __construct() {
        // Carica le variabili d'ambiente
        $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();

        // Configura PHPMailer
        $this->mailer = new PHPMailer(true);

        // Configura il server SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $this->mailer->Port = $_ENV['MAIL_PORT'];

        // Configura il mittente
        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);

        // Configura la lingua
        $this->mailer->setLanguage('it');

        // Configura il formato HTML
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Invia un'email di verifica all'utente
     *
     * @param string $email Email del destinatario
     * @param string $firstName Nome del destinatario
     * @param string $verificationToken Token di verifica
     * @return bool True se l'email è stata inviata con successo
     */
    public function sendVerificationEmail($email, $firstName, $verificationToken) {
        try {
            // Imposta il destinatario
            $this->mailer->addAddress($email);

            // Imposta l'oggetto
            $this->mailer->Subject = 'Verifica il tuo indirizzo email - SEO Metadata API';

            // URL di verifica
            $verificationUrl = $_ENV['APP_URL'] . '/api/user/verify?token=' . $verificationToken;

            // Corpo dell'email in HTML
            $this->mailer->Body = $this->getVerificationEmailTemplate($firstName, $verificationUrl);

            // Corpo dell'email in testo semplice
            $this->mailer->AltBody = "Ciao $firstName, grazie per esserti registrato! Per verificare il tuo indirizzo email, visita questo link: $verificationUrl";

            // Invia l'email
            $this->mailer->send();

            return true;
        } catch (Exception $e) {
            // Registra l'errore (in un'applicazione reale, utilizzare un logger)
            error_log('Errore nell\'invio dell\'email: ' . $this->mailer->ErrorInfo);

            return false;
        }
    }

    /**
     * Invia un'email di recupero password
     *
     * @param string $email Email del destinatario
     * @param string $firstName Nome del destinatario
     * @param string $resetToken Token di reset
     * @return bool True se l'email è stata inviata con successo
     */
    public function sendPasswordResetEmail($email, $firstName, $resetToken) {
        try {
            // Imposta il destinatario
            $this->mailer->addAddress($email);

            // Imposta l'oggetto
            $this->mailer->Subject = 'Recupero password - SEO Metadata API';

            // URL di reset
            $resetUrl = $_ENV['APP_URL'] . '/password/reset?token=' . $resetToken;

            // Corpo dell'email in HTML
            $this->mailer->Body = $this->getPasswordResetTemplate($firstName, $resetUrl);

            // Corpo dell'email in testo semplice
            $this->mailer->AltBody = "Ciao $firstName, abbiamo ricevuto una richiesta di recupero password. Per reimpostare la tua password, visita questo link: $resetUrl";

            // Invia l'email
            $this->mailer->send();

            return true;
        } catch (Exception $e) {
            // Registra l'errore
            error_log('Errore nell\'invio dell\'email: ' . $this->mailer->ErrorInfo);

            return false;
        }
    }

    /**
     * Ottiene il template HTML per l'email di verifica
     *
     * @param string $firstName Nome del destinatario
     * @param string $verificationUrl URL di verifica
     * @return string Template HTML
     */
    private function getVerificationEmailTemplate($firstName, $verificationUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Verifica il tuo indirizzo email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                .container { padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px; }
                .header { text-align: center; padding-bottom: 10px; border-bottom: 1px solid #e1e1e1; margin-bottom: 20px; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
                .footer { margin-top: 30px; font-size: 12px; color: #777; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Verifica il tuo indirizzo email</h2>
                </div>
                <p>Ciao ' . htmlspecialchars($firstName) . ',</p>
                <p>Grazie per esserti registrato alla nostra API per la generazione automatica di metadati SEO!</p>
                <p>Per completare la registrazione e iniziare a utilizzare il servizio, devi verificare il tuo indirizzo email cliccando sul pulsante qui sotto:</p>
                <p style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($verificationUrl) . '" class="btn">Verifica Email</a>
                </p>
                <p>Oppure copia e incolla questo link nel tuo browser:</p>
                <p>' . htmlspecialchars($verificationUrl) . '</p>
                <p>Se non hai creato un account, puoi ignorare questa email.</p>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' SEO Metadata API. Tutti i diritti riservati.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Ottiene il template HTML per l'email di recupero password
     *
     * @param string $firstName Nome del destinatario
     * @param string $resetUrl URL di reset
     * @return string Template HTML
     */
    private function getPasswordResetTemplate($firstName, $resetUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Recupero password</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                .container { padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px; }
                .header { text-align: center; padding-bottom: 10px; border-bottom: 1px solid #e1e1e1; margin-bottom: 20px; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
                .footer { margin-top: 30px; font-size: 12px; color: #777; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Recupero password</h2>
                </div>
                <p>Ciao ' . htmlspecialchars($firstName) . ',</p>
                <p>Abbiamo ricevuto una richiesta di recupero password per il tuo account.</p>
                <p>Per reimpostare la tua password, clicca sul pulsante qui sotto:</p>
                <p style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($resetUrl) . '" class="btn">Reimposta Password</a>
                </p>
                <p>Oppure copia e incolla questo link nel tuo browser:</p>
                <p>' . htmlspecialchars($resetUrl) . '</p>
                <p>Se non hai richiesto il recupero della password, puoi ignorare questa email.</p>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' SEO Metadata API. Tutti i diritti riservati.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
