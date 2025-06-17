<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CreateRestaurantEmbeddingsCommand extends Command
{
    protected $signature = 'restaurants-denver:json-embedding';
    protected $description = 'Generate embeddings for RestaurantsDenver.json';

    public function handle()
    {
        $filePath = public_path('denver_pubs.json');

        if (!file_exists($filePath)) {
            $this->error("File not found: $filePath");
            return;
        }

        $this->info("Reading JSON file...");

        $jsonData = file_get_contents($filePath);
        $restaurants = json_decode($jsonData, true);

        if (!is_array($restaurants)) {
            $this->error("Failed to decode JSON.");
            return;
        }

        $apiKey = env('VOYAGE_API_KEY');
        $processed = 0;

        foreach ($restaurants as &$restaurant) {

            if (!isset($restaurant['reviews']) || empty($restaurant['reviews'])) {
                $this->warn("Skipping {$restaurant['name']} - no reviews.");
                continue;
            }

            $embedding = $this->getEmbedding($restaurant['reviews'], $apiKey);

            if ($embedding) {
                $restaurant['embedding'] = $embedding;
                $processed++;
                $this->info("Processed {$restaurant['name']}");
            } else {
                $this->error("Failed embedding for {$restaurant['name']}");
            }
        }

        file_put_contents($filePath, json_encode($restaurants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Updated JSON with embeddings. Total processed: $processed");
    }

    private function getEmbedding($text, $apiKey)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.voyageai.com/v1/embeddings', [
            'model' => 'voyage-3.5',
            'input' => $text,
        ]);

        if ($response->successful()) {
            return $response->json()['data'][0]['embedding'];
        }

        return null;
    }
}
