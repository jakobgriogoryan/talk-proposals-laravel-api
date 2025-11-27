<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Proposal;
use App\Models\User;

/**
 * Proposal policy.
 */
class ProposalPolicy
{
    /**
     * Determine if the user can view any proposals.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the proposal.
     */
    public function view(User $user, Proposal $proposal): bool
    {
        // Speakers can only view their own proposals (unless admin)
        if ($user->isSpeaker() && ! $user->isAdmin()) {
            return $user->id === $proposal->user_id;
        }

        return true;
    }

    /**
     * Determine if the user can create proposals.
     */
    public function create(User $user): bool
    {
        return $user->isSpeaker();
    }

    /**
     * Determine if the user can update the proposal.
     */
    public function update(User $user, Proposal $proposal): bool
    {
        return $user->id === $proposal->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can delete the proposal.
     */
    public function delete(User $user, Proposal $proposal): bool
    {
        return $user->id === $proposal->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can update the proposal status.
     */
    public function updateStatus(User $user, Proposal $proposal): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can download the proposal file.
     */
    public function downloadFile(User $user, Proposal $proposal): bool
    {
        // Speakers can only download their own proposals (unless admin)
        if ($user->isSpeaker() && ! $user->isAdmin()) {
            return $user->id === $proposal->user_id;
        }

        // Reviewers and admins can download any proposal
        return $user->isReviewer() || $user->isAdmin();
    }
}
