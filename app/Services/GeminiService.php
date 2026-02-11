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
            $prompt = "Sei un ANALISTA ELITE di Betfair. Analizza gli eventi FUTURI forniti e suggerisci i migliori pronostici (max 10).\n\n" .
                "LISTA EVENTI CANDIDATI:\n" . json_encode($candidates) . "\n\n" .
                "REGOLE:\n" .
                "1. Analizza sport, competizione e volumi.\n" .
                "2. Suggerisci max 10 pronostici di valore.\n" .
                "3. STILE: Motivazione ultra-sintetica e tecnica.\n\n" .
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
                "    \"motivation\": \"Sintesi tecnica (max 30 parole).\"\n" .
                "  }\n" .
                "]\n" .
                "```";
        } elseif ($isGiaNik) {
            $prompt = "Sei un ANALISTA ELITE e TRADER di Betfair. Analizza l'EVENTO LIVE e decidi l'operazione migliore (Puntata, Cash-out o Hedging).\n\n" .
                $balanceText .
                "DATI EVENTO E MERCATI:\n" . json_encode($candidates[0]) . "\n\n" .
                "POSIZIONI APERTE (Se presenti):\n" . (isset($options['open_bets']) ? json_encode($options['open_bets']) : "Nessuna") . "\n\n" .
                "STATS LIVE:\n" .
                "Calcio: " . (isset($candidates[0]['api_football']) ? json_encode($candidates[0]['api_football']) : "Non disponibili") . "\n" .
                "Basket: " . (isset($candidates[0]['api_basketball']) ? json_encode($candidates[0]['api_basketball']) : "Non disponibili") . "\n\n" .
                "REGOLE RIGIDE:\n" .
                "1. Opzioni: BACK (Punta) per nuove occasioni, LAY (Banca) per Cash-out/Hedging se una posizione aperta è a rischio o per bloccare profitto.\n" .
                "2. Scegli l'operazione con miglior rischio/rendimento.\n" .
                "3. Stake: max 5% del Budget Disponibile. Per il Cash-out, calcola lo stake necessario per bilanciare.\n" .
                "4. SOGLIA CONFIDENZA: Solo se >= 80%.\n" .
                "5. QUOTA MINIMA: 1.25 per BACK. Nessun limite per LAY in caso di hedging.\n" .
                "6. MULTI-ENTRY (Calcio): Max 4 totali (2 per tempo).\n" .
                "7. STILE: Risposta ultra-concisa. Motivazione tecnica e telegrafica.\n\n" .
                "FORMATO RISPOSTA (JSON OBBLIGATORIO ALL'INIZIO):\n" .
                "```json\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"side\": \"BACK/LAY\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"stake\": 5.0,\n" .
                "  \"confidence\": 90,\n" .
                "  \"sentiment\": \"Bullish/Bearish/Neutral\",\n" .
                "  \"motivation\": \"Sintesi tecnica (max 40 parole, stile telegrafico).\"\n" .
                "}\n" .
                "```\n\n" .
                "Dopo il JSON, aggiungi solo un commento di massimo 2 righe se necessario.";
        } else {
            $prompt = "Sei un TRADER ELITE di Betfair. Analizza il mercato live e scova la scommessa migliore tra quelle fornite.\n\n" .
                $balanceText .
                "LISTA EVENTI LIVE CANDIDATI:\n" . json_encode($candidates) . "\n\n" .
                "REGOLE RIGIDE:\n" .
                "1. Analizza volumi (totalMatched) e quote Back/Lay.\n" .
                "2. SCEGLI SOLO 1 EVENTO più profittevole. Se nulla convince, non scegliere nulla.\n" .
                "3. Stake: 1-5% del portfolio.\n" .
                "4. QUOTA MINIMA: 1.25.\n" .
                "5. STILE: Motivazione telegrafica, max 40 parole.\n\n" .
                "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n" .
                "```json\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"stake\": 2.0,\n" .
                "  \"confidence\": 90,\n" .
                "  \"sentiment\": \"Sentiment breve\",\n" .
                "  \"motivation\": \"Sintesi tecnica telegrafica.\"\n" .
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
                "maxOutputTokens" => 800
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
