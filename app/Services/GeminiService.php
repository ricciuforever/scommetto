<?php
// app/Services/GeminiService.php

namespace App\Services;

use App\Config\Config;

class GeminiService
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = Config::get('GEMINI_API_KEY');
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

        if ($isUpcoming) {
            $prompt = "Sei un ANALISTA ELITE di Betfair. Il tuo compito è analizzare gli eventi FUTURI forniti e suggerire i migliori pronostici (max 10).\n\n" .
                "LISTA EVENTI CANDIDATI:\n" . json_encode($candidates) . "\n\n" .
                "REGOLE:\n" .
                "1. Analizza sport, competizione e volumi.\n" .
                "2. Suggerisci fino a 10 pronostici interessanti.\n" .
                "3. Per ogni pronostico specifica l'advice, la motivazione tecnica (motivation) e il sentiment globale del mercato.\n\n" .
                "RISPONDI ESCLUSIVAMENTE IN FORMATO JSON (ARRAY DI OGGETTI):\n" .
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
            $prompt = "Sei un ANALISTA ELITE e TRADER di Betfair. Il tuo compito è analizzare un EVENTO LIVE con i suoi molteplici mercati e decidere l'operazione migliore.\n\n" .
                $balanceText .
                "DATI EVENTO E MERCATI:\n" . json_encode($candidates[0]) . "\n\n" .
                "DATI STATISTICI AVANZATI (Se disponibili):\n" .
                "Calcio: " . (isset($candidates[0]['api_football']) ? json_encode($candidates[0]['api_football']) : "Non disponibili") . "\n\n" .
                "📚 CONTESTO STORICO E PRE-MATCH (Intelligence):\n" .
                (isset($candidates[0]['deep_context']) ? $candidates[0]['deep_context'] : "Non disponibile") . "\n\n" .
                "📈 PERFORMANCE STORICHE AI (Metriche):\n" .
                (isset($candidates[0]['performance_metrics']) ? $candidates[0]['performance_metrics'] : "Nessuna metrica disponibile") . "\n\n" .
                "📚 LEZIONI IMPARATE (Post-Mortem):\n" .
                (isset($candidates[0]['ai_lessons']) ? $candidates[0]['ai_lessons'] : "Nessuna lezione pertinente") . "\n\n" .
                "🚨 DATI LIVE DEL MATCH:\n" .
                "Se presenti in api_football.live, troverai:\n" .
                "- live_score: {home, away, halftime_home, halftime_away} = SCORE ATTUALE E HALFTIME\n" .
                "- live_status: {short, long, elapsed_minutes} = STATO MATCH E MINUTI TRASCORSI\n" .
                "- match_info: {fixture_id, date, venue_id}\n" .
                "USA QUESTI DATI per contestualizzare la tua analisi! Non dire mai che non conosci lo score o il minuto se questi dati sono presenti.\n\n" .
                "📊 STATISTICS LIVE DEL MATCH:\n" .
                "Se presenti in api_football.statistics, troverai array per home/away con:\n" .
                "- Shots on Goal, Shots off Goal, Total Shots, Blocked Shots\n" .
                "- Ball Possession (% possesso palla)\n" .
                "- Corner Kicks, Offsides, Fouls\n" .
                "- Yellow Cards, Red Cards\n" .
                "- Total passes, Passes accurate, Passes %\n" .
                "- Goalkeeper Saves\n" .
                "USA QUESTE STATISTICHE per valutare il dominio del match, pericolosità, e probabilità di gol!\n\n" .
                "⚡ MOMENTUM (Variazione ultimi 10-15 minuti):\n" .
                (isset($candidates[0]['api_football']['momentum']) ? $candidates[0]['api_football']['momentum'] : "Dati momentum non ancora disponibili.") . "\n\n" .
                "⚽ EVENTS LIVE DEL MATCH:\n" .
                "Se presenti in api_football.events, troverai cronologia eventi con:\n" .
                "- Goal (Normal Goal, Own Goal, Penalty, Missed Penalty) + giocatore + assist + minuto\n" .
                "- Card (Yellow Card, Red Card) + giocatore + minuto\n" .
                "- Subst (Substitution 1/2/3) + giocatore IN/OUT + minuto\n" .
                "- Var (Goal cancelled, Penalty confirmed)\n" .
                "USA QUESTI EVENTI per capire momentum, espulsioni, cambi tattici, e chi ha segnato!\n\n" .
                "REGOLE RIGIDE:\n" .
                "1. Analizza TUTTI i mercati forniti (Match Odds, Double Chance, varie linee di Under/Over, BTTS).\n" .
                "2. Scegli l'operazione che offre il miglior rapporto rischio/rendimento. Non sei obbligato a scegliere il mercato principale se un altro (es. Over 1.5) è più sicuro o profittevole.\n" .
                "3. Analizza quote Back/Lay, volumi e DATI STATISTICI LIVE.\n" .
                "4. Usa la CLASSIFICA e i PRONOSTICI esterni (predictions) per validare la tua scelta.\n" .
                "5. Sii molto tecnico nella spiegazione (motivation), correlando stats live, classifica e volumi Betfair.\n" .
                "6. SOGLIA DI CONFIDENZA: La tua 'confidence' (0-100) deve rispecchiare la PROBABILITÀ REALE che l'evento si verifichi. Non gonfiare i numeri.\n" .
                "   Se la quota è 1.50 (probabilità implicita 66%) e tu stimi una probabilità del 60%, la tua confidence deve essere 60.\n" .
                "7. REGOLA CALCIO (SINGOLA GIOCATA CONTEMPORANEA): È permessa solo UNA scommessa attiva alla volta per match.\n" .
                "8. ⚠️ QUOTA MINIMA: 1.25 è la tua quota MINIMA di ingresso. Se la quota attuale è inferiore ma l'evento è valido, imposta 'odds' a 1.25 nel JSON.\n" .
                "9. RISPONDI ESCLUSIVAMENTE IN FORMATO JSON.\n" .
                "10. STILE RISPOSTA: La 'motivation' deve essere sintetica (max 80 parole). Evita di ripetere dati già chiari.\n\n" .
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
            $prompt = "Sei un TRADER ELITE di Betfair. Il tuo compito è analizzare il mercato live multi-sport e scovare la scommessa migliore tra quelle fornite.\n\n" .
                $balanceText .
                "LISTA EVENTI LIVE CANDIDATI:\n" . json_encode($candidates) . "\n\n" .
                "REGOLE RIGIDE:\n" .
                "1. Analizza i volumi (totalMatched) e le quote Back/Lay.\n" .
                "2. SCEGLI SOLO 1 EVENTO dalla lista che ritieni più profittevole.\n" .
                "3. Se nessun evento è convincente (risk/reward scarso), non scegliere nulla.\n" .
                "4. NON INVENTARE QUOTE: usa solo quelle presenti nel JSON per il runner scelto.\n" .
                "5. CONFIDENCE: La tua 'confidence' (0-100) deve rispecchiare la PROBABILITÀ REALE. Sii brutale e onesto. Se non c'è valore rispetto alla quota, scrivi una confidence bassa.\n" .
                "6. QUOTA MINIMA: 1.25 è la quota minima di ingresso.\n" .
                "7. Se per uno sport non hai dati statistici (ma solo quote), sii più prudente.\n\n" .
                "RISPONDI ESCLUSIVAMENTE IN FORMATO JSON:\n" .
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

        $balanceText = "";
        if (isset($options['current_portfolio'])) {
            $labelPortfolio = ($options['is_gianik'] ?? false) ? "Budget Totale Virtuale GiaNik" : "Portfolio Reale";
            $labelAvailable = ($options['is_gianik'] ?? false) ? "Disponibilità Virtuale GiaNik" : "Disponibilità";

            $balanceText = "SITUAZIONE PORTAFOGLIO:\n" .
                "- $labelPortfolio: " . number_format($options['current_portfolio'], 2) . "€\n" .
                "- $labelAvailable: " . number_format($options['available_balance'], 2) . "€\n\n";
        }

        $prompt = "Sei un ANALISTA ELITE e TRADER di Betfair Exchange. Il tuo compito è analizzare un BATCH di EVENTI LIVE (Calcio) e decidere le operazioni migliori.\n\n" .
            $balanceText .
            "LISTA EVENTI DA ANALIZZARE:\n" . json_encode($events, JSON_PRETTY_PRINT) . "\n\n" .
            "REGOLE RIGIDE:\n" .
            "1. Per ogni evento, analizza i mercati, i volumi, i dati statistici live, il momentum e il contesto storico.\n" .
            "2. Scegli l'operazione migliore per ogni evento (o nessuna se il rischio/rendimento è scarso).\n" .
            "3. CONFIDENCE: La tua 'confidence' (0-100) deve rispecchiare la PROBABILITÀ REALE. Sii onesto: se la quota è 1.50 (66% imp) e tu stimi il 60%, scrivi confidence 60.\n" .
            "4. Quota Minima: 1.25. Se la quota attuale è inferiore ma l'evento è valido, scrivi '1.25' per piazzare un ordine limite.\n" .
            "5. Rispondi con un ARRAY JSON di oggetti, uno per ogni evento che ritieni meritevole di analisi.\n\n" .
            "RISPONDI ESCLUSIVAMENTE CON QUESTO SCHEMA JSON (ARRAY):\n" .
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
