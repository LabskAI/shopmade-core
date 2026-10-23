<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\CoreTools;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'password', 'role', 'must_set_password', 'password_invite_version'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'must_set_password' => 'boolean',
            'password_invite_version' => 'integer',
            'deactivated_at' => 'datetime',
        ];
    }

    public function mustSetPassword(): bool
    {
        return $this->must_set_password === true;
    }

    public function issuePasswordInvite(): int
    {
        $this->must_set_password = true;
        $this->password_invite_version = ((int) $this->password_invite_version) + 1;
        $this->save();

        return $this->password_invite_version;
    }

    public function matchesPasswordInvite(int $version): bool
    {
        return $this->mustSetPassword()
            && $this->password_invite_version > 0
            && $version === $this->password_invite_version;
    }

    public function consumePasswordInvite(int $version, string $plainPassword): bool
    {
        $updated = static::query()
            ->whereKey($this->id)
            ->where('must_set_password', true)
            ->where('password_invite_version', $version)
            ->update([
                'password' => Hash::make($plainPassword),
                'must_set_password' => false,
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /**
     * A deactivated user cannot sign in, loses every ability and is signed out on their next request.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isUser(): bool
    {
        return $this->role === UserRole::User;
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function canViewCoreTool(string $slug): bool
    {
        return CoreTools::allows($this, $slug, 'view');
    }

    public function canManageCoreTool(string $slug): bool
    {
        return CoreTools::allows($this, $slug, 'manage');
    }
}
