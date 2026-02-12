<?php
// tennis-bet/app/Controllers/TennisController.php

namespace TennisApp\Controllers;

use TennisApp\Config\TennisConfig;
use TennisApp\Services\TennisDataService;
use TennisApp\Services\TennisBetfairService;
use TennisApp\Services\TennisGeminiService;

class TennisController
{
    private $dataService;
    private $betfairService;
    private $geminiService;
    private $db;

    public function __construct()
    {
        $this->dataService = new TennisDataService();
        $this->betfairService = new TennisBetfairService();
        $this->geminiService = new TennisGeminiService();
        $this->db = TennisConfig::getDB();
    }

    public function index()
    {
        $portfolio = $this->getPortfolio();
        $activeBets = $this->getActiveBets();
        $recentBets = $this->getRecentBets();

        // Fetch events from Betfair
        $upcoming = $this->betfairService->getUpcomingTennisEvents(12);
        $events = $upcoming['result'] ?? [];

        // Prepare view data
        $viewData = [
            'portfolio' => $portfolio,
            'activeBets' => $activeBets,
            'recentBets' => $recentBets,
            'upcomingEvents' => $events
        ];

        return $viewData;
    }

    public function analyzeEvent($eventId, $eventName)
    {
        // 1. Get Market Info
        $catalogues = $this->betfairService->getMarketCatalogues([$eventId]);
        $market = $catalogues['result'][0] ?? null;
        if (!$market)
            return ["error" => "No markets found for event"];

        $marketId = $market['marketId'];
        $runners = $market['runners'];

        // 2. Get Prices
        $prices = $this->betfairService->getMarketBooks([$marketId]);
        $book = $prices['result'][0] ?? null;

        // 3. Get Player Historical Stats
        // Extract names from eventName (e.g. "Federer v Nadal")
        $names = explode(' v ', $eventName);
        $player1 = trim($names[0] ?? '');
        $player2 = trim($names[1] ?? '');

        $stats1 = $this->dataService->getPlayerHistory($player1);
        $stats2 = $this->dataService->getPlayerHistory($player2);

        // 4. Gemini Analysis
        $matchData = [
            'event' => $eventName,
            'market' => $market,
            'prices' => $book
        ];

        $historical = [
            $player1 => $stats1,
            $player2 => $stats2
        ];

        $portfolio = $this->getPortfolio();
        $analysis = $this->geminiService->analyzeMatch($matchData, $historical, $portfolio);

        // 5. If Confidence is high, place a virtual bet
        if (isset($analysis['confidence']) && $analysis['confidence'] >= TennisConfig::CONFIDENCE_THRESHOLD) {
            $this->placeVirtualBet($analysis);
        }

        return $analysis;
    }

    private function getPortfolio()
    {
        return $this->db->query("SELECT * FROM portfolio LIMIT 1")->fetch();
    }

    private function getActiveBets()
    {
        return $this->db->query("SELECT * FROM bets WHERE status = 'PENDING' ORDER BY created_at DESC")->fetchAll();
    }

    private function getRecentBets()
    {
        return $this->db->query("SELECT * FROM bets WHERE status != 'PENDING' ORDER BY created_at DESC LIMIT 10")->fetchAll();
    }

    private function placeVirtualBet($analysis)
    {
        $stmt = $this->db->prepare("INSERT INTO bets (market_id, event_name, advice, odds, stake, confidence, motivation) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $analysis['marketId'] ?? 'N/A',
            $analysis['event'] ?? 'Unknown',
            $analysis['advice'] ?? 'N/A',
            $analysis['odds'] ?? 0,
            $analysis['stake'] ?? TennisConfig::DEFAULT_STAKE,
            $analysis['confidence'] ?? 0,
            $analysis['motivation'] ?? ''
        ]);

        // Deduct from portfolio
        $stake = $analysis['stake'] ?? TennisConfig::DEFAULT_STAKE;
        $this->db->prepare("UPDATE portfolio SET balance = balance - ?")->execute([$stake]);
    }
}
