<?php

namespace SeoMetadataApi\Metadata;

class MetadataGenerator {
    private $contentAnalyzer;
    private $seoOptimizer;

    public function __construct() {
        $this->contentAnalyzer = new ContentAnalyzer();
        $this->seoOptimizer = new SeoOptimizer();
    }

    /**
     * Genera metadati SEO ottimizzati per un URL
     *
     * @param string $url URL della pagina da analizzare
     * @return array Metadati SEO ottimizzati
     */
    public function generateMetadata($url) {
        // Recupera il contenuto della pagina
        $html = $this->contentAnalyzer->fetchContent($url);

        // Analizza il contenuto
        $contentData = $this->contentAnalyzer->analyzeContent($html);

        // Ottimizza i metadati
        $optimizedMetadata = $this->seoOptimizer->optimizeMetadata($contentData);

        // Prepara l'output finale
        $result = $this->prepareOutput($url, $contentData, $optimizedMetadata);

        return $result;
    }

    /**
     * Prepara l'output finale con metadati e suggerimenti
     */
    private function prepareOutput($url, $contentData, $optimizedMetadata) {
        // Crea HTML dei metadati ottimizzati
        $metadataHtml = $this->generateMetadataHtml($optimizedMetadata);

        // Formatta l'output
        $output = [
            'url' => $url,
            'original_metadata' => [
                'title' => $contentData['title'],
                'description' => $contentData['description'],
                'keywords' => $contentData['keywords'],
                'og_tags' => $contentData['og_tags']
            ],
            'optimized_metadata' => [
                'title' => $optimizedMetadata['title'],
                'description' => $optimizedMetadata['description'],
                'keywords' => $optimizedMetadata['keywords'],
                'og_tags' => $optimizedMetadata['og_tags'],
                'twitter_cards' => $optimizedMetadata['twitter_cards'],
                'structured_data' => $optimizedMetadata['structured_data']
            ],
            'suggestions' => $optimizedMetadata['suggestions'],
            'metadata_html' => $metadataHtml,
            'content_stats' => $contentData['content_stats'],
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $output;
    }

    /**
     * Genera codice HTML dei metadati ottimizzati
     */
    private function generateMetadataHtml($optimizedMetadata) {
        $html = "<!-- SEO Metadata ottimizzati generati automaticamente -->\n";

        // Title
        $html .= "<title>{$optimizedMetadata['title']}</title>\n";

        // Meta description
        $html .= "<meta name=\"description\" content=\"{$optimizedMetadata['description']}\">\n";

        // Meta keywords
        if (!empty($optimizedMetadata['keywords'])) {
            $keywordsString = implode(', ', $optimizedMetadata['keywords']);
            $html .= "<meta name=\"keywords\" content=\"{$keywordsString}\">\n";
        }

        // Open Graph tags
        foreach ($optimizedMetadata['og_tags'] as $property => $content) {
            $html .= "<meta property=\"{$property}\" content=\"{$content}\">\n";
        }

        // Twitter Cards
        foreach ($optimizedMetadata['twitter_cards'] as $name => $content) {
            $html .= "<meta name=\"{$name}\" content=\"{$content}\">\n";
        }

        // Structured Data (JSON-LD)
        if (!empty($optimizedMetadata['structured_data'])) {
            $html .= "<script type=\"application/ld+json\">\n";
            $html .= json_encode($optimizedMetadata['structured_data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $html .= "\n</script>\n";
        }

        return $html;
    }
