<?php
// app/Services/GeminiService.php

namespace App\Services;

use App\Config\Config;

class GeminiService
{
    private $apiKey;

    public function __construct($customApiKey = null)
    {
        $this->apiKey = $customApiKey ?: Config::get('GEMINI_API_KEY');
    }

    public function getDefaultStrategyPrompt($agent)
    {
        if ($agent === 'gianik') {
            return "Sei un ANALISTA ELITE e TRADER di Betfair. Il tuo compito è analizzare un EVENTO LIVE con i suoi molteplici mercati e decidere l'operazione migliore.\n\n" .
                "IL TUO VANTAGGIO STRATEGICO:\n" .
                "1. Analizza TUTTI i mercati forniti (Match Odds, Double Chance, varie linee di Under/Over, BTTS).\n" .
                "2. Scegli l'operazione che offre il miglior rapporto rischio/rendimento. Non sei obbligato a scegliere il mercato principale se un altro (es. Over 1.5) è più sicuro o profittevole.\n" .
                "3. Analizza quote Back/Lay, volumi e DATI STATISTICI LIVE.\n" .
                "4. Usa la CLASSIFICA e i PRONOSTICI esterni (predictions) per validare la tua scelta.\n" .
                "5. Sii molto tecnico nella spiegazione (motivation), correlando stats live, classifica e volumi Betfair.\n" .
                "6. SOGLIA DI CONFIDENZA: La tua 'confidence' (0-100) deve rispecchiare la PROBABILITÀ REALE che l'evento si verifichi. Non gonfiare i numeri.\n" .
                "   Se la quota è 1.50 (probabilità implicita 66%) e tu stimi una probabilità del 60%, la tua confidence deve essere 60.";
        } elseif ($agent === 'dio') {
            return "Sei un QUANT TRADER denominato 'Dio'. Non sei uno scommettitore, sei un analista di Price Action (Tape Reading).\n\n" .
                "IL TUO VANTAGGIO (Price Action Rules):\n" .
                "1. Analizza i volumi (totalMatched) e le quote Back/Lay.\n" .
                "2. Analizza ogni evento nel batch e identifica le migliori opportunità.\n" .
                "3. Se un evento è convincente, proponi l'operazione. Se il rischio/rendimento è scarso, scrivi 'PASS'.\n" .
                "4. NON INVENTARE QUOTE: usa solo quelle presenti nel JSON per il runner scelto.\n" .
                "5. CONFIDENCE: La tua 'confidence' (0-100) deve rispecchiare la PROBABILITÀ REALE stimata. Sii brutale e onesto. Fornisci SEMPRE un valore numerico per la confidence, anche se decidi di passare.\n" .
                "6. Analizza 'lastPriceTraded' vs Quota Attuale: Se divergono, identifica il momentum.\n" .
                "7. Analizza lo SCORE vs QUOTE: Se le quote non riflettono correttamente l'andamento del match, identifica il valore.\n" .
                "8. VOLUMI: Un volume alto indica precisione. Se il volume è basso, sii estremamente prudente.\n" .
                "9. IGNORA i nomi delle squadre/atleti. Guarda solo l'efficienza del mercato.\n" .
                "10. Se per uno sport non hai dati statistici (ma solo quote), sii più prudente.";
        } elseif ($agent === 'batch') {
            return "Sei un ANALISTA ELITE e TRADER di Betfair Exchange. Il tuo compito è analizzare un BATCH di EVENTI LIVE (Calcio) e decidere le operazioni migliori.\n\n" .
                "REGOLE RIGIDE:\n" .
                "1. Per ogni evento, analizza i mercati, i volumi, i dati statistici live, il momentum e il contesto storico.\n" .
                "2. Scegli l'operazione migliore per ogni evento (o nessuna se il rischio/rendimento è scarso).\n" .
                "3. CONFIDENCE: La tua 'confidence' (0-100) deve rispecchiare la PROBABILITÀ REALE. Sii onesto: se la quota è 1.50 (66% imp) e tu stimi il 60%, scrivi confidence 60.\n" .
                "4. Quota Minima: 1.25. Se la quota attuale è inferiore ma l'evento è valido, scrivi '1.25' per piazzare un ordine limite.";
        }
        return "";
    }

    public function analyze($candidates, $options = [])
    {
        if (!$this->apiKey)
            return "Error: Missing Gemini API Key";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $balanceText = "";
        if (isset($options['current_portfolio'])) {
            $labelPortfolio = ($options['is_gianik'] ?? false) ? "Budget Totale Virtuale GiaNik" : "Portfolio Reale";
            $labelAvailable = ($options['is_gianik'] ?? false) ? "Disponibilità Virtuale GiaNik" : "Disponibilità";

            $balanceText = "SITUAZIONE PORTAFOGLIO:\n" .
                "- $labelPortfolio: " . number_format($options['current_portfolio'], 2) . "€\n" .
                "- $labelAvailable: " . number_format($options['available_balance'], 2) . "€\n\n";
        }

        $isUpcoming = $options['is_upcoming'] ?? false;
        $isGiaNik = $options['is_gianik'] ?? false;

        $minLiquidity = $options['min_liquidity'] ?? 2000;
        $minConfidence = $options['min_confidence'] ?? 80;
        $systemOverride = "SYSTEM OVERRIDE (USER SETTINGS):\n" .
            "- LIQUIDITY THRESHOLD: " . number_format($minLiquidity, 0, '', '') . "€. If volume > " . number_format($minLiquidity, 0, '', '') . ", consider it SUFFICIENT. Do not PASS solely because of volume if it exceeds this threshold.\n" .
            "- CONFIDENCE THRESHOLD: " . $minConfidence . "%. If you identify a profitable opportunity (Value Bet), your Confidence score MUST be scaled to at least " . $minConfidence . " (or higher) to trigger the bet. Do not output low confidence (e.g. 60) for valid trades just because the raw probability is low; scale it to the signal strength.";

        if ($isUpcoming) {
            $customPrompt = $options['custom_prompt'] ?? $this->getDefaultStrategyPrompt('upcoming');

            // Map placeholders
            $mapping = [
                '{{portfolio_stats}}' => $balanceText,
                '{{candidates_list}}' => "LISTA EVENTI CANDIDATI:\n" . json_encode($candidates)
            ];

            // If prompt contains placeholders, replace them. Otherwise, append data for backward compatibility.
            $hasPlaceholders = false;
            foreach(array_keys($mapping) as $key) if(strpos($customPrompt, $key) !== false) $hasPlaceholders = true;

            if ($hasPlaceholders) {
                $prompt = str_replace(array_keys($mapping), array_values($mapping), $customPrompt);
            } else {
                $prompt = $customPrompt . "\n\n" .
                    $mapping['{{candidates_list}}'] . "\n\n";
            }

            $prompt .= "\n\nSYSTEM CONSTRAINTS:\n" .
                "1. RISPONDI ESCLUSIVAMENTE IN FORMATO JSON (ARRAY DI OGGETTI).\n" .
                "[\n" .
                "  {\n" .
                "    \"marketId\": \"1.XXXXX\",\n" .
                "    \"event\": \"Team A v Team B\",\n" .
                "    \"sport\": \"Soccer\",\n" .
                "    \"competition\": \"Premier League\",\n" .
                "    \"advice\": \"Runner Name\",\n" .
                "    \"odds\": 1.80,\n" .
                "    \"confidence\": 85,\n" .
                "    \"totalMatched\": 5000,\n" .
                "    \"sentiment\": \"Bullish/Bearish/Neutral\",\n" .
                "    \"motivation\": \"Spiegazione tecnica del perché questo pronostico è di valore.\"\n" .
                "  }\n" .
                "]";
        } elseif ($isGiaNik) {
            $customPrompt = $options['custom_prompt'] ?? $this->getDefaultStrategyPrompt('gianik');

            $mapping = [
                '{{portfolio_stats}}' => $balanceText,
                '{{event_markets}}' => "DATI EVENTO E MERCATI:\n" . json_encode($candidates[0]),
                '{{api_football_raw}}' => "DATI STATISTICI AVANZATI (Se disponibili):\n" . (isset($candidates[0]['api_football']) ? json_encode($candidates[0]['api_football']) : "Non disponibili"),
                '{{historical_context}}' => "📚 CONTESTO STORICO E PRE-MATCH (Intelligence):\n" . (isset($candidates[0]['deep_context']) ? $candidates[0]['deep_context'] : "Non disponibile"),
                '{{performance_metrics}}' => "📈 PERFORMANCE STORICHE AI (Metriche):\n" . (isset($candidates[0]['performance_metrics']) ? $candidates[0]['performance_metrics'] : "Nessuna metrica disponibile"),
                '{{ai_lessons}}' => "📚 LEZIONI IMPARATE (Post-Mortem):\n" . (isset($candidates[0]['ai_lessons']) ? $candidates[0]['ai_lessons'] : "Nessuna lezione pertinente"),
                '{{live_match_data}}' => "🚨 DATI LIVE DEL MATCH:\n" .
                    "- live_score: " . json_encode($candidates[0]['api_football']['live']['live_score'] ?? []) . "\n" .
                    "- live_status: " . json_encode($candidates[0]['api_football']['live']['live_status'] ?? []) . "\n" .
                    "- match_info: " . json_encode($candidates[0]['api_football']['live']['match_info'] ?? []),
                '{{live_statistics}}' => "📊 STATISTICS LIVE DEL MATCH:\n" . (isset($candidates[0]['api_football']['statistics']) ? json_encode($candidates[0]['api_football']['statistics']) : "Non disponibili"),
                '{{momentum}}' => "⚡ MOMENTUM (Variazione ultimi 10-15 minuti):\n" . (isset($candidates[0]['api_football']['momentum']) ? $candidates[0]['api_football']['momentum'] : "Dati momentum non ancora disponibili."),
                '{{live_events}}' => "⚽ EVENTS LIVE DEL MATCH:\n" . (isset($candidates[0]['api_football']['events']) ? json_encode($candidates[0]['api_football']['events']) : "Non disponibili")
            ];

            $hasPlaceholders = false;
            foreach(array_keys($mapping) as $key) if(strpos($customPrompt, $key) !== false) $hasPlaceholders = true;

            if ($hasPlaceholders) {
                $prompt = str_replace(array_keys($mapping), array_values($mapping), $customPrompt);
            } else {
                $prompt = $customPrompt . "\n\n" .
                    implode("\n\n", array_values($mapping));
            }

            $prompt .= "\n\n" . $systemOverride . "\n\nSYSTEM CONSTRAINTS (MANDATORY):\n" .
                "1. REGOLA CALCIO: È permessa solo UNA scommessa attiva alla volta per match.\n" .
                "2. ⚠️ QUOTA MINIMA: 1.25. Se la quota attuale è inferiore ma l'evento è valido, imposta 'odds' a 1.25.\n" .
                "3. RISPONDI ESCLUSIVAMENTE IN FORMATO JSON.\n" .
                "4. STILE RISPOSTA: La 'motivation' deve essere sintetica (max 80 parole). Evita di ripetere dati già chiari.\n\n" .
                "RISPONDI ESCLUSIVAMENTE CON QUESTO SCHEMA JSON:\n" .
                "{\n" .
                "  \"eventName\": \"Team A v Team B\",\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"confidence\": 90,\n" .
                "  \"sentiment\": \"Bullish/Bearish/Neutral\",\n" .
                "  \"motivation\": \"Sintesi tecnica qui (Menziona SEMPRE i nomi delle squadre e i dati statistici chiave usati per la decisione).\"\n" .
                "}";
        } else {
            $customPrompt = $options['custom_prompt'] ?? $this->getDefaultStrategyPrompt('dio');

            $mapping = [
                '{{portfolio_stats}}' => $balanceText,
                '{{candidates_list}}' => "LISTA EVENTI LIVE CANDIDATI:\n" . json_encode($candidates)
            ];

            $hasPlaceholders = false;
            foreach(array_keys($mapping) as $key) if(strpos($customPrompt, $key) !== false) $hasPlaceholders = true;

            if ($hasPlaceholders) {
                $prompt = str_replace(array_keys($mapping), array_values($mapping), $customPrompt);
            } else {
                $prompt = $customPrompt . "\n\n" .
                    $mapping['{{portfolio_stats}}'] . "\n\n" .
                    $mapping['{{candidates_list}}'];
            }

            $prompt .= "\n\nSYSTEM CONSTRAINTS (MANDATORY):\n" .
                "1. QUOTA MINIMA: 1.25.\n" .
                "2. RISPONDI ESCLUSIVAMENTE IN FORMATO JSON:\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"confidence\": 90,\n" .
                "  \"sentiment\": \"Testo breve sul sentiment del mercato\",\n" .
                "  \"motivation\": \"Spiegazione tecnica dettagliata (perché questo evento e questo runner?)\"\n" .
                "}";
        }

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.1, // Più basso per maggiore precisione
                "response_mime_type" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error)
            return "CURL Error: " . $error;

        $result = json_decode($response, true);
        if (isset($result['error'])) {
            error_log("Gemini API Error: " . ($result['error']['message'] ?? 'Unknown Error'));
            return "Gemini API Error: " . ($result['error']['message'] ?? 'Unknown Error');
        }

        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? "";

        // Log response for debugging
        if (!file_exists(Config::LOGS_PATH))
            mkdir(Config::LOGS_PATH, 0777, true);
        file_put_contents(Config::LOGS_PATH . 'gemini_last_response.log', $text);

        return $text ?: "Error: Nessuna risposta valida dall'AI.";
    }

    public function analyzeBatch($events, $options = [])
    {
        if (!$this->apiKey) return "Error: Missing Gemini API Key";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $minLiquidity = $options['min_liquidity'] ?? 2000;
        $minConfidence = $options['min_confidence'] ?? 80;
        $systemOverride = "SYSTEM OVERRIDE (USER SETTINGS):\n" .
            "- LIQUIDITY THRESHOLD: " . number_format($minLiquidity, 0, '', '') . "€. If volume > " . number_format($minLiquidity, 0, '', '') . ", consider it SUFFICIENT. Do not PASS solely because of volume if it exceeds this threshold.\n" .
            "- CONFIDENCE THRESHOLD: " . $minConfidence . "%. If you identify a profitable opportunity (Value Bet), your Confidence score MUST be scaled to at least " . $minConfidence . " (or higher) to trigger the bet. Do not output low confidence (e.g. 60) for valid trades just because the raw probability is low; scale it to the signal strength.";

        $balanceText = "";
        if (isset($options['current_portfolio'])) {
            $labelPortfolio = ($options['is_gianik'] ?? false) ? "Budget Totale Virtuale GiaNik" : "Portfolio Reale";
            $labelAvailable = ($options['is_gianik'] ?? false) ? "Disponibilità Virtuale GiaNik" : "Disponibilità";

            $balanceText = "SITUAZIONE PORTAFOGLIO:\n" .
                "- $labelPortfolio: " . number_format($options['current_portfolio'], 2) . "€\n" .
                "- $labelAvailable: " . number_format($options['available_balance'], 2) . "€\n\n";
        }

        $customPrompt = $options['custom_prompt'] ?? $this->getDefaultStrategyPrompt('batch');

        $mapping = [
            '{{portfolio_stats}}' => $balanceText,
            '{{events_batch}}' => "LISTA EVENTI DA ANALIZZARE:\n" . json_encode($events, JSON_PRETTY_PRINT)
        ];

        $hasPlaceholders = false;
        foreach(array_keys($mapping) as $key) if(strpos($customPrompt, $key) !== false) $hasPlaceholders = true;

        if ($hasPlaceholders) {
            $prompt = str_replace(array_keys($mapping), array_values($mapping), $customPrompt);
        } else {
            $prompt = $customPrompt . "\n\n" .
                $mapping['{{portfolio_stats}}'] . "\n\n" .
                $mapping['{{events_batch}}'];
        }

        $prompt .= "\n\n" . $systemOverride . "\n\nSYSTEM CONSTRAINTS:\n" .
            "1. RISPONDI ESCLUSIVAMENTE CON QUESTO SCHEMA JSON (ARRAY DI OGGETTI):\n" .
            "[\n" .
            "  {\n" .
            "    \"eventName\": \"Team A v Team B\",\n" .
            "    \"marketId\": \"1.XXXXX\",\n" .
            "    \"advice\": \"Runner Name\",\n" .
            "    \"odds\": 1.80,\n" .
            "    \"confidence\": 90,\n" .
            "    \"sentiment\": \"Bullish/Bearish/Neutral\",\n" .
            "    \"motivation\": \"Sintesi tecnica qui.\"\n" .
            "  }\n" .
            "]";

        $data = [
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => [
                "temperature" => 0.1,
                "response_mime_type" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "[]";
    }

    public function analyzeCustom($prompt)
    {
        if (!$this->apiKey) return "Error: Missing Gemini API Key";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $data = [
            "contents" => [["parts" => [["text" => $prompt]]]]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "Error";
    }
}
