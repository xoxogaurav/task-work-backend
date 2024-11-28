<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'reward',
        'time_estimate',
        'category',
        'difficulty',
        'time_in_seconds',
        'steps',
        'approval_type',
        'is_active'
    ];

    protected $casts = [
        'steps' => 'array',
        'reward' => 'decimal:2',
        'time_in_seconds' => 'integer',
        'is_active' => 'boolean'
    ];

    public function submissions()
    {
        return $this->hasMany(TaskSubmission::class);
    }
}