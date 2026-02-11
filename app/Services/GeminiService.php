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
                "DATA INPUT:\n" . json_encode($candidates) . "\n\n" .
                "REGOLE:\n" .
                "1. Analizza sport, competizione e volumi.\n" .
                "2. Suggerisci fino a 10 pronostici di valore.\n" .
                "3. Restituisci SOLO un array JSON di oggetti.\n\n" .
                "SCHEMA OGGETTO:\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"event\": \"Team A v Team B\",\n" .
                "  \"sport\": \"Soccer\",\n" .
                "  \"competition\": \"Premier League\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"confidence\": 85,\n" .
                "  \"sentiment\": \"Bullish/Bearish/Neutral\",\n" .
                "  \"motivation\": \"Max 20 parole.\"\n" .
                "}";
        } elseif ($isGiaNik) {
            $dataContext = json_encode([
                'market_data' => $candidates[0],
                'stats_football' => $candidates[0]['api_football'] ?? null,
                'stats_basketball' => $candidates[0]['api_basketball'] ?? null,
                'portfolio' => [
                    'total' => $options['current_portfolio'] ?? 0,
                    'available' => $options['available_balance'] ?? 0
                ]
            ]);

            $prompt = "Sei un TRADER ALGORITMICO ELITE. Analizza i dati JSON forniti ed emetti un ordine di trading immediato.\n\n" .
                "INPUT DATA:\n" . $dataContext . "\n\n" .
                "OBIETTIVO: Identificare valore/rischio basandosi su statistiche live (tiri, possesso, momentum) e volumi.\n\n" .
                "REGOLE DI OUTPUT (FERREE):\n" .
                "1. Restituisci SOLO un oggetto JSON valido (niente testo prima/dopo).\n" .
                "2. 'motivation': Max 15-20 parole. Stile telegrafico.\n" .
                "3. 'odds': Minimo 1.25. Se mercato < 1.25 e vuoi puntare, usa 1.25 (ordine unmatched).\n" .
                "4. 'action': 'bet' per puntare, 'cashout' per coprire (side LAY), 'nothing' se incerto.\n" .
                "5. 'stake': Max 5% disponibile. Sii conservativo.\n\n" .
                "SCHEMA JSON:\n" .
                "{\n" .
                "  \"action\": \"bet|cashout|nothing\",\n" .
                "  \"marketId\": \"1.xxxx\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"side\": \"BACK|LAY\",\n" .
                "  \"odds\": 1.25,\n" .
                "  \"stake\": 2.00,\n" .
                "  \"confidence\": 95,\n" .
                "  \"motivation\": \"Sintesi tecnica.\"\n" .
                "}";
        } else {
            $prompt = "Sei un TRADER ELITE di Betfair. Analizza il mercato live e scova la scommessa migliore.\n\n" .
                "INPUT DATA:\n" . json_encode($candidates) . "\n\n" .
                "REGOLE:\n" .
                "1. Restituisci SOLO un oggetto JSON.\n" .
                "2. 'motivation': Max 20 parole.\n" .
                "3. 'odds': Minimo 1.25.\n" .
                "4. 'stake': 1-5% portfolio.\n\n" .
                "SCHEMA JSON:\n" .
                "{\n" .
                "  \"marketId\": \"1.XXXXX\",\n" .
                "  \"advice\": \"Runner Name\",\n" .
                "  \"odds\": 1.80,\n" .
                "  \"stake\": 2.0,\n" .
                "  \"confidence\": 90,\n" .
                "  \"motivation\": \"Analisi rapida.\"\n" .
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
                "temperature" => 0.1,
                "maxOutputTokens" => 400,
                "responseMimeType" => "application/json"
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
