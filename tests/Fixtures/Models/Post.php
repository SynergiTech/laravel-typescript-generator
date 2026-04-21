<?php

namespace SynergiTech\TypeScriptGenerator\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $table = 'posts';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
