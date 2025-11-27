<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Proposal resource for API responses.
 *
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate average rating if reviews are loaded or if calculated from query
        $avgRating = null;
        $reviewsCount = 0;

        // Check if values were calculated from the query (for topRated endpoint)
        if (isset($this->avg_rating) && isset($this->reviews_count)) {
            $avgRating = round((float) $this->avg_rating, 1);
            $reviewsCount = (int) $this->reviews_count;
        } elseif (isset($this->reviews_avg_rating)) {
            $avgRating = round((float) $this->reviews_avg_rating, 1);
            $reviewsCount = (int) ($this->reviews_count ?? 0);
        } elseif ($this->relationLoaded('reviews') && $this->reviews->count() > 0) {
            $avgRating = round((float) $this->reviews->avg('rating'), 1);
            $reviewsCount = $this->reviews->count();
        }

        $status = $this->status;
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'file_path' => $this->file_path ? '/proposals/'.$this->id.'/download' : null,
            'status' => $status,
            'user' => new UserResource($this->whenLoaded('user')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'average_rating' => $avgRating,
            'reviews_count' => $reviewsCount,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
