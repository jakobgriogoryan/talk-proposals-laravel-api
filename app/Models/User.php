<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * User model.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property UserRole|string $role
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Proposal> $proposals
 * @property-read Collection<int, Review> $reviews
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
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
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the proposals for the user.
     *
     * @return HasMany<Proposal>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * Get the reviews made by the user.
     *
     * @return HasMany<Review>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role === UserRole::ADMIN;
    }

    /**
     * Check if user is reviewer.
     */
    public function isReviewer(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role === UserRole::REVIEWER || $this->isAdmin();
    }

    /**
     * Check if user is speaker.
     */
    public function isSpeaker(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role === UserRole::SPEAKER || $this->isAdmin();
    }

    /**
     * Get the user's role as an enum.
     */
    public function getRoleEnum(): UserRole
    {
        return $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);
    }
}
