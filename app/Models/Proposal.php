<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proposal model.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $description
 * @property string|null $file_path
 * @property ProposalStatus|string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Review> $reviews
 * @property-read float|null $avg_rating
 * @property-read int|null $reviews_count
 * @property-read float|null $reviews_avg_rating
 */
class Proposal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'file_path',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
        ];
    }

    /**
     * Get the user that owns the proposal.
     *
     * @return BelongsTo<User, Proposal>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the proposal.
     *
     * @return BelongsToMany<Tag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the reviews for the proposal.
     *
     * @return HasMany<Review>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Scope a query to only include approved proposals.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::APPROVED->value);
    }

    /**
     * Scope a query to only include pending proposals.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::PENDING->value);
    }

    /**
     * Scope a query to only include rejected proposals.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::REJECTED->value);
    }

    /**
     * Scope a query to filter by status.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function scopeByStatus(Builder $query, ProposalStatus|string $status): Builder
    {
        $statusValue = $status instanceof ProposalStatus ? $status->value : $status;

        return $query->where('status', $statusValue);
    }

    /**
     * Scope a query to search by title.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function scopeSearchByTitle(Builder $query, string $search): Builder
    {
        return $query->where('title', 'like', '%'.$search.'%');
    }

    /**
     * Scope a query to filter by tags.
     *
     * @param  Builder<Proposal>  $query
     * @param  array<int>  $tagIds
     * @return Builder<Proposal>
     */
    public function scopeByTags(Builder $query, array $tagIds): Builder
    {
        return $query->whereHas('tags', function ($q) use ($tagIds): void {
            $q->whereIn('tags.id', $tagIds);
        });
    }

    /**
     * Scope a query to filter by user.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if proposal is approved.
     */
    public function isApproved(): bool
    {
        $status = $this->status instanceof ProposalStatus ? $this->status : ProposalStatus::from($this->status);

        return $status === ProposalStatus::APPROVED;
    }

    /**
     * Check if proposal is pending.
     */
    public function isPending(): bool
    {
        $status = $this->status instanceof ProposalStatus ? $this->status : ProposalStatus::from($this->status);

        return $status === ProposalStatus::PENDING;
    }

    /**
     * Check if proposal is rejected.
     */
    public function isRejected(): bool
    {
        $status = $this->status instanceof ProposalStatus ? $this->status : ProposalStatus::from($this->status);

        return $status === ProposalStatus::REJECTED;
    }

    /**
     * Get the proposal's status as an enum.
     */
    public function getStatusEnum(): ProposalStatus
    {
        return $this->status instanceof ProposalStatus ? $this->status : ProposalStatus::from($this->status);
    }

    /**
     * Calculate the average rating from reviews.
     */
    public function getAverageRating(): ?float
    {
        if (! $this->relationLoaded('reviews') || $this->reviews->isEmpty()) {
            return null;
        }

        return round((float) $this->reviews->avg('rating'), 1);
    }

    /**
     * Get the reviews count.
     */
    public function getReviewsCount(): int
    {
        if (! $this->relationLoaded('reviews')) {
            return $this->reviews()->count();
        }

        return $this->reviews->count();
    }
}
