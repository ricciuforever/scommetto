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

    public function analyze($matchData)
    {
        if (!$this->apiKey) {
            return "Error: Missing Gemini API Key";
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $prompt = "Sei un analista scommesse PRO. Analizza questi dati live (JSON) e suggerisci una scommessa di valore in ITALIANO.\n\n" .
            "DATI: " . json_encode($matchData) . "\n\n" .
            "FORMATO RISPOSTA:\n" .
            "1. Breve analisi in ITALIANO.\n" .
            "2. Blocco JSON finale obbligatorio:\n" .
            "```json\n" .
            "{\n" .
            "  \"advice\": \"Consiglio breve\",\n" .
            "  \"market\": \"Mercato\",\n" .
            "  \"odds\": 1.80,\n" .
            "  \"stake\": 2.0,\n" .
            "  \"urgency\": \"Medium\"\n" .
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
                "temperature" => 0.2,
                "maxOutputTokens" => 1000
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
