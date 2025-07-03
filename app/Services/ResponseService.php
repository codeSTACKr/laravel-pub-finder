<?php

namespace App\Services;

use MongoDB\Client;
use Illuminate\Support\Facades\Http;

class ResponseService
{
    protected $voyageApiKey;
    protected $mongoClient;

    public function __construct()
    {
        $this->voyageApiKey = env('VOYAGE_API_KEY');
        $this->mongoClient = new Client(env('MONGODB_URI'));
    }

    public function search(string $query, $referenceLat = null, $referenceLng = null)
    {
        $embedding = $this->getEmbedding($query);
        
        
        $referenceLat = $referenceLat ?? 39.7392; 
        $referenceLng = $referenceLng ?? -104.9903; 
    
        $collection = $this->mongoClient
            ->selectDatabase(env('MONGODB_DATABASE'))
            ->selectCollection('pub-data');  
    
        $cursor = $collection->aggregate([
            [
                '$vectorSearch' => [
                    'index' => 'vector_index',
                    'path' => 'embedding',
                    'queryVector' => $embedding,
                    'limit' => 5,
                    'numCandidates' => 100
                ]
            ],
            [
                '$addFields' => [
                    'similarityScore' => ['$meta' => 'vectorSearchScore'],
                   
                    'calculatedDistance' => [
                        '$cond' => [
                            'if' => [
                                '$and' => [
                                    ['$ne' => ['$lng', null]], 
                                    ['$ne' => ['$lat', null]]  
                                ]
                            ],
                            'then' => [
                                '$multiply' => [
                                    [
                                        '$acos' => [
                                            '$add' => [
                                                [
                                                    '$multiply' => [
                                                        ['$sin' => ['$degreesToRadians' => $referenceLat]],
                                                        ['$sin' => ['$degreesToRadians' => '$lat']]
                                                    ]
                                                ],
                                                [
                                                    '$multiply' => [
                                                        ['$cos' => ['$degreesToRadians' => $referenceLat]],
                                                        ['$cos' => ['$degreesToRadians' => '$lat']],
                                                        ['$cos' => [
                                                            '$degreesToRadians' => [
                                                                '$subtract' => [$referenceLng, '$lng']
                                                            ]
                                                        ]]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                    3959 
                                ]
                            ],
                            'else' => null
                        ]
                    ]
                ]
            ],
            [
                '$sort' => [
                    'similarityScore' => -1,
                    'calculatedDistance' => 1
                ]
                ],
            [
                '$project' => [
                    '_id' => 0,
                    'name' => 1,
                    'formatted_address' => 1,
                    'rating' => 1,
                    'reviews' => 1,
                    'GoogleMapURI' => 1,
                    'similarityScore' => 1,
                    'calculatedDistance' => 1
                ]
            ]
        ]);
    
        $results = $cursor->toArray();

        return array_map(function($result) use ($referenceLat, $referenceLng) {
            $distance = $this->formatDistance($result, $referenceLat, $referenceLng);
            
            // Try different possible field names for Google Maps URL
            $googleMapUrl = $result['GoogleMapURI'] ??  '#';
            
            return [
                'name' => $result['name'] ?? 'Unnamed Pub',
                'formatted_address' => $result['formatted_address'] ?? '',
                'rating' => $result['rating'] ?? 0,
                'reviews' => $result['reviews'] ?? 'No reviews available.',
                'distance' => $distance,
                'GoogleMapURI' => $googleMapUrl,
                'similarityScore' => number_format($result['similarityScore'] ?? 0, 8)
            ];
        }, $results);
    }
    
    private function formatDistance($result, $referenceLat, $referenceLng)
    {
        
        if (isset($result['calculatedDistance']) && $result['calculatedDistance'] !== null) {
            return number_format($result['calculatedDistance'], 1) . ' miles';
        }
        
        if (isset($result['lat']) && isset($result['lng']) && 
            $result['lat'] !== null && $result['lng'] !== null) {
            
            $distance = $this->calculateDistance($referenceLat, $referenceLng, $result['lat'], $result['lng']);
            return number_format($distance, 1) . ' miles';
        }
        
        // If no coordinates available, return unknown
        return 'Distance unknown';
    }
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 3959; // Earth's radius in miles
        
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);
        
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;
        
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }


    public function getEmbedding(string $query): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->voyageApiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.voyageai.com/v1/embeddings', [
            'model' => 'voyage-3.5',
            'input' => $query,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get embedding from VoyageAI');
        }

        return $response->json()['data'][0]['embedding'];
    }
}