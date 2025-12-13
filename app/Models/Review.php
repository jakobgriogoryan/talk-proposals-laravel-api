<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Review model.
 *
 * @property int $id
 * @property int $proposal_id
 * @property int $reviewer_id
 * @property int $rating
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Proposal $proposal
 * @property-read User $reviewer
 */
class Review extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'proposal_id',
        'reviewer_id',
        'rating',
        'comment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Get the proposal that the review belongs to.
     *
     * @return BelongsTo<Proposal, Review>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * Get the reviewer (user) that made the review.
     *
     * @return BelongsTo<User, Review>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Scope a query to filter by proposal.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeByProposal(Builder $query, int $proposalId): Builder
    {
        return $query->where('proposal_id', $proposalId);
    }

    /**
     * Scope a query to filter by reviewer.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeByReviewer(Builder $query, int $reviewerId): Builder
    {
        return $query->where('reviewer_id', $reviewerId);
    }

    /**
     * Scope a query to filter by rating.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeByRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    /**
     * Check if rating is valid.
     */
    public static function isValidRating(int $rating): bool
    {
        return in_array($rating, ReviewRating::values(), true);
    }
}
