<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'method',
        'path',
        'response_time',
        'status_code',
    ];

    
}
