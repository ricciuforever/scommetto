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
                "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n" .
                "```json\n" .
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
                "]\n" .
                "```";
        } elseif ($isGiaNik) {
            $prompt = "Sei un ANALISTA ELITE e TRADER di Betfair. Il tuo compito è analizzare un EVENTO LIVE con i suoi molteplici mercati e decidere l'operazione migliore.\n\n" .
                $balanceText .
                "DATI EVENTO E MERCATI:\n" . json_encode($candidates[0]) . "\n\n" .
                "DATI STATISTICI AVANZATI (Se disponibili):\n" .
                "Calcio: " . (isset($candidates[0]['api_football']) ? json_encode($candidates[0]['api_football']) : "Non disponibili") . "\n" .
                "Basket: " . (isset($candidates[0]['api_basketball']) ? json_encode($candidates[0]['api_basketball']) : "Non disponibili") . "\n\n" .
                "REGOLE RIGIDE:\n" .
                "1. Analizza TUTTI i mercati forniti (Match Odds, Double Chance, varie linee di Under/Over, BTTS).\n" .
                "2. Scegli l'operazione che offre il miglior rapporto rischio/rendimento. Non sei obbligato a scegliere il mercato principale se un altro (es. Over 1.5) è più sicuro o profittevole.\n" .
                "3. Decidi lo STAKE (in Euro) da puntare. Hai piena libertà di arrivare fino al 5% del Budget Disponibile Virtuale (minimo 2€).\n" .
                "4. Analizza quote Back/Lay, volumi e DATI STATISTICI LIVE. Per il Basket guarda attentamente a tiri totali, rimbalzi, assist e percentuali dal campo se forniti.\n" .
                "5. Usa la CLASSIFICA e i PRONOSTICI esterni (predictions) per validare la tua scelta.\n" .
                "6. Sii molto tecnico nella spiegazione (motivation), correlando stats live, classifica e volumi Betfair.\n" .
                "7. SOGLIA DI CONFIDENZA: Suggerisci l'operazione SOLO se la tua 'confidence' è pari o superiore all'80%. Se è inferiore, non scommettere sul mercato.\n" .
                "8. REGOLE CALCIO (MULTI-ENTRY): Se le condizioni cambiano durante il match, puoi rientrare con nuove scommesse. Massimo 4 puntate totali per match: 2 nel Primo Tempo e 2 nel Secondo Tempo. Ogni ingresso deve avere confidence >= 80%.\n" .
                "9. QUOTA MINIMA E VALORE: Non accettare mai quote inferiori a 1.25. Se un evento ha confidenza >= 80% ma la quota attuale è più bassa (es. 1.10 - 1.20), puoi comunque suggerire di puntare a 1.25 (Puntata a Quota Fissa). In tal caso, imposta 'odds' a 1.25 nel JSON. L'ordine rimarrà in attesa sul mercato.\n" .
                "10. Restituisci SEMPRE un blocco JSON con i dettagli.\n\n" .
                "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n" .
                "```json\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"stake\": 5.0,\n" .
                "  \"confidence\": 90,\n" .
                "  \"sentiment\": \"Bullish/Bearish/Neutral\",\n" .
                "  \"motivation\": \"Spiegazione tecnica dettagliata. Collega i dati statistici live con la scelta del mercato e dello stake.\"\n" .
                "}\n" .
                "```";
        } else {
            $prompt = "Sei un TRADER ELITE di Betfair. Il tuo compito è analizzare il mercato live multi-sport e scovare la scommessa migliore tra quelle fornite.\n\n" .
                $balanceText .
                "LISTA EVENTI LIVE CANDIDATI:\n" . json_encode($candidates) . "\n\n" .
                "REGOLE RIGIDE:\n" .
                "1. Analizza i volumi (totalMatched) e le quote Back/Lay.\n" .
                "2. SCEGLI SOLO 1 EVENTO dalla lista che ritieni più profittevole.\n" .
                "3. Se nessun evento è convincente (risk/reward scarso), non scegliere nulla.\n" .
                "4. NON INVENTARE QUOTE: usa solo quelle presenti nel JSON per il runner scelto.\n" .
                "5. Stake: 1-5% del portfolio.\n" .
                "6. QUOTA MINIMA: Ignora quote inferiori a 1.25. Se un evento è eccezionale ma la quota è bassa, puoi puntare a 1.25 (Puntata a Quota Fissa) forzando il campo 'odds' a 1.25.\n" .
                "7. Se per uno sport non hai dati statistici (ma solo quote), sii più prudente e cerca solo 'Value Bets' evidenti.\n\n" .
                "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n" .
                "```json\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"stake\": 2.0,\n" .
                "  \"confidence\": 90,\n" .
                "  \"sentiment\": \"Testo breve sul sentiment del mercato\",\n" .
                "  \"motivation\": \"Spiegazione tecnica dettagliata (perché questo evento e questo runner?)\"\n" .
                "}\n" .
                "```";
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
                "maxOutputTokens" => 1200
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
}
