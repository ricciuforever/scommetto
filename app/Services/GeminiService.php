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

        $fid = $matchData['fixture']['id'];
        $home_id = $matchData['teams']['home']['id'];
        $away_id = $matchData['teams']['away']['id'];
        $league_id = $matchData['league']['id'];

        // Recupero Intelligenza dal DB Locale
        $intelService = new \App\Services\IntelligenceService();
        $dbContent = $intelService->getDeepContext($fid, $home_id, $away_id, $league_id);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $this->apiKey;

        $prompt = "Sei un analista scommesse PROFESSIONALE (Senior Tipster). Il tuo obiettivo è il profitto a lungo termine.\n\n" .
            "SITUAZIONE LIVE:\n" . json_encode($matchData) . "\n\n" .
            "INTELLIGENZA NEL DATABASE (Storico, Classifica, Predictions):\n" . json_encode($dbContent) . "\n\n" .
            "REGOLE AGGIORNATE (CARTA BIANCA):\n" .
            "1. Analizza se l'andamento LIVE conferma o smentisce i dati storici.\n" .
            "2. Valuta la 'confidence' (fiducia) da 0 a 100.\n" .
            "3. HAI CARTA BIANCA. Il portafoglio è pieno. Rimuovi limiti conservativi. Sii aggressivo se c'è valore.\n" .
            "4. Se confidence >= 60 (o c'è valore statistico), rispondi con un'analisi e il blocco JSON.\n\n" .
            "VOCABOLARIO JSON CONTROLLATO (OBBLIGATORIO):\n" .
            "- Market: USA SOLO: '1X2', 'Double Chance', 'Over/Under', 'Both Teams to Score', 'Correct Score'.\n" .
            "- Advice (1X2): '1', 'X', '2'\n" .
            "- Advice (Double Chance): '1X', 'X2', '12'\n" .
            "- Advice (Over/Under): 'Over 0.5 Goals', 'Over 1.5 Goals', 'Over 2.5 Goals', 'Under 2.5 Goals', etc.\n" .
            "- Advice (BTTS): 'Yes' (Goal/Goal), 'No' (No Goal)\n" .
            "- Advice (Correct Score): '1-0', '2-1', etc.\n" .
            "NON USARE TERMINI COME 'Vittoria Casa', 'Pareggio', 'Segna Gol', 'Next Goalscorer'. Usa SOLO lo standard internazionale.\n\n" .
            "FORMATO RISPOSTA:\n" .
            "Analisi tecnica in ITALIANO.\n" .
            "```json\n" .
            "{\n" .
            "  \"advice\": \"Over 2.5 Goals\",\n" .
            "  \"market\": \"Over/Under\",\n" .
            "  \"odds\": 1.80,\n" .
            "  \"stake\": 3.0,\n" .
            "  \"urgency\": \"High\",\n" .
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
