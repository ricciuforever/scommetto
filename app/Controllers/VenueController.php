<?php
// app/Controllers/VenueController.php

namespace App\Controllers;

use App\Models\Venue;
use App\Services\FootballApiService;

class VenueController
{
    /**
     * Carica la vista degli stadi
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/venues.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Venues non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON degli stadi con aggiornamento on-demand (24h)
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $model = new Venue();

            $filters = [
                'id' => $_GET['id'] ?? null,
                'name' => $_GET['name'] ?? null,
                'city' => $_GET['city'] ?? null,
                'country' => $_GET['country'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];

            // On-demand sync se i risultati sono vuoti per i filtri forniti (e non è solo getAll)
            if (array_filter($filters)) {
                $venues = $model->find($filters);
                if (empty($venues)) {
                    // Proviamo a sincronizzare con i filtri forniti all'API
                    $apiParams = array_filter($filters, fn($k) => in_array($k, ['id', 'name', 'city', 'country']), ARRAY_FILTER_USE_KEY);
                    if (!empty($apiParams)) {
                        $this->sync($apiParams);
                        $venues = $model->find($filters);
                    }
                }
            } else {
                $venues = $model->getAll(100);
            }

            echo json_encode(['response' => $venues]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza gli stadi dall'API al Database
     */
    private function sync($params)
    {
        $api = new FootballApiService();
        $model = new Venue();

        $data = $api->fetchVenues($params);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                $model->save($item);
            }
        }
    }
}
