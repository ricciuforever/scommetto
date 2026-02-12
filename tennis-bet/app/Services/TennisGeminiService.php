<?php
// tennis-bet/app/Services/TennisGeminiService.php

namespace TennisApp\Services;

use TennisApp\Config\TennisConfig;

class TennisGeminiService
{
    private $apiKey;

    public function __construct()
    {
        TennisConfig::init();
        $this->apiKey = TennisConfig::get('GEMINI_API_KEY');
    }

    public function analyzeMatch($matchData, $historicalStats = [], $portfolio = [])
    {
        if (!$this->apiKey)
            return ["error" => "Missing Gemini API Key"];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $prompt = "Sei un ANALISTA ESPERTO di Betting sul Tennis. Il tuo compito è analizzare il match fornito e decidere la scommessa migliore (Virtuale).\n\n";

        if (!empty($portfolio)) {
            $prompt .= "SITUAZIONE PORTAFOGLIO VIRTUALE:\n";
            $prompt .= "- Budget: " . number_format($portfolio['balance'], 2) . " EUR\n\n";
        }

        $prompt .= "DATI MATCH ATTUALE:\n" . json_encode($matchData) . "\n\n";

        if (!empty($historicalStats)) {
            $prompt .= "DATI STORICI (Jeff Sackmann): \n" . json_encode($historicalStats) . "\n\n";
        }

        $prompt .= "REGOLE:\n";
        $prompt .= "1. Analizza i giocatori: forma recente, superficie (surface), testa a testa (H2H).\n";
        $prompt .= "2. Valuta le quote se fornite.\n";
        $prompt .= "3. Scegli se scommettere (Virtuale) o passare (NO_BET).\n";
        $prompt .= "4. SOGLIA CONFIDENZA: Solo se >= 75%.\n";
        $prompt .= "5. Stake: Solitamente 1-2% del budget.\n\n";

        $prompt .= "FORMATO RISPOSTA (JSON OBBLIGATORIO):\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= "  \"event\": \"Player A vs Player B\",\n";
        $prompt .= "  \"marketId\": \"1.XXXX\",\n";
        $prompt .= "  \"advice\": \"Vincitore Match / Set Betting / Over-Under\",\n";
        $prompt .= "  \"selection\": \"Nome Giocatore o Esito\",\n";
        $prompt .= "  \"odds\": 1.80,\n";
        $prompt .= "  \"confidence\": 85,\n";
        $prompt .= "  \"stake\": 10.00,\n";
        $prompt .= "  \"motivation\": \"Spiegazione tecnica breve.\"\n";
        $prompt .= "}\n";
        $prompt .= "```";

        $data = [
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => ["temperature" => 0.2, "maxOutputTokens" => 800]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? "";

        // Extract JSON from response
        if (preg_match('/```json\s+(.*?)\s+```/s', $text, $matches)) {
            return json_decode($matches[1], true);
        }

        return json_decode($text, true) ?: ["error" => "Navi AI analysis failed", "raw" => $text];
    }
}
