<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VoyageEmbeddingService
{
    public function embed(string $text): array
    {
        $response = Http::withToken(env('VOYAGE_API_KEY'))
            ->post(env('VOYAGE_ENDPOINT'), [
                'input' => $text,
                'model' => env('VOYAGE_API_MODEL', 'voyage-2'),
            ]);

        if ($response->successful()) {
            return $response->json('data.0.embedding');
        }

        throw new \Exception('Embedding failed: ' . $response->body());
    }
}
