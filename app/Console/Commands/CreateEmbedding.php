<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RestaurantsDenver;
use App\Services\VoyageEmbeddingService;

class CreateEmbedding extends Command
{
    protected $signature = 'restaurants-denver:create-embedding';
    protected $description = 'Generate embeddings for the reviews fields and save into MongoDB';

    protected VoyageEmbeddingService $embeddingService;

    public function __construct(VoyageEmbeddingService $embeddingService)
    {
        parent::__construct();
        $this->embeddingService = $embeddingService;
    }

    public function handle(): int
    {
        $restaurants = RestaurantsDenver::where('embedding', 'exists', false)
        ->orWhereNull('embedding')
        ->get();

        if ($restaurants->isEmpty()) {
            $this->info("All restaurants already have embeddings.");
            return 0;
        }

        foreach ($restaurants as $restaurant) {
            try {
                $embedding = $this->embeddingService->embed($restaurant->reviews);

                $restaurant->embedding = $embedding;
                $restaurant->save();

                $this->info("Embedded: {$restaurant->name}");
            } catch (\Throwable $e) {
                $this->error("Failed: {$restaurant->name} - {$e->getMessage()}");
            }
        }

        return 0;
    }
}
