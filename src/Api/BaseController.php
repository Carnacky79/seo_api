<?php

namespace SeoMetadataApi\Api;

class BaseController {
    // Istanza del database
    protected $db;

    public function __construct() {
        $this->db = \SeoMetadataApi\Config\Database::getInstance();
    }
    /**
     * Invia una risposta JSON
     *
     * @param mixed $data Dati da includere nella risposta
     * @param int $statusCode Codice di stato HTTP
     * @return void
     */
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // Previeni il caching delle risposte API
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Invia una risposta di errore
     *
     * @param string $message Messaggio di errore
     * @param int $statusCode Codice di stato HTTP
     * @return void
     */
    protected function errorResponse($message, $statusCode = 400) {
        $this->jsonResponse([
            'status' => 'error',
            'message' => $message
        ], $statusCode);
    }

    /**
     * Ottiene i dati dalla richiesta POST in formato JSON
     *
     * @return array Dati della richiesta
     */
    protected function getJsonInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errorResponse('JSON non valido: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Verifica che i parametri richiesti siano presenti
     *
     * @param array $data Dati della richiesta
     * @param array $requiredParams Parametri richiesti
     * @return bool
     */
    protected function validateRequiredParams($data, $requiredParams) {
        foreach ($requiredParams as $param) {
            if (!isset($data[$param]) || empty($data[$param])) {
                $this->errorResponse("Parametro mancante: {$param}");
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica che il metodo della richiesta sia quello atteso
     *
     * @param string $method Metodo atteso (GET, POST, ecc.)
     * @return bool
     */
    protected function validateRequestMethod($method) {
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            $this->errorResponse("Metodo non consentito: {$_SERVER['REQUEST_METHOD']}", 405);
            return false;
        }

        return true;
    }
}
