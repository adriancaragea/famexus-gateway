<?php

namespace App\Enums;

/**
 * A member's role in their family and the permissions it grants. Services
 * receive the permissions in the signed token and enforce them themselves.
 * Custom roles can later move this map into tables without changing the token.
 */
enum Role: string
{
    case Owner = 'owner';
    case Adult = 'adult';
    case Child = 'child';

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => [
                'family.manage',
                'finance.view', 'finance.edit',
                'todo.view', 'todo.edit',
                'calendar.view', 'calendar.edit',
            ],
            self::Adult => [
                'finance.view', 'finance.edit',
                'todo.view', 'todo.edit',
                'calendar.view', 'calendar.edit',
            ],
            self::Child => [
                'todo.view', 'todo.edit',
                'calendar.view',
            ],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
