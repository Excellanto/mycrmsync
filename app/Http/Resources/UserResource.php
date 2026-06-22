<?php

namespace App\Http\Resources;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'intsysuser' => $this->intsysuser,
            'mapped_user' => $this->mapped_user_display ?? '—',
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'tenant' => $this->whenLoaded('tenant', function () {
                if (! $this->tenant) {
                    return null;
                }

                $slug = data_get($this->tenant->integration, 'slug');
                $integratedSystemName = filled($slug)
                    ? Integration::query()->where('slug', (string) $slug)->value('name')
                    : null;

                return [
                    'id' => $this->tenant->id,
                    'company_name' => $this->tenant->company_name,
                    'integrated_system_name' => $integratedSystemName,
                ];
            }),
        ];
    }
}
