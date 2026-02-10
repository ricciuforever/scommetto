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
            $prompt = "Sei un ANALISTA ELITE e TRADER di Betfair. Il tuo compito è analizzare un EVENTO LIVE e decidere l'operazione migliore con precisione INFALLIBILE.\n\n" .
                $balanceText .
                "DATI EVENTO E MERCATI:\n" . json_encode($candidates[0]) . "\n\n" .
                "🚨 DATI LIVE E STATISTICHE:\n" .
                (isset($candidates[0]['api_football']) ? "Calcio (Stats & Events): " . json_encode($candidates[0]['api_football']) : "Dati live limitati.") . "\n\n" .
                "REGOLE RIGIDE PER IL SUCCESSO:\n" .
                "1. PRECISIONE ESTREMA: Scommetti SOLO se sei certo del risultato basandoti sui dati. Se hai dubbi o i dati sono insufficienti, non scommettere.\n" .
                "2. CONCISIONE: La tua 'motivation' deve essere di pochissime righe (max 3-4). Vai dritto al punto: verdetto e giocata. Non descrivere ciò che è ovvio.\n" .
                "3. ANALISI CROSS-MARKET: Analizza Match Odds, Double Chance, Under/Over, BTTS. Scegli il mercato con il miglior rapporto rischio/rendimento.\n" .
                "4. CONFIDENZA: Richiesta confidence >= 90% per operare. Sotto il 90%, non generare una scommessa.\n" .
                "5. LIMITI: Massimo 4 puntate per match (2 nel 1° Tempo, 2 nel 2° Tempo). Non sprecare ingressi.\n" .
                "6. STAKE: Fino al 5% del budget disponibile (minimo 2€).\n" .
                "7. QUOTA MINIMA 1.25: Se la quota attuale è < 1.25, scrivi '1.25' nel JSON (ordine limite).\n" .
                "8. Restituisci SOLO il blocco JSON.\n\n" .
                "FORMATO RISPOSTA:\n" .
                "```json\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"stake\": 5.0,\n" .
                "  \"confidence\": 95,\n" .
                "  \"sentiment\": \"Testo brevissimo\",\n" .
                "  \"motivation\": \"Verdetto tecnico conciso.\"\n" .
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
                "6. QUOTA MINIMA: 1.25 è la quota minima di ingresso. Se la quota attuale è superiore, usala. Se è inferiore ma l'evento è eccezionale, scrivi '1.25' nel campo 'odds' per piazzare un ordine limite.\n" .
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
