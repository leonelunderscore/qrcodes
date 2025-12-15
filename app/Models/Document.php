<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'reference',
        'name',
        'path',
        'size',
        'mime',
        'page_count',
        'pages',
        'full_size',
        'status',
    ];

    public static function generateReference(): string
    {
        do {
            $reference = mb_strtolower(substr(md5(uniqid(rand(), true)), 0, 8));
        } while (Document::where('reference', $reference)->exists());
        return $reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'pages' => 'array'
        ];
    }
}
