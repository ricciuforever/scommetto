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
    public static function calculateOptimalStake(float $bankroll, float $odds, float $confidence, float $kellyMultiplier = null): array
    {
        if ($kellyMultiplier === null) {
            $kellyMultiplier = Config::KELLY_MULTIPLIER_GIANIK;
        }

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

        // 6. Logica "Smart Floor" e Limiti Hard
        // - Stake < 1.00€: SCARTA (SKIP)
        // - 1.00€ <= Stake < 2.00€: FORZA A 2.00€ (Minimo Betfair)
        // - Stake >= 2.00€: Usa valore calcolato

        if ($suggestedStake < 1.00) {
            return [
                'stake' => 0,
                'reason' => 'Stake suggerito troppo basso per il rischio (' . round($suggestedStake, 2) . '€)',
                'is_value_bet' => false,
                'kelly_fraction' => $kellyFraction,
                'edge' => $edge
            ];
        }

        $finalStake = max(2.00, $suggestedStake);

        // Limite di Sicurezza: Mai più del 5% del bankroll totale su una singola operazione
        $maxAllowed = $bankroll * 0.05;
        if ($finalStake > $maxAllowed) {
            $finalStake = $maxAllowed;
        }

        // Se dopo il cap siamo sotto il minimo di 2€ (può succedere se bankroll < 40€)
        if ($finalStake < 2.00) {
            if ($maxAllowed >= 2.00) {
                $finalStake = 2.00;
            } else {
                return [
                    'stake' => 0,
                    'reason' => 'Bankroll troppo basso per rispettare i limiti minimi di sicurezza',
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
