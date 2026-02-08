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

    public function analyze($matchData, $balanceInfo = null)
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

        $balanceText = "";
        if ($balanceInfo) {
            $balanceText = "SITUAZIONE PORTAFOGLIO:\n" .
                "- Portfolio Attuale: " . number_format($balanceInfo['current_portfolio'], 2) . "€\n" .
                "- Disponibilità per Nuove Scommesse: " . number_format($balanceInfo['available_balance'], 2) . "€\n" .
                "- Stake Totale in Sospeso: " . number_format($balanceInfo['pending_stakes'], 2) . "€\n\n";
        }

        $prompt = "Sei un analista scommesse PROFESSIONALE (Senior Tipster). Il tuo obiettivo è il profitto a lungo termine.\n\n" .
            $balanceText .
            "SITUAZIONE LIVE:\n" . json_encode($matchData) . "\n\n" .
            "INTELLIGENZA NEL DATABASE (Storico, Classifica, Predictions):\n" . json_encode($dbContent) . "\n\n" .
            "REGOLE RIGIDE DI ANALISI E BANKROLL:\n" .
            "1. USA SOLO LE QUOTE REALI fornite nel campo 'odds_context'. NON INVENTARE MAI LE QUOTE.\n" .
            "2. Se non ci sono quote disponibili o non sono vantaggiose, NON consigliare la scommessa.\n" .
            "3. Analizza se l'andamento LIVE conferma o smentisce i dati storici.\n" .
            "4. Valuta la 'confidence' (fiducia) da 0 a 100.\n" .
            "5. DEVI RISPETTARE IL BUDGET. Non consigliare MAI uno stake superiore alla disponibilità attuale.\n" .
            "6. Stake suggerito normalmente: 1-5% del portfolio. Sii più aggressivo (fino al 10%) SOLO se la confidence è > 85.\n" .
            "7. Se confidence < 60, non consigliare alcuna scommessa.\n\n" .
            "VOCABOLARIO JSON CONTROLLATO (OBBLIGATORIO):\n" .
            "- Market: USA SOLO: '1X2', 'Double Chance', 'Over/Under', 'Both Teams to Score', 'Correct Score'.\n" .
            "- Advice (1X2): '1', 'X', '2'\n" .
            "- Advice (Double Chance): '1X', 'X2', '12'\n" .
            "- Advice (Over/Under): 'Over 0.5 Goals', 'Over 1.5 Goals', 'Over 2.5 Goals', 'Under 2.5 Goals', etc.\n" .
            "- Advice (BTTS): 'Yes' (Goal/Goal), 'No' (No Goal)\n" .
            "- Advice (Correct Score): '1-0', '2-1', etc.\n" .
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
