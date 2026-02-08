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
            $id = $_GET['id'] ?? null;
            $country = $_GET['country'] ?? null;
            $city = $_GET['city'] ?? null;
            $name = $_GET['name'] ?? null;

            if ($id) {
                if ($model->needsRefresh($id, 24)) {
                    $this->sync(['id' => $id]);
                }
                $result = $model->getById($id);
            } elseif ($country) {
                // Per semplicità facciamo sync solo se la tabella è vuota o forzato
                // L'endpoint richiede almeno un parametro.
                $result = $model->getByCountry($country);
                if (empty($result)) {
                    $this->sync(['country' => $country]);
                    $result = $model->getByCountry($country);
                }
            } else {
                $result = $model->getAll(100);
            }

            echo json_encode(['response' => $result]);
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
