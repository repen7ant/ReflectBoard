<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'parent_id', 'category_id', 'title', 'description',
        'reflection_text', 'time_spent_minutes', 'status',
        'is_project', 'is_on_board', 'is_quick_capture', 'is_productive',
        'deadline', 'tags', 'completed_at', 'position',
        'category_snapshot_name', 'category_snapshot_color',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_project' => 'boolean',
        'is_on_board' => 'boolean',
        'is_quick_capture' => 'boolean',
        'is_productive' => 'boolean',
        'deadline' => 'datetime',
        'completed_at' => 'datetime',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'parent_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
