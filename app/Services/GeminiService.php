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

    public function analyze($betfairEvent, $balanceInfo = null)
    {
        if (!$this->apiKey) return "Error: Missing Gemini API Key";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $balanceText = "";
        if ($balanceInfo) {
            $balanceText = "SITUAZIONE PORTAFOGLIO:\n" .
                "- Portfolio Reale (Saldo+Esposizione): " . number_format($balanceInfo['current_portfolio'], 2) . "€\n" .
                "- Disponibilità Immediata: " . number_format($balanceInfo['available_balance'], 2) . "€\n\n";
        }

        $prompt = "Sei un TRADER PROFESSIONISTA DI SCOMMESSE LIVE. Il tuo obiettivo è il profitto tramite l'analisi di volumi e quote Betfair.\n\n" .
            $balanceText .
            "DATI EVENTO LIVE BETFAIR (Sport: " . ($betfairEvent['sport'] ?? 'Unknown') . "):\n" . json_encode($betfairEvent) . "\n\n" .
            "REGOLE DI ANALISI:\n" .
            "1. Analizza i volumi abbinati (totalMatched) e la discrepanza tra Back e Lay.\n" .
            "2. USA SOLO I RUNNER E LE QUOTE FORNITI NEI PREZZI BETFAIR. NON INVENTARE NULLA.\n" .
            "3. Valuta la 'confidence' (0-100) basandoti sull'andamento del mercato.\n" .
            "4. Bankroll: Stake suggerito 1-3% della disponibilità.\n" .
            "5. Se l'evento è illiquido (volumi bassi) o rischioso, NON scommettere (confidence < 70).\n\n" .
            "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n" .
            "Analisi rapida in ITALIANO focalizzata sui mercati Betfair.\n" .
            "```json\n" .
            "{\n" .
            "  \"advice\": \"Runner Name\",\n" .
            "  \"odds\": 1.80,\n" .
            "  \"stake\": 2.0,\n" .
            "  \"confidence\": 85\n" .
            "}\n" .
            "```";

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
            return "Gemini API Error: " . ($result['error']['message'] ?? 'Unknown Error');
        }

        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "Error: Nessuna risposta valida dall'AI.";
    }
}
