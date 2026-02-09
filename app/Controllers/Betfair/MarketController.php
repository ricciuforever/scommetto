<?php
// app/Controllers/Betfair/MarketController.php

namespace App\Controllers\Betfair;

use App\Services\BetfairService;
use App\Services\GeminiService;
use App\Config\Config;

class MarketController
{
    private $betfair;
    private $gemini;

    public function __construct()
    {
        $this->betfair = new BetfairService();
        $this->gemini = new GeminiService();
    }

    public function index()
    {
        $file = __DIR__ . '/../../Views/betfair/live_markets.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Betfair Live Markets</h1><p>View not found.</p>";
        }
    }

    /**
     * API: Get Account Funds & Orders
     */
    public function getUserAccount()
    {
        header('Content-Type: application/json');
        try {
            $funds = $this->betfair->getFunds();
            $orders = $this->betfair->getCurrentOrders();

            echo json_encode([
                'balance' => $funds['result'] ?? null,
                'orders' => $orders['result']['currentOrders'] ?? []
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get all live events grouped by Sport
     */
    public function getLiveEvents()
    {
        header('Content-Type: application/json');

        if (!$this->betfair->isConfigured()) {
            echo json_encode(['error' => 'Betfair not configured']);
            return;
        }

        try {
            // 1. Get All Event Types (Sports)
            $sports = $this->betfair->getEventTypes();
            if (empty($sports['result'])) {
                echo json_encode(['response' => [], 'debug' => 'No sports found']);
                return;
            }

            $liveData = [];

            // Allow more sports and fix naming checks
            // 'Soccer' is ID 1. Let's rely on IDs for main sports if possible, or just iterate top ones.
            $targetSports = [
                '1' => 'Soccer',
                '2' => 'Tennis',
                '7522' => 'Basketball',
                '4' => 'Cricket',
                '7524' => 'Ice Hockey'
            ];

            foreach ($sports['result'] as $sport) {
                // If ID is in our target list, use our name, otherwise skip or allow generic?
                // Let's try to fetch live events for ALL returned sports types (filtering empty later)
                // but limit to top 10 to avoid timeouts.

                $id = $sport['eventType']['id'];
                $name = $sport['eventType']['name'];

                // Prioritize Target Sports
                if (!isset($targetSports[$id]) && count($liveData) >= 5)
                    continue;

                $events = $this->betfair->getLiveEvents([$id]);

                if (!empty($events['result'])) {
                    // Force name consistency
                    $displayName = $targetSports[$id] ?? $name;
                    $liveData[$displayName] = $events['result'];
                }
            }

            echo json_encode(['response' => $liveData]);

        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get Market Book for specific Markets
     */
    public function getMarketBook()
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $marketIds = $input['marketIds'] ?? ($_GET['ids'] ? explode(',', $_GET['ids']) : []);

        if (empty($marketIds)) {
            echo json_encode(['error' => 'No Market IDs provided']);
            return;
        }

        try {
            // Limit to 40 per request
            $books = $this->betfair->getMarketBooks(array_slice($marketIds, 0, 40));
            echo json_encode(['response' => $books['result'] ?? []]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get Market Catalogue for Event
     */
    public function getEventMarkets($eventId)
    {
        header('Content-Type: application/json');
        try {
            $catalogue = $this->betfair->getMarketCatalogues([$eventId], 20); // Top 20 markets
            echo json_encode(['response' => $catalogue['result'] ?? []]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Helper to get full deep market data for Gemini
     */
    public function analyzeMarket($marketId)
    {
        header('Content-Type: application/json');
        try {
            // 1. Get Market Book (Odds & Volume)
            $book = $this->betfair->getMarketBooks([$marketId]);
            if (empty($book['result'])) {
                throw new \Exception("Market Book not found");
            }
            $marketBook = $book['result'][0];

            // 2. We need Market Catalogue for runners names (Book only has SelectionId)
            // Ideally we pass eventId, but we might not have it easily here if just marketId provided.
            // But we can try to guess or require the client to pass full context.
            // For this specific 'Better Separato', we assume the frontend sends the Event context.

            // Standardizing Data for Gemini
            $candidate = [
                'marketId' => $marketBook['marketId'],
                'totalMatched' => $marketBook['totalMatched'],
                'status' => $marketBook['status'],
                'runners' => []
            ];

            // Merging with Names passed from Input or fetching catalogue if needed
            // For V1, let's keep it simple: client sends context, we add fresh odds

            $input = json_decode(file_get_contents('php://input'), true);
            $eventContext = $input['event'] ?? [];

            $candidate = array_merge($candidate, $eventContext);

            // Populate runners with fresh prices
            if (isset($input['runnersMap'])) { // Map selectionId -> RunnerName
                foreach ($marketBook['runners'] as $r) {
                    $name = $input['runnersMap'][$r['selectionId']] ?? 'Unknown';
                    $candidate['runners'][] = [
                        'name' => $name,
                        'selectionId' => $r['selectionId'],
                        'back' => $r['ex']['availableToBack'][0]['price'] ?? null,
                        'lay' => $r['ex']['availableToLay'][0]['price'] ?? null
                    ];
                }
            }

            // Call Gemini
            $balance = [
                'available_balance' => 0, // Mock or fetch real
                'current_portfolio' => 0
            ];

            // Optional: Fetch real balance
            try {
                $funds = $this->betfair->getFunds();
                if (isset($funds['result']))
                    $funds = $funds['result'];
                $balance['available_balance'] = $funds['availableToBetBalance'] ?? 0;
            } catch (\Exception $ex) {
            }


            // Force standard prompt mode
            $analysis = $this->gemini->analyze([$candidate], $balance);

            echo json_encode([
                'analysis' => $analysis,
                'market_book' => $marketBook
            ]);

        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
