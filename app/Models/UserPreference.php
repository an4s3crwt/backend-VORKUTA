<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'map_theme', 'map_filters'];
    protected $casts = ['map_filters' => 'array'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
