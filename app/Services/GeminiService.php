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

    public function analyze($matchData, $externalPredictions = null)
    {
        if (!$this->apiKey) {
            return "Error: Missing Gemini API Key";
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        // Arricchimento del contesto con i dati di API-Football Predictions
        $predictionContext = "";
        if ($externalPredictions) {
            $predictionContext = "DATI PRE-MATCH E ALGORITMICI:\n" .
                "- Consiglio Algoritmo: " . ($externalPredictions['advice'] ?? 'N/A') . "\n" .
                "- Probabilità: " . json_encode($externalPredictions['percent'] ?? []) . "\n" .
                "- Confronto Statistico: " . json_encode($externalPredictions['comparison'] ?? []) . "\n\n";
        }

        $prompt = "Sei un analista scommesse PROFESSIONALE (Senior Tipster). Il tuo obiettivo è il profitto a lungo termine, non la quantità di giocate.\n\n" .
            "CONTESTO LIVE:\n" . json_encode($matchData) . "\n\n" .
            $predictionContext .
            "REGOLE FERREE:\n" .
            "1. Valuta la 'confidence' (fiducia) da 0 a 100 basandoti sulla coerenza tra dati live e predizioni algoritmiche.\n" .
            "2. Se la fiducia è inferiore a 75, NON CONSIGLIARE la scommessa. Rispondi che il rischio è troppo alto.\n" .
            "3. Se consigli una giocata, deve avere un valore (EV+) basato sul momento del match.\n\n" .
            "FORMATO RISPOSTA (Markdown):\n" .
            "1. Analisi tecnica dettagliata in ITALIANO (Max 3 paragrafi).\n" .
            "2. Spiegazione del perché la fiducia è alta/bassa.\n" .
            "3. Se (e solo se) confidence >= 75, aggiungi questo blocco JSON:\n" .
            "```json\n" .
            "{\n" .
            "  \"advice\": \"IL TUO CONSIGLIO\",\n" .
            "  \"market\": \"IL MERCATO\",\n" .
            "  \"odds\": 1.80,\n" .
            "  \"stake\": 3.0,\n" .
            "  \"urgency\": \"High\",\n" .
            "  \"confidence\": 85\n" .
            "}\n" .
            "```\n\n" .
            "Se la fiducia è < 75, NON includere il blocco JSON o scrivi solo un'analisi di cautela.";

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
