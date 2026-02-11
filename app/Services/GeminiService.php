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
            $prompt = "Sei un ANALISTA ELITE e TRADER di Betfair GiaNik. Il tuo compito è analizzare un EVENTO LIVE e decidere l'operazione migliore con precisione chirurgica.\n\n" .
                $balanceText .
                "DATI EVENTO, MERCATI E STATISTICHE LIVE:\n" . json_encode($candidates[0]) . "\n\n" .
                "CRITERI DI ANALISI (Dati API-Football):\n" .
                "- live_score & elapsed_minutes: Fondamentali per il contesto temporale.\n" .
                "- statistics: Analizza Shots on Goal, Possession, Corners e Goalkeeper Saves per determinare il dominio del match.\n" .
                "- events: Monitora Goals, Cards (soprattutto Red Cards) e Substitutions per capire cambi di momentum.\n" .
                "- active_bets: Se presenti, valuta se la situazione è peggiorata rispetto all'ingresso e suggerisci un Cash-out (azione: cashout, side: LAY) per limitare perdite o proteggere profitti.\n\n" .
                "REGOLE FERREE:\n" .
                "1. QUOTA MINIMA 1.25: Se vuoi puntare ma la quota attuale è < 1.25, DEVI impostare 'odds': 1.25 nel JSON (ordine unmatched). MAI suggerire quote inferiori a 1.25.\n" .
                "2. RISPOSTA SOLO JSON: Non aggiungere commenti, introduzioni o saluti. Restituisci SOLO il blocco JSON.\n" .
                "3. MOTIVAZIONE TECNICA: Il campo 'motivation' deve essere un riassunto tecnico fulmineo (max 2 righe) che colleghi le stats live alla scelta operativa.\n" .
                "4. MULTI-ENTRY: Massimo 4 scommesse totali per match (limite: 2 nel 1° tempo, 2 nel 2° tempo).\n" .
                "5. SOGLIA OPERATIVA: Suggerisci un'azione (bet/cashout) solo se la tua 'confidence' è >= 80%. Altrimenti action: 'nothing'.\n\n" .
                "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n" .
                "```json\n" .
                "{\n" .
                "  \"action\": \"bet | cashout | nothing\",\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"side\": \"BACK | LAY\",\n" .
                "  \"odds\": 1.25,\n" .
                "  \"stake\": 2.0,\n" .
                "  \"confidence\": 95,\n" .
                "  \"motivation\": \"Sintesi tecnica dell'operazione.\"\n" .
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
