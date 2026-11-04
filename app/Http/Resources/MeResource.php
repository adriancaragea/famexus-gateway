<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user with their family, role and permissions, so the React
 * app can decide which pages and buttons to show.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->membership;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'family' => $membership === null ? null : [
                'id' => $membership->family->id,
                'name' => $membership->family->name,
            ],
            'role' => $membership?->role->value,
            'permissions' => $membership?->role->permissions() ?? [],
        ];
    }
}
