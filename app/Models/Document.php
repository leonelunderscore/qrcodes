<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{

    use SoftDeletes;

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
}
