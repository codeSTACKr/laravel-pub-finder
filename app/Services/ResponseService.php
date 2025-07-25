<?php

namespace App\Services;

use MongoDB\Client;
use Illuminate\Support\Facades\Http;

class ResponseService
{
    protected $voyageApiKey;
    protected $mongoClient;
    protected $openaiApiKey;

    public function __construct()
    {
        $this->voyageApiKey = env('VOYAGE_API_KEY');
        $this->openaiApiKey = env('OPENAI_API_KEY');
        $this->mongoClient = new Client(env('MONGODB_URI'));
    }

    public function search(string $query, $referenceLat = null, $referenceLng = null)
    {
        if (!$this->isValidPubQuery($query)) {
            throw new \Exception('Not a valid search string. Please search for pubs, bars, breweries, or beer-related terms.');
        }

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
                    'limit' => 20, 
                    'numCandidates' => 200
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
                '$match' => [
                    '$or' => [
                        ['name' => ['$regex' => '(?i).*(pub|bar|brewery|taproom|tavern|saloon|lounge|ale|beer|craft|brewing|brewpub).*']],
                        ['formatted_address' => ['$regex' => '(?i).*(pub|bar|brewery|taproom|tavern|saloon|lounge|ale|beer|craft|brewing|brewpub).*']],
                        ['reviews' => ['$regex' => '(?i).*(pub|bar|brewery|taproom|tavern|saloon|lounge|ale|beer|craft|brewing|brewpub|drink|alcohol|pint|draft|draught).*']],
                        
                        ['$and' => [
                            ['name' => ['$not' => ['$regex' => '(?i).*(dunkin|starbucks|mcdonalds|burger|pizza|taco|subway|wendys|kfc|arbys|dominos|papa|chipotle|qdoba|panera).*']]],
                            ['formatted_address' => ['$not' => ['$regex' => '(?i).*(dunkin|starbucks|mcdonalds|burger|pizza|taco|subway|wendys|kfc|arbys|dominos|papa|chipotle|qdoba|panera).*']]]
                        ]]
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
                '$limit' => 5 
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

        return array_map(function($result) use ($referenceLat, $referenceLng, $query) {
            $distance = $this->formatDistance($result, $referenceLat, $referenceLng);
            
            $googleMapUrl = $result['GoogleMapURI'] ??  '#';
            
            
            $summarizedReview = $this->summarizeReview($result['reviews'] ?? '', $query);
            
            return [
                'name' => $result['name'] ?? 'Unnamed Pub',
                'formatted_address' => $result['formatted_address'] ?? '',
                'rating' => $result['rating'] ?? 0,
                'reviews' => $summarizedReview,
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

    private function isValidPubQuery(string $query): bool
    {
        // List of valid pub/bar/brewery related keywords and phrases
        $validKeywords = [
            'pub', 'bar', 'brewery', 'brewpub', 'taproom', 'tavern', 'saloon', 'lounge',
            'ale', 'beer', 'craft', 'brewing', 'brewhouse', 'gastropub', 'sports bar',
            'dive bar', 'cocktail bar', 'wine bar', 'beer garden', 'microbrewery',
            'distillery', 'whiskey', 'bourbon', 'vodka', 'gin', 'rum', 'tequila',
            'drink', 'drinks', 'alcohol', 'alcoholic', 'pint', 'draft', 'draught',
            'happy hour', 'nightlife', 'bartender', 'mixologist', 'cocktail', 'cocktails',
            'ipa', 'lager', 'stout', 'porter', 'pilsner', 'wheat beer', 'pale ale',
            'dark beer', 'light beer', 'local beer', 'craft beer', 'on tap',
            'wine', 'spirits', 'liquor', 'martini', 'margarita', 'mojito',
            'outdoor seating', 'live music', 'trivia night', 'karaoke', 'pool table',
            'darts', 'game', 'games', 'watch', 'sports', 'tv', 'television'
        ];

        $query = strtolower(trim($query));
        
        
        foreach ($validKeywords as $keyword) {
            if (strpos($query, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function summarizeReview(string $reviews, string $query): string
    {
        if (empty($reviews) || $reviews === 'No reviews available.') {
            return 'No reviews available.';
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that summarizes pub/bar/brewery reviews. Create a brief, relevant summary (max 100 words) focusing on aspects most relevant to the user\'s search query. Highlight key points about drinks, atmosphere, service, food, or specific features mentioned.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Search query: \"{$query}\"\n\nReviews to summarize:\n{$reviews}\n\nPlease provide a brief summary focusing on aspects most relevant to the search query."
                    ]
                ],
                'max_tokens' => 150,
                'temperature' => 0.3
            ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';
                return !empty($content) ? trim($content) : 'Review summary unavailable.';
            }
        } catch (\Exception $e) {
            // Log error if needed, but don't break the application
            \Log::warning('OpenAI summarization failed: ' . $e->getMessage());
        }

        // Fallback: return truncated original reviews
        return strlen($reviews) > 200 ? substr($reviews, 0, 197) . '...' : $reviews;
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