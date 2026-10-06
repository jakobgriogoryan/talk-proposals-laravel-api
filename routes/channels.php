<?php

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('proposals', function (User $user): bool {
    return $user->isReviewer();
});

Broadcast::channel('proposals.{proposal}', function (User $user, Proposal $proposal): bool {
    return $user->can('view', $proposal);
});

Broadcast::channel('user.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === (int) $userId;
});
