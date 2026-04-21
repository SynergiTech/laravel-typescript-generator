<?php

namespace SynergiTech\TypeScriptGenerator\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SynergiTech\TypeScriptGenerator\Tests\Fixtures\Enums\Status;

class User extends Model
{
    protected $table = 'users';

    protected $casts = [
        'is_admin' => 'boolean',
        'metadata' => 'array',
        'status'   => Status::class,
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
