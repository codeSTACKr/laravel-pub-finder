<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class RestaurantsDenver extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'RestaurantsDenver';

    protected $fillable = [
        'formatted_address',
        'name',
        'rating',
        'user_ratings_total',
        'lat',
        'lng',
        'reviews',
        'embeddings'
    ];
}
