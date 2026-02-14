<?php
// app/Dio/Controllers/BrainController.php

namespace App\Dio\Controllers;

use App\Services\GeminiService;
use App\Dio\DioDatabase;
use PDO;

class BrainController
{
    private $gemini;
    private $db;

    public function __construct()
    {
        $this->gemini = new GeminiService();
        $this->db = DioDatabase::getInstance()->getConnection();
    }

    /**
     * Scans for settled bets that don't have a lesson yet and generates one.
     */
    public function learn()
    {
        $this->sendJsonHeader();

        // Find settled bets without an experience entry
        $stmt = $this->db->prepare("
            SELECT b.* FROM bets b
            LEFT JOIN experiences e ON b.id = e.data_context -- Using id as link for now
            WHERE b.status IN ('won', 'lost') AND e.id IS NULL
            LIMIT 5
        ");
        $stmt->execute();
        $newSettled = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($newSettled)) {
            return json_encode(['status' => 'success', 'message' => 'No new lessons to learn.']);
        }

        $learned = 0;
        foreach ($newSettled as $bet) {
            $lesson = $this->extractLesson($bet);
            if ($lesson) {
                $stmt = $this->db->prepare("INSERT INTO experiences (sport, market_type, outcome, lesson, data_context) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $bet['sport'],
                    'MATCH_ODDS', // assuming for now, could be dynamic
                    $bet['status'],
                    $lesson,
                    (string) $bet['id']
                ]);
                $learned++;
            }
        }

        echo json_encode(['status' => 'success', 'lessons_learned' => $learned]);
    }

    private function sendJsonHeader()
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
    }

    private function extractLesson($bet)
    {
        $prompt = "Sei il 'Cervello' analitico di Dio, un AI Quant Trader. Analizza questa operazione conclusa e scrivi una BREVISSIMA lezione tecnica (massimo 20 parole) per il futuro.\n\n" .
            "DETTAGLI OPERAZIONE:\n" .
            "- Sport: {$bet['sport']}\n" .
            "- Evento: {$bet['event_name']}\n" .
            "- Mercato: {$bet['market_name']}\n" .
            "- Scelta: {$bet['runner_name']} (Quota: {$bet['odds']})\n" .
            "- Motivazione Originale: {$bet['motivation']}\n" .
            "- ESITO: " . strtoupper($bet['status']) . " (Profitto: {$bet['profit']}€)\n\n" .
            "OBIETTIVO: Se hai vinto, identifica perché l'analisi era corretta. Se hai perso, identifica l'errore tecnico (spread, momentum o volume).\n" .
            "RISPONDI SOLO CON LA LEZIONE TECNICA.";

        return $this->gemini->analyzeCustom($prompt);
    }
}
