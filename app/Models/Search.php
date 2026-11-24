<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Search extends Model
{
    protected $fillable = ['city', 'country', 'latitude', 'longitude', 'hits'];

    /** Запомнить найденный город: новый добавить, у старого увеличить счётчик. */
    public static function remember(array $city): void
    {
        $search = static::firstOrNew([
            'latitude' => round($city['latitude'], 5),
            'longitude' => round($city['longitude'], 5),
        ]);

        $search->fill(['city' => $city['name'], 'country' => $city['country']]);
        $search->hits = $search->exists ? $search->hits + 1 : 1;
        $search->save();
    }
}
