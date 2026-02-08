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

    public function analyze($candidates, $balanceInfo = null)
    {
        if (!$this->apiKey) return "Error: Missing Gemini API Key";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $balanceText = "";
        if ($balanceInfo) {
            $balanceText = "SITUAZIONE PORTAFOGLIO:\n" .
                "- Portfolio Reale: " . number_format($balanceInfo['current_portfolio'], 2) . "€\n" .
                "- Disponibilità: " . number_format($balanceInfo['available_balance'], 2) . "€\n\n";
        }

        $prompt = "Sei un TRADER ELITE di Betfair. Il tuo compito è analizzare il mercato live multi-sport e scovare la scommessa migliore tra quelle fornite.\n\n" .
            $balanceText .
            "LISTA EVENTI LIVE CANDIDATI:\n" . json_encode($candidates) . "\n\n" .
            "REGOLE RIGIDE:\n" .
            "1. Analizza i volumi (totalMatched) e le quote Back/Lay.\n" .
            "2. SCEGLI SOLO 1 EVENTO dalla lista che ritieni più profittevole.\n" .
            "3. Se nessun evento è convincente (risk/reward scarso), non scegliere nulla.\n" .
            "4. NON INVENTARE QUOTE: usa solo quelle presenti nel JSON per il runner scelto.\n" .
            "5. Stake: 1-5% del portfolio.\n\n" .
            "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n" .
            "Breve analisi tecnica e poi il JSON:\n" .
            "```json\n" .
            "{\n" .
            "  \"marketId\": \"1.XXXXX\",\n" .
            "  \"advice\": \"Runner Name\",\n" .
            "  \"odds\": 1.80,\n" .
            "  \"stake\": 2.0,\n" .
            "  \"confidence\": 90\n" .
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
