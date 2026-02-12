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

        // Custom Raw Prompt support
        if (isset($options['raw_prompt'])) {
            $prompt = $options['raw_prompt'];
        } else {
            $balanceText = "";
            if (isset($options['current_portfolio'])) {
                $labelPortfolio = "Budget_Totale_Reale";
                $labelAvailable = "Disponibilità_Liquida_Reale";

                $balanceText = "SITUAZIONE PORTAFOGLIO:\n" .
                    "- $labelPortfolio: " . number_format($options['current_portfolio'], 2) . "€\n" .
                    "- $labelAvailable: " . number_format($options['available_balance'], 2) . "€\n\n";
            }

            $isUpcoming = $options['is_upcoming'] ?? false;
            $isGiaNik = $options['is_gianik'] ?? false;

            $feedbackText = "";
            if (!empty($options['recent_lost_bets'])) {
                $feedbackText = "FEEDBACK ULTIME SCOMMESSE PERSE (Analizza perché hanno fallito per non ripetere l'errore):\n" .
                    json_encode($options['recent_lost_bets']) . "\n\n";
            }

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
                $prompt = "Sei un ANALISTA ELITE e TRADER di Betfair. Il tuo compito è analizzare un EVENTO LIVE con i suoi molteplici mercati e decidere l'operazione migliore.\n\n" .
                    $balanceText .
                    $feedbackText .
                    "DATI EVENTO E MERCATI:\n" . json_encode($candidates[0]) . "\n\n" .
                    "DATI STATISTICI AVANZATI (Se disponibili):\n" .
                    "Calcio: " . (isset($candidates[0]['api_football']) ? json_encode($candidates[0]['api_football']) : "Non disponibili") . "\n" .
                    "Basket: " . (isset($candidates[0]['api_basketball']) ? json_encode($candidates[0]['api_basketball']) : "Non disponibili") . "\n\n" .
                    "🚨 DATI LIVE DEL MATCH:\n" .
                    "Se presenti in api_football.live, troverai:\n" .
                    "- live_score: {home, away, halftime_home, halftime_away} = SCORE ATTUALE E HALFTIME\n" .
                    "- live_status: {short, long, elapsed_minutes} = STATO MATCH E MINUTI TRASCORSI\n" .
                    "- match_info: {fixture_id, date, venue_id}\n\n" .
                    "📈 PRESSURE INDEX & MARKET TREND (FOCUS):\n" .
                    "- api_football.recent_events: Cronologia degli ultimi 15 minuti di gioco. USA QUESTI DATI per capire se una squadra sta premendo o se il match è bloccato.\n" .
                    "- api_football.price_trend: {price, trend, prev}. Il trend indica la direzione delle quote Betfair (-1 giù, 1 su, 0 stabile). Un trend in discesa unito a pressione statistica indica alto valore!\n" .
                    "- market_data.runners.wom: Weight of Money (0-100%). Rappresenta la percentuale di volume sul lato BACK. Se > 70%, c'è forte pressione per far scendere la quota (segnale rialzista per quel runner).\n\n" .
                    "USA QUESTI DATI per contestualizzare la tua analisi! Non dire mai che non conosci lo score o il minuto se questi dati sono presenti.\n\n" .
                    "📊 STATISTICS LIVE DEL MATCH:\n" .
                    "Se presenti in api_football.statistics, troverai array per home/away con:\n" .
                    "- Shots on Goal, Shots off Goal, Total Shots, Blocked Shots\n" .
                    "- Ball Possession (% possesso palla)\n" .
                    "- Corner Kicks, Offsides, Fouls\n" .
                    "- Yellow Cards, Red Cards\n" .
                    "- Total passes, Passes accurate, Passes %\n" .
                    "- Goalkeeper Saves\n" .
                    "USA QUESTE STATISTICHE per valutare il dominio del match, pericolosità, e probabilità di gol!\n\n" .
                    "⚽ EVENTS LIVE DEL MATCH:\n" .
                    "Se presenti in api_football.events, troverai cronologia eventi con:\n" .
                    "- Goal (Normal Goal, Own Goal, Penalty, Missed Penalty) + giocatore + assist + minuto\n" .
                    "- Card (Yellow Card, Red Card) + giocatore + minuto\n" .
                    "- Subst (Substitution 1/2/3) + giocatore IN/OUT + minuto\n" .
                    "- Var (Goal cancelled, Penalty confirmed)\n" .
                    "USA QUESTI EVENTI per capire momentum, espulsioni, cambi tattici, e chi ha segnato!\n\n" .
                    "REGOLE RIGIDE:\n" .
                    "1. Analizza TUTTI i mercati forniti (Match Odds, Double Chance, varie linee di Under/Over, BTTS).\n" .
                    "2. Scegli l'operazione che offre il miglior rapporto rischio/rendimento. Non sei obbligato a scegliere il mercato principale se un altro (es. Over 1.5) è più sicuro o profittevole.\n" .
                    "3. Decidi lo STAKE (in Euro) da puntare. Hai piena libertà di arrivare fino al 5% del 'Budget_Totale' (es. se Budget_Totale=100€, stake max 5€). Attenzione: non superare mai la 'Disponibilità_Liquida' fornita. Scommessa minima 2€, MASSIMA 10€.\n" .
                    "4. Analizza quote Back/Lay, volumi (totalMatched) e DATI STATISTICI LIVE. Cerca mercati liquidi per evitare ordini non abbinati. Per il Basket guarda attentamente a tiri totali, rimbalzi, assist e percentuali dal campo se forniti.\n" .
                    "5. Usa la CLASSIFICA e i PRONOSTICI esterni (predictions) per validare la tua scelta.\n" .
                    "6. Sii molto tecnico nella spiegazione (motivation), correlando stats live, classifica e volumi Betfair.\n" .
                    "7. SOGLIA DI CONFIDENZA: Suggerisci l'operazione SOLO se la tua 'confidence' è pari o superiore all'80%. Se è inferiore, non scommettere sul mercato.\n" .
                    "8. REGOLE CALCIO (MULTI-ENTRY): Se le condizioni cambiano durante il match, puoi rientrare con nuove scommesse. Massimo 4 puntate totali per match: 2 nel Primo Tempo e 2 nel Secondo Tempo. Ogni ingresso deve avere confidence >= 80%.\n" .
                    "9. ⚠️ QUOTA MINIMA E VALORE (REGOLA FERREA): 1.25 è la tua quota MINIMA assoluta di ingresso. Non suggerire MAI quote inferiori a 1.25 nel campo 'odds'. Se la quota attuale del mercato è superiore a 1.25, usala. Se invece la quota attuale è inferiore (es. 1.01 - 1.24) ma la tua 'confidence' è >= 80%, devi OBBLIGATORIAMENTE impostare il campo 'odds' a 1.25 nel JSON. Questo creerà un ordine 'unmatched' che attenderà che il mercato salga a 1.25. Suggerire quote come 1.03 o 1.15 è VIETATO e considerato un errore grave.\n" .
                    "10. ⚠️ NO DATA = NO BET: Se i dati statistici live (api_football.live o api_football.statistics) non sono forniti o sono vuoti, NON devi assolutamente scommettere. In tal caso, imposta 'confidence' a 0 e 'advice' a 'NO_LIVE_DATA'.\n" .
                    "11. ⚠️ QUOTE ALTE LIVE: Sii estremamente sospettoso verso quote > 5.0 in tempo reale. Spesso indicano una situazione compromessa (es. squadra sotto di 2 gol a fine match). Non scommettere su rimonte improbabili a meno che le statistiche di pressione negli ultimi 15m non siano schiaccianti (es. +10 tiri).\n" .
                    "12. Restituisci SEMPRE un blocco JSON con i dettagli come PRIMA COSA nella tua risposta.\n" .
                    "13. STILE RISPOSTA: La 'motivation' deve essere sintetica (max 80 parole). Evita di ripetere dati già chiari.\n\n" .
                    "FORMATO RISPOSTA (JSON OBBLIGATORIO ALL'INIZIO):\n" .
                    "```json\n" .
                    "{\n" .
                    "  \"marketId\": \"1.XXXXX\",\n" .
                    "  \"advice\": \"Runner Name\",\n" .
                    "  \"odds\": 1.80,\n" .
                    "  \"stake\": 5.0,\n" .
                    "  \"confidence\": 90,\n" .
                    "  \"sentiment\": \"Bullish/Bearish/Neutral\",\n" .
                    "  \"motivation\": \"Sintesi tecnica qui.\"\n" .
                    "}\n" .
                    "```\n\n" .
                    "Dopo il JSON, puoi aggiungere un'analisi narrativa più libera se necessario, ma mantienila breve.";
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
                "maxOutputTokens" => 800
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
