<?php

namespace SynergiTech\TypeScriptGenerator\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'title'  => $this->title,
            'body'   => $this->body,
            'author' => new UserResource($this->user),
        ];
    }
}
