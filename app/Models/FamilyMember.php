<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $family_id
 * @property string $user_id
 * @property Role $role
 * @property-read Family $family
 * @property-read User $user
 */
#[Fillable(['family_id', 'user_id', 'role'])]
class FamilyMember extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The claims the gateway puts in every token it signs for this member.
     *
     * @return array{user_id: string, family_id: string, role: string, permissions: list<string>}
     */
    public function claims(): array
    {
        return [
            'user_id' => $this->user_id,
            'family_id' => $this->family_id,
            'role' => $this->role->value,
            'permissions' => $this->role->permissions(),
        ];
    }
}
