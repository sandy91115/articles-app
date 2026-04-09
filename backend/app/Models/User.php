<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'profile_photo_url',
        'email',
        'password',
        'phone',
        'role',
        'wallet_balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'wallet_balance' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(ArticleUnlock::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'author_id');
    }

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', UserRole::ADMIN);
    }

    public function scopeAuthors(Builder $query): Builder
    {
        return $query->where('role', UserRole::AUTHOR);
    }

    public function scopeReaders(Builder $query): Builder
    {
        return $query->where('role', UserRole::READER);
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $roleValues = array_map(
            fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : $role,
            $roles,
        );

        return in_array($this->role?->value ?? $this->role, $roleValues, true);
    }
}
