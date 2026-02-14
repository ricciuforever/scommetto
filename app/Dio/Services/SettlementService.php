<?php
// app/Dio/Services/SettlementService.php

namespace App\Dio\Services;

use App\Services\BetfairService;
use App\Dio\DioDatabase;
use PDO;

class SettlementService
{
    private $bf;
    private $db;

    public function __construct()
    {
        $this->bf = new BetfairService();
        $this->db = DioDatabase::getInstance()->getConnection();
    }

    /**
     * Scans for pending virtual bets and checks their outcome on Betfair
     */
    public function settlePendingBets()
    {
        $stmt = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending'");
        $stmt->execute();
        $pendingBets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendingBets))
            return 0;

        $settledCount = 0;
        foreach ($pendingBets as $bet) {
            $marketId = $bet['market_id'];

            // Check if market is settled via listClearedOrders
            // Note: Since these are virtual, we look for the market status in listMarketBook first
            // because listClearedOrders only shows real bets.
            $bookRes = $this->bf->getMarketBooks([$marketId]);
            $book = $bookRes['result'][0] ?? null;

            if ($book && $book['status'] === 'CLOSED') {
                // Determine winner from runners
                $winnerSelectionId = null;
                foreach ($book['runners'] as $runner) {
                    // echo "Runner {$runner['selectionId']} status: {$runner['status']}\n";
                    if ($runner['status'] === 'WINNER') {
                        // BUG FIX: Betfair uses selectionId (camelCase)
                        $winnerSelectionId = $runner['selectionId'];
                        break;
                    }
                }

                if ($winnerSelectionId !== null) {
                    $isWin = ($winnerSelectionId == $bet['selection_id']);
                    $profit = $isWin ? ($bet['stake'] * $bet['odds']) - $bet['stake'] : -$bet['stake'];
                    $status = $isWin ? 'won' : 'lost';

                    $update = $this->db->prepare("UPDATE bets SET status = ?, profit = ?, settled_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $update->execute([$status, $profit, $bet['id']]);

                    // Update bankroll
                    if ($isWin) {
                        $this->updateVirtualBalance($profit + $bet['stake']);
                    }

                    $settledCount++;
                }
            }
        }

        return $settledCount;
    }

    private function updateVirtualBalance($amount)
    {
        $stmt = $this->db->prepare("UPDATE system_state SET value = value + ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'virtual_balance'");
        $stmt->execute([number_format($amount, 2, '.', '')]);
    }
}
