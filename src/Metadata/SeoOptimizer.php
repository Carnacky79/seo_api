<?php

namespace SeoMetadataApi\Metadata;

class SeoOptimizer {
    /**
     * Ottimizza i metadati SEO in base all'analisi del contenuto
     *
     * @param array $contentData Dati estratti dalla pagina
     * @return array Metadati SEO ottimizzati
     */
    public function optimizeMetadata($contentData) {
        $optimizedData = [
            'title' => $this->optimizeTitle($contentData),
            'description' => $this->optimizeDescription($contentData),
            'keywords' => $this->optimizeKeywords($contentData),
            'og_tags' => $this->optimizeOpenGraph($contentData),
            'twitter_cards' => $this->createTwitterCards($contentData),
            'structured_data' => $this->optimizeStructuredData($contentData),
            'suggestions' => $this->generateSuggestions($contentData)
        ];

        return $optimizedData;
    }

    /**
     * Ottimizza il titolo della pagina
     */
    private function optimizeTitle($contentData) {
        $title = $contentData['title'];

        // Se il titolo è vuoto, genera un suggerimento basato sui contenuti
        if (empty($title) && !empty($contentData['headings']['h1'])) {
            $title = $contentData['headings']['h1'][0];
        }

        // Verifica lunghezza ottimale (50-60 caratteri)
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 57) . '...';
        } elseif (mb_strlen($title) < 30 && !empty($contentData['keywords'])) {
            // Aggiunge parole chiave se il titolo è troppo corto
            $title .= ' - ' . ucfirst($contentData['keywords'][0]);
        }

        return $title;
    }

    /**
     * Ottimizza la descrizione della pagina
     */
    private function optimizeDescription($contentData) {
        $description = $contentData['description'];

        // Verifica lunghezza ottimale (150-160 caratteri)
        if (mb_strlen($description) > 160) {
            $description = mb_substr($description, 0, 157) . '...';
        } elseif (mb_strlen($description) < 70) {
            // Descrizione troppo corta, prova ad arricchirla
            if (!empty($contentData['headings']['h2'])) {
                $extraInfo = ' ' . $contentData['headings']['h2'][0];
                $description .= $extraInfo;

                if (mb_strlen($description) > 160) {
                    $description = mb_substr($description, 0, 157) . '...';
                }
            }
        }

        // Assicurati che includa almeno una parola chiave principale
        if (!empty($contentData['keywords']) && !$this->containsKeyword($description, $contentData['keywords'][0])) {
            $keyword = $contentData['keywords'][0];
            if (mb_strlen($description) + mb_strlen($keyword) + 5 <= 160) {
                $description .= ' ' . ucfirst($keyword) . '.';
            }
        }

        return $description;
    }

    /**
     * Ottimizza le parole chiave
     */
    private function optimizeKeywords($contentData) {
        $keywords = $contentData['keywords'];

        // Limita a 5-7 parole chiave
        if (count($keywords) > 7) {
            $keywords = array_slice($keywords, 0, 7);
        }

        return $keywords;
    }

    /**
     * Ottimizza i tag Open Graph
     */
    private function optimizeOpenGraph($contentData) {
        $ogTags = $contentData['og_tags'];
        $optimizedOgTags = [];

        // Titolo OG
        $optimizedOgTags['og:title'] = isset($ogTags['og:title']) ?
            $ogTags['og:title'] : $this->optimizeTitle($contentData);

        // Descrizione OG
        $optimizedOgTags['og:description'] = isset($ogTags['og:description']) ?
            $ogTags['og:description'] : $this->optimizeDescription($contentData);

        // Tipo OG
        $optimizedOgTags['og:type'] = isset($ogTags['og:type']) ?
            $ogTags['og:type'] : 'website';

        // Immagine OG
        if (isset($ogTags['og:image'])) {
            $optimizedOgTags['og:image'] = $ogTags['og:image'];
        }

        // URL OG
        if (isset($ogTags['og:url'])) {
            $optimizedOgTags['og:url'] = $ogTags['og:url'];
        }

        // Locale OG
        $optimizedOgTags['og:locale'] = isset($ogTags['og:locale']) ?
            $ogTags['og:locale'] : 'it_IT';

        return $optimizedOgTags;
    }

    /**
     * Crea i tag per Twitter Cards
     */
    private function createTwitterCards($contentData) {
        $twitterCards = [];

        // Tipo di card
        $twitterCards['twitter:card'] = 'summary_large_image';

        // Titolo
        $twitterCards['twitter:title'] = $this->optimizeTitle($contentData);

        // Descrizione
        $twitterCards['twitter:description'] = $this->optimizeDescription($contentData);

        // Immagine (usa l'immagine OG se disponibile)
        if (isset($contentData['og_tags']['og:image'])) {
            $twitterCards['twitter:image'] = $contentData['og_tags']['og:image'];
        }

        return $twitterCards;
    }

    /**
     * Ottimizza i dati strutturati
     */
    private function optimizeStructuredData($contentData) {
        $structuredData = $contentData['structured_data'];
        $optimizedData = [];

        // Se non ci sono dati strutturati, crea un oggetto WebPage di base
        if (empty($structuredData)) {
            $webPage = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $this->optimizeTitle($contentData),
                'description' => $this->optimizeDescription($contentData)
            ];

            // Aggiungi breadcrumb se ci sono H2
            if (!empty($contentData['headings']['h2'])) {
                $webPage['breadcrumb'] = [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Home'
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => $contentData['headings']['h2'][0]
                        ]
                    ]
                ];
            }

            $optimizedData[] = $webPage;
        } else {
            // Usa i dati strutturati esistenti, aggiungendo eventuali miglioramenti
            foreach ($structuredData as $data) {
                // Se è un oggetto WebPage, migliora i dati
                if (isset($data['@type']) && $data['@type'] === 'WebPage') {
                    $data['name'] = $this->optimizeTitle($contentData);
                    $data['description'] = $this->optimizeDescription($contentData);
                }

                $optimizedData[] = $data;
            }
        }

        return $optimizedData;
    }

    /**
     * Genera suggerimenti per il miglioramento SEO
     */
    private function generateSuggestions($contentData) {
        $suggestions = [];

        // Verifica lunghezza del titolo
        $titleLength = mb_strlen($contentData['title']);
        if ($titleLength < 30) {
            $suggestions[] = "Il titolo è troppo corto ({$titleLength} caratteri). Idealmente dovrebbe essere di 50-60 caratteri.";
        } elseif ($titleLength > 60) {
            $suggestions[] = "Il titolo è troppo lungo ({$titleLength} caratteri). Potrebbe essere troncato nei risultati di ricerca. Limitalo a 50-60 caratteri.";
        }

        // Verifica lunghezza della descrizione
        $descLength = mb_strlen($contentData['description']);
        if ($descLength < 70) {
            $suggestions[] = "La meta description è troppo corta ({$descLength} caratteri). Idealmente dovrebbe essere di 150-160 caratteri.";
        } elseif ($descLength > 160) {
            $suggestions[] = "La meta description è troppo lunga ({$descLength} caratteri). Potrebbe essere troncata nei risultati di ricerca. Limitala a 150-160 caratteri.";
        }

        // Verifica presenza H1
        if (empty($contentData['headings']['h1'])) {
            $suggestions[] = "La pagina non contiene un tag H1. Aggiungine uno con la parola chiave principale.";
        } elseif (count($contentData['headings']['h1']) > 1) {
            $suggestions[] = "La pagina contiene più di un tag H1. Idealmente, dovrebbe esserci un solo H1 per pagina.";
        }

        // Verifica struttura degli heading
        if (empty($contentData['headings']['h2'])) {
            $suggestions[] = "La pagina non contiene tag H2. Utilizzali per strutturare i contenuti e includere parole chiave secondarie.";
        }

        // Verifica lunghezza contenuto
        $wordCount = $contentData['content_stats']['word_count'];
        if ($wordCount < 300) {
            $suggestions[] = "Il contenuto della pagina è piuttosto breve ({$wordCount} parole). Per posizionarsi meglio, considera di ampliare il contenuto a almeno 500-700 parole.";
        }

        // Verifica presenza Open Graph
        if (empty($contentData['og_tags'])) {
            $suggestions[] = "Mancano i tag Open Graph. Aggiungerli migliorerà la condivisione sui social media.";
        } elseif (!isset($contentData['og_tags']['og:image'])) {
            $suggestions[] = "Manca l'immagine nei tag Open Graph. Aggiungine una per migliorare l'aspetto nei social media.";
        }

        // Verifica presenza dati strutturati
        if (empty($contentData['structured_data'])) {
            $suggestions[] = "La pagina non contiene dati strutturati (schema.org). Aggiungerli può migliorare la visibilità nei risultati di ricerca.";
        }

        return $suggestions;
    }

    /**
     * Verifica se una stringa contiene una parola chiave
     */
    private function containsKeyword($text, $keyword) {
        return mb_stripos($text, $keyword) !== false;
    }
}
