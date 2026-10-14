<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'driver' => $this->driver,
            'config' => $this->config,
            'enabled' => $this->enabled,
            'last_run_at' => $this->last_run_at,
            'last_status' => $this->last_status,
        ];
    }
}
