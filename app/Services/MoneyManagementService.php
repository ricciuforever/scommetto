<?php

namespace App\Services;

use App\Config\Config;

class MoneyManagementService
{
    /**
     * Calcola lo stake ottimale usando Kelly Frazionario e filtraggio EV+
     *
     * @param float $bankroll Il budget totale disponibile (Available + Exposure)
     * @param float $odds La quota offerta (es. 1.50)
     * @param float $confidence La probabilità stimata dall'AI (0-100)
     * @param float|null $kellyMultiplier Moltiplicatore di sicurezza
     * @return array [stake, reason, is_value_bet, kelly_fraction, edge]
     */
    /**
     * Calcola lo stake basato sulla modalità scelta (Kelly, Flat, Percentage)
     */
    public static function calculateStake(float $bankroll, float $odds, float $confidence, string $mode = 'kelly', float $value = 0.15, float $minStake = 2.00): array
    {
        // Enforce Betfair absolute minimum
        $effectiveMinStake = max(2.00, $minStake);

        $res = [];
        if ($mode === 'flat') {
            $res = [
                'stake' => round($value, 2),
                'reason' => 'Flat Bet',
                'is_value_bet' => true,
                'kelly_fraction' => 0,
                'edge' => 0
            ];
        } elseif ($mode === 'percentage') {
            $res = [
                'stake' => round($bankroll * ($value / 100), 2),
                'reason' => 'Percentage Bet (' . $value . '%)',
                'is_value_bet' => true,
                'kelly_fraction' => 0,
                'edge' => 0
            ];
        } else {
            // Default: Kelly
            return self::calculateOptimalStake($bankroll, $odds, $confidence, $value);
        }

        // Apply safety caps for Flat/Percentage too
        $maxAllowed = $bankroll * 0.05;
        if ($res['stake'] > $maxAllowed) {
            $res['stake'] = round($maxAllowed, 2);
            $res['reason'] .= " (Capped at 5%)";
        }

        if ($res['stake'] < $effectiveMinStake && $maxAllowed >= $effectiveMinStake) {
             $res['stake'] = $effectiveMinStake;
        } elseif ($res['stake'] < $effectiveMinStake) {
             $res['stake'] = 0;
             $res['reason'] = "Bankroll insufficiente per puntata minima (" . number_format($effectiveMinStake, 2) . "€)";
             $res['is_value_bet'] = false;
        }

        return $res;
    }

    public static function calculateOptimalStake(float $bankroll, float $odds, float $confidence, float $kellyMultiplier = 0.15, float $minStake = 2.00): array
    {

        // 1. Controllo base
        if ($odds <= 1.01 || $confidence <= 0) {
            return [
                'stake' => 0,
                'reason' => 'Dati non validi (Quota <= 1.01 o Confidenza <= 0)',
                'is_value_bet' => false,
                'kelly_fraction' => 0,
                'edge' => 0
            ];
        }

        // 2. Calcolo Probabilità Implicita del Bookmaker
        // Es. Quota 1.50 = 1/1.50 = 0.66 (66.6%)
        $impliedProbability = 1 / $odds;
        $estimatedProbability = $confidence / 100;
        $edge = $estimatedProbability - $impliedProbability;

        // 3. Filtro Expected Value (EV) con soglia minima
        // Se l'edge è inferiore alla soglia minima (es. 3%), non si gioca.
        if ($edge < Config::MIN_VALUE_THRESHOLD) {
            return [
                'stake' => 0,
                'reason' => "Edge insufficiente (" . round($edge * 100, 1) . "%, richiesto min " . (Config::MIN_VALUE_THRESHOLD * 100) . "%)",
                'is_value_bet' => false,
                'kelly_fraction' => 0,
                'edge' => $edge
            ];
        }

        // 4. Formula di Kelly: f* = (bp - q) / b
        // b = quote nette (decimal odds - 1)
        // p = probabilità di vittoria
        // q = probabilità di sconfitta (1 - p)
        $b = $odds - 1;
        $p = $estimatedProbability;
        $q = 1 - $p;

        $kellyFraction = ($b * $p - $q) / $b;

        // Se Kelly è negativo (non dovrebbe succedere qui dato il controllo sull'edge, ma per sicurezza)
        if ($kellyFraction <= 0) {
            return [
                'stake' => 0,
                'reason' => 'Kelly negativo o zero',
                'is_value_bet' => false,
                'kelly_fraction' => $kellyFraction,
                'edge' => $edge
            ];
        }

        // 5. Applicazione Frazionaria
        $suggestedStake = $bankroll * $kellyFraction * $kellyMultiplier;

        // Enforce Betfair absolute minimum
        $effectiveMinStake = max(2.00, $minStake);

        // 6. Logica "Smart Floor" e Limiti Hard
        // - Stake < (MinStake/2): SCARTA (SKIP)
        // - (MinStake/2) <= Stake < MinStake: FORZA A MinStake
        // - Stake >= MinStake: Usa valore calcolato

        $floor = $effectiveMinStake / 2;

        if ($suggestedStake < $floor) {
            return [
                'stake' => 0,
                'reason' => 'Stake suggerito troppo basso per il rischio (' . round($suggestedStake, 2) . '€)',
                'is_value_bet' => false,
                'kelly_fraction' => $kellyFraction,
                'edge' => $edge
            ];
        }

        $finalStake = max($effectiveMinStake, $suggestedStake);

        // Limite di Sicurezza: Mai più del 5% del bankroll totale su una singola operazione
        $maxAllowed = $bankroll * 0.05;
        if ($finalStake > $maxAllowed) {
            $finalStake = $maxAllowed;
        }

        // Se dopo il cap siamo sotto il minimo (può succedere se bankroll < MinStake * 20)
        if ($finalStake < $effectiveMinStake) {
            if ($maxAllowed >= $effectiveMinStake) {
                $finalStake = $effectiveMinStake;
            } else {
                return [
                    'stake' => 0,
                    'reason' => 'Bankroll troppo basso per rispettare i limiti minimi di sicurezza (' . number_format($effectiveMinStake, 2) . '€)',
                    'is_value_bet' => true, // Sarebbe value bet ma non abbiamo budget
                    'kelly_fraction' => $kellyFraction,
                    'edge' => $edge
                ];
            }
        }

        $finalStake = round($finalStake, 2);

        return [
            'stake' => $finalStake,
            'reason' => "Kelly: " . round($kellyFraction * 100, 2) . "%, Edge: " . round($edge * 100, 1) . "%, EV+ confermato",
            'is_value_bet' => true,
            'kelly_fraction' => $kellyFraction,
            'edge' => $edge
        ];
    }
}
