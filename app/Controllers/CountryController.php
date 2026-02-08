<?php
// app/Controllers/CountryController.php

namespace App\Controllers;

use App\Models\Country;
use App\Services\FootballApiService;

class CountryController
{
    /**
     * Carica la vista dei paesi (React)
     */
    public function index()
    {
        $file = __DIR__ . '/../Views/countries.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>Vista Countries non trovata</h1>";
        }
    }

    /**
     * Ritorna i dati JSON dei paesi con aggiornamento on-demand (24h)
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $model = new Country();

            // Verifica se i dati nel DB sono scaduti (default 24 ore)
            if ($model->needsRefresh(24)) {
                $this->sync();
            }

            $countries = $model->getAll();
            echo json_encode(['response' => $countries]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza i paesi dall'API al Database
     */
    private function sync()
    {
        $api = new FootballApiService();
        $model = new Country();

        $data = $api->fetchCountries();

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                $model->save([
                    'name' => $item['name'],
                    'code' => $item['code'] ?? null,
                    'flag' => $item['flag'] ?? null
                ]);
            }
        }
    }
}
