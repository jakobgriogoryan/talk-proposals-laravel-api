<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Tag model.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Proposal> $proposals
 */
class Tag extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the proposals that have this tag.
     *
     * @return BelongsToMany<Proposal>
     */
    public function proposals(): BelongsToMany
    {
        return $this->belongsToMany(Proposal::class);
    }

    /**
     * Scope a query to search by name.
     *
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeSearchByName(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }
}
