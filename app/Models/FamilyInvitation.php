<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $family_id
 * @property string|null $invited_by
 * @property string $email
 * @property Role $role
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read Family $family
 * @property-read User|null $inviter
 */
#[Fillable(['family_id', 'invited_by', 'email', 'role', 'token_hash', 'expires_at'])]
#[Hidden(['token_hash'])]
class FamilyInvitation extends Model
{
    use HasUlids;

    public const VALID_DAYS = 7;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Create an invitation and return it with the plain token for the email link.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Family $family, User $inviter, string $email, Role $role): array
    {
        $token = Str::random(48);

        $invitation = self::create([
            'family_id' => $family->id,
            'invited_by' => $inviter->id,
            'email' => Str::lower($email),
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        return [$invitation, $token];
    }

    public static function findPendingByToken(string $token): ?self
    {
        return self::query()->pending()->where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
