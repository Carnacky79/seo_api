<?php

namespace SeoMetadataApi\Metadata;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class ContentAnalyzer {
    private $client;

    public function __construct() {
        $this->client = new Client([
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'SEO Metadata API/1.0'
            ]
        ]);
    }

    /**
     * Recupera il contenuto di una pagina web
     *
     * @param string $url URL della pagina da analizzare
     * @return string HTML della pagina
     */
    public function fetchContent($url) {
        try {
            $response = $this->client->get($url);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            throw new \Exception("Impossibile recuperare il contenuto della pagina: " . $e->getMessage());
        }
    }

    /**
     * Analizza il contenuto della pagina per estrarre informazioni rilevanti
     *
     * @param string $html HTML della pagina
     * @return array Dati estratti dalla pagina
     */
    public function analyzeContent($html) {
        $crawler = new Crawler($html);

        // Estrai il titolo
        $title = $this->extractTitle($crawler);

        // Estrai la descrizione
        $description = $this->extractDescription($crawler);

        // Estrai le parole chiave
        $keywords = $this->extractKeywords($crawler, $title, $description);

        // Estrai i tag Open Graph esistenti
        $ogTags = $this->extractOpenGraphTags($crawler);

        // Estrai eventuali metadati strutturati (JSON-LD)
        $structuredData = $this->extractStructuredData($crawler);

        // Analizza la struttura dei contenuti e degli heading
        $headings = $this->extractHeadings($crawler);

        // Analizza lunghezza contenuto e densità parole chiave
        $contentStats = $this->analyzeContentStats($crawler);

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'og_tags' => $ogTags,
            'structured_data' => $structuredData,
            'headings' => $headings,
            'content_stats' => $contentStats
        ];
    }

    /**
     * Estrae il titolo della pagina
     */
    private function extractTitle(Crawler $crawler) {
        // Prima cerca il tag title
        $title = $crawler->filter('title')->count() > 0
            ? trim($crawler->filter('title')->text())
            : '';

        // Se non c'è, cerca l'heading principale
        if (empty($title) && $crawler->filter('h1')->count() > 0) {
            $title = trim($crawler->filter('h1')->text());
        }

        return $title;
    }

    /**
     * Estrae la descrizione della pagina
     */
    private function extractDescription(Crawler $crawler) {
        // Prima cerca il meta tag description
        $description = '';

        if ($crawler->filter('meta[name="description"]')->count() > 0) {
            $description = $crawler->filter('meta[name="description"]')->attr('content');
        } elseif ($crawler->filter('meta[property="og:description"]')->count() > 0) {
            $description = $crawler->filter('meta[property="og:description"]')->attr('content');
        } else {
            // Estrai i primi paragrafi
            $paragraphs = $crawler->filter('p')->each(function (Crawler $node) {
                return trim($node->text());
            });

            // Trova il primo paragrafo non vuoto
            foreach ($paragraphs as $p) {
                if (strlen($p) > 50) {
                    $description = $p;
                    break;
                }
            }

            // Limita la lunghezza
            if (strlen($description) > 160) {
                $description = substr($description, 0, 157) . '...';
            }
        }

        return trim($description);
    }

    /**
     * Estrae le parole chiave dalla pagina
     */
    private function extractKeywords(Crawler $crawler, $title, $description) {
        $keywords = [];

        // Cerca meta keywords esistenti
        if ($crawler->filter('meta[name="keywords"]')->count() > 0) {
            $metaKeywords = $crawler->filter('meta[name="keywords"]')->attr('content');
            $keywordList = explode(',', $metaKeywords);
            foreach ($keywordList as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    $keywords[] = $keyword;
                }
            }
        }

        // Se non ci sono meta keywords, estrai dal contenuto
        if (empty($keywords)) {
            // Unisci titolo e descrizione per l'analisi
            $text = $title . ' ' . $description;

            // Aggiungi il testo di h1, h2, h3
            $headingText = '';
            $crawler->filter('h1, h2, h3')->each(function (Crawler $node) use (&$headingText) {
                $headingText .= ' ' . $node->text();
            });
            $text .= ' ' . $headingText;

            // Rimuovi punteggiatura e converti in minuscolo
            $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
            $text = mb_strtolower($text, 'UTF-8');

            // Dividi in parole
            $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

            // Conta le occorrenze delle parole
            $wordCount = array_count_values($words);

            // Rimuovi le parole comuni (stop words)
            $stopWords = $this->getStopWords();
            foreach ($stopWords as $stopWord) {
                unset($wordCount[$stopWord]);
            }

            // Ordina per frequenza
            arsort($wordCount);

            // Prendi le prime 10 parole chiave
            $keywords = array_slice(array_keys($wordCount), 0, 10);
        }

        return $keywords;
    }

    /**
     * Estrae i tag Open Graph dalla pagina
     */
    private function extractOpenGraphTags(Crawler $crawler) {
        $ogTags = [];

        $crawler->filter('meta[property^="og:"]')->each(function (Crawler $node) use (&$ogTags) {
            $property = $node->attr('property');
            $content = $node->attr('content');
            $ogTags[$property] = $content;
        });

        return $ogTags;
    }

    /**
     * Estrae i dati strutturati (JSON-LD) dalla pagina
     */
    private function extractStructuredData(Crawler $crawler) {
        $structuredData = [];

        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$structuredData) {
            $json = $node->text();
            try {
                $data = json_decode($json, true);
                if ($data) {
                    $structuredData[] = $data;
                }
            } catch (\Exception $e) {
                // Ignora JSON non valido
            }
        });

        return $structuredData;
    }

    /**
     * Estrae gli heading dalla pagina
     */
    private function extractHeadings(Crawler $crawler) {
        $headings = [
            'h1' => [],
            'h2' => [],
            'h3' => []
        ];

        // Estrai h1
        $crawler->filter('h1')->each(function (Crawler $node) use (&$headings) {
            $headings['h1'][] = trim($node->text());
        });

        // Estrai h2
        $crawler->filter('h2')->each(function (Crawler $node) use (&$headings) {
            $headings['h2'][] = trim($node->text());
        });

        // Estrai h3
        $crawler->filter('h3')->each(function (Crawler $node) use (&$headings) {
            $headings['h3'][] = trim($node->text());
        });

        return $headings;
    }

    /**
     * Analizza statistiche del contenuto
     */
    private function analyzeContentStats(Crawler $crawler) {
        // Estrai il testo principale
        $mainContent = '';
        $crawler->filter('p, li, h1, h2, h3, h4, h5, h6')->each(function (Crawler $node) use (&$mainContent) {
            $mainContent .= ' ' . $node->text();
        });

        // Conta parole
        $wordCount = count(preg_split('/\s+/', $mainContent, -1, PREG_SPLIT_NO_EMPTY));

        // Conta caratteri
        $charCount = mb_strlen(strip_tags($mainContent), 'UTF-8');

        return [
            'word_count' => $wordCount,
            'char_count' => $charCount
        ];
    }

    /**
     * Restituisce un elenco di stop words comuni
     */
    private function getStopWords() {
        return ['a', 'about', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'have',
            'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the', 'to', 'was', 'were', 'will',
            'with', 'il', 'lo', 'la', 'le', 'un', 'una', 'del', 'della', 'e', 'che', 'di', 'da',
            'in', 'per', 'con'];
    }
}
