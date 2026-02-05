<?php
// backend/gemini.php
require_once __DIR__ . '/config.php';

function analyze_with_gemini($intelligence_json)
{
    $apiKey = GEMINI_API_KEY;
    if (!$apiKey)
        return "Error: Missing Gemini API Key";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

    $prompt = "Sei un analista scommesse PRO. Analizza questi dati live (JSON) e suggerisci una scommessa di valore in ITALIANO.
    
    DATI: " . json_encode($intelligence_json) . "
    
    FORMATO RISPOSTA:
    1. Breve analisi in ITALIANO.
    2. Blocco JSON finale obbligatorio:
    ```json
    {
      \"advice\": \"Consiglio breve\",
      \"market\": \"Mercato\",
      \"odds\": 1.80,
      \"stake\": 2.0,
      \"urgency\": \"Medium\"
    }
    ```";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.3,
            "maxOutputTokens" => 2000
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
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? "Error parsing Gemini response";
}
