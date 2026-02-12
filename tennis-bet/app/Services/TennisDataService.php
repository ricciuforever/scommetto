<?php
// tennis-bet/app/Services/TennisDataService.php

namespace TennisApp\Services;

use TennisApp\Config\TennisConfig;

class TennisDataService
{
    /**
     * Downloads a CSV file from Jeff Sackmann's GitHub if not already present or out of date.
     */
    public function syncHistoricalData($type = 'atp', $year = null)
    {
        if (!$year)
            $year = date('Y');

        $baseUrl = ($type === 'atp') ? TennisConfig::JEFF_SACKMANN_ATP_BASE : TennisConfig::JEFF_SACKMANN_WTA_BASE;
        $filename = "{$type}_matches_{$year}.csv";
        $url = $baseUrl . $filename;
        $localPath = TennisConfig::DATA_PATH . $filename;

        // Only download if older than 24h
        if (!file_exists($localPath) || (time() - filemtime($localPath) > 86400)) {
            $content = @file_get_contents($url);
            if ($content) {
                if (!is_dir(dirname($localPath)))
                    mkdir(dirname($localPath), 0777, true);
                file_put_contents($localPath, $content);
                return ["success" => true, "message" => "Downloaded $filename", "path" => $localPath];
            }
            return ["success" => false, "message" => "Failed to download $filename from $url"];
        }

        return ["success" => true, "message" => "$filename is already up to date.", "path" => $localPath];
    }

    /**
     * Parses the CSV and returns recent matches for a player or tournament.
     */
    public function getPlayerHistory($playerName, $type = 'atp', $limit = 10)
    {
        $year = date('Y');
        $data = $this->syncHistoricalData($type, $year);
        if (!$data['success'])
            return [];

        $handle = fopen($data['path'], "r");
        if (!$handle)
            return [];

        $header = fgetcsv($handle);
        $matches = [];

        while (($row = fgetcsv($handle)) !== FALSE) {
            $match = array_combine($header, $row);
            if (stripos($match['winner_name'], $playerName) !== false || stripos($match['loser_name'], $playerName) !== false) {
                $matches[] = $match;
                if (count($matches) >= $limit)
                    break;
            }
        }
        fclose($handle);
        return $matches;
    }
}
