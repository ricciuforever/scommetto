<?php
// verification/test_gemini_optimization.php

require_once __DIR__ . '/../bootstrap.php';

use App\Dio\Controllers\DioQuantumController;
use App\GiaNik\Controllers\GiaNikController;
use App\Services\GeminiService;

echo "Verifica Ottimizzazione Gemini API\n";
echo "==================================\n\n";

// 1. Check GeminiService has analyzeBatch
if (method_exists(GeminiService::class, 'analyzeBatch')) {
    echo "[OK] GeminiService::analyzeBatch esiste.\n";
} else {
    echo "[FAIL] GeminiService::analyzeBatch MANCANTE.\n";
}

// 2. Check DioQuantumController analyzeQuantumBatch
if (method_exists(DioQuantumController::class, 'analyzeQuantumBatch')) {
    echo "[OK] DioQuantumController::analyzeQuantumBatch esiste.\n";
} else {
    echo "[FAIL] DioQuantumController::analyzeQuantumBatch MANCANTE.\n";
}

// 3. Inspect GiaNikController::autoProcess for batch logic
$gianikFile = file_get_contents(__DIR__ . '/../app/GiaNik/Controllers/GiaNikController.php');
if (strpos($gianikFile, '$batchEvents[] = $event') !== false && strpos($gianikFile, 'analyzeBatch($batchEvents') !== false) {
    echo "[OK] GiaNikController::autoProcess implementa il batching.\n";
} else {
    echo "[FAIL] GiaNikController::autoProcess NON sembra implementare il batching.\n";
}

// 4. Inspect DioQuantumController::scanAndTrade for batch logic
$dioFile = file_get_contents(__DIR__ . '/../app/Dio/Controllers/DioQuantumController.php');
if (strpos($dioFile, '$batchTickers[] = $ticker') !== false && strpos($dioFile, 'analyzeQuantumBatch($batchTickers') !== false) {
    echo "[OK] DioQuantumController::scanAndTrade implementa il batching.\n";
} else {
    echo "[FAIL] DioQuantumController::scanAndTrade NON sembra implementare il batching.\n";
}

// 5. Check cooldowns
if (preg_match('/time\(\) - \$lastRun < 180/', $dioFile)) {
    echo "[OK] Cooldown Dio impostato a 180s.\n";
} else {
    echo "[FAIL] Cooldown Dio NON corretto.\n";
}

if (preg_match('/time\(\) - \$lastRun < 120/', $gianikFile)) {
    echo "[OK] Cooldown GiaNik impostato a 120s.\n";
} else {
    echo "[FAIL] Cooldown GiaNik NON corretto.\n";
}

echo "\nFine Verifica.\n";
