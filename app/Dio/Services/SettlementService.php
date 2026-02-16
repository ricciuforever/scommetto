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
        $this->db = DioDatabase::getInstance()->getConnection();

        // Fetch custom Betfair credentials from agent database
        $overrides = $this->db->query("SELECT key, value FROM system_state WHERE key LIKE 'BETFAIR_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $overrides = array_filter($overrides);

        $this->bf = new BetfairService($overrides, 'dio');
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
        // Optimized: Chunking requests to avoid TOO_MUCH_DATA with EX_TRADED (Limit 5 per batch)
        $chunks = array_chunk($pendingBets, 5);

        foreach ($chunks as $batch) {
            $marketIds = array_unique(array_column($batch, 'market_id'));
            $bookRes = $this->bf->getMarketBooks($marketIds);
            $booksMap = [];
            foreach ($bookRes['result'] ?? [] as $b) {
                $booksMap[$b['marketId']] = $b;
            }

            foreach ($batch as $bet) {
                $book = $booksMap[$bet['market_id']] ?? null;

                if (!$book)
                    continue; // Skip if market not found in API

                if ($book['status'] === 'CLOSED') {
                    $winnerSelectionId = null;
                    foreach ($book['runners'] as $runner) {
                        if ($runner['status'] === 'WINNER') {
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

                        if ($isWin) {
                            $this->updateVirtualBalance($profit + $bet['stake']);
                        }

                        $settledCount++;
                    }
                } else {
                    // Market is OPEN/SUSPENDED - Do nothing for scores
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
