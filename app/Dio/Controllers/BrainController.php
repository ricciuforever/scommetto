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

        // Throttling: Solo un ciclo di apprendimento ogni 10 minuti per Dio
        $cooldownFile = \App\Config\Config::DATA_PATH . 'dio_brain_cooldown.txt';
        $lastRun = file_exists($cooldownFile) ? (int) file_get_contents($cooldownFile) : 0;
        if (time() - $lastRun < 600) {
            return json_encode(['status' => 'success', 'message' => 'Dio Brain in cooldown']);
        }
        file_put_contents($cooldownFile, time());

        // Find settled bets without an experience entry
        // Prioritizziamo le PERDITE per imparare dai fallimenti
        $stmt = $this->db->prepare("
            SELECT b.* FROM bets b
            LEFT JOIN experiences e ON b.id = e.data_context
            WHERE b.status IN ('won', 'lost') AND e.id IS NULL
            ORDER BY CASE WHEN b.status = 'lost' THEN 0 ELSE 1 END ASC, b.created_at DESC
            LIMIT 3
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
                    'MATCH_ODDS',
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
        // Pulisci i riferimenti al CSV per evitare allucinazioni dell'AI sulla fonte dei dati
        $runnerName = ($bet['runner_name'] === 'Runner CSV') ? 'Selezione' : $bet['runner_name'];
        $motivation = (strpos($bet['motivation'], 'Importato da') !== false) ? 'Analisi tecnica post-match.' : $bet['motivation'];

        $prompt = "Sei il 'Cervello' analitico di Dio, un AI Quant Trader. Analizza questa operazione conclusa e scrivi una BREVISSIMA lezione tecnica (massimo 20 parole) per il futuro. Usa un linguaggio chiaro, tecnico ma accessibile.\n\n" .
            "DETTAGLI OPERAZIONE:\n" .
            "- Sport: {$bet['sport']}\n" .
            "- Evento: {$bet['event_name']}\n" .
            "- Mercato: {$bet['market_name']}\n" .
            "- Scelta: {$runnerName} (Quota: {$bet['odds']})\n" .
            "- Motivazione Originale: {$motivation}\n" .
            "- ESITO: " . strtoupper($bet['status']) . " (Profitto: {$bet['profit']}€)\n\n" .
            "OBIETTIVO: Se hai vinto, spiega brevemente perché l'operazione ha avuto successo. Se hai perso, identifica se l'errore è stato nella quota non vantaggiosa, nel timing di ingresso o nella gestione della posizione.\n" .
            "IMPORTANTE: Ignora la fonte dei dati (CSV o Betfair). Concentrati solo sulle dinamiche di mercato.\n" .
            "RISPONDI SOLO CON LA LEZIONE TECNICA IN ITALIANO.";

        return $this->gemini->analyzeCustom($prompt);
    }
}
