<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use OpenApi\Attributes as OA;

final class BroadcastAuthenticationController extends Controller
{
    #[OA\Post(
        path: '/broadcasting/auth',
        summary: 'Authorize a private realtime channel',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['socket_id', 'channel_name'],
            properties: [
                new OA\Property(property: 'socket_id', type: 'string', example: '123.456'),
                new OA\Property(property: 'channel_name', type: 'string', example: 'private-user.1'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Channel authorized', content: new OA\JsonContent(properties: [new OA\Property(property: 'auth', type: 'string')])),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Channel access denied'),
        ]
    )]
    public function __invoke(Request $request): mixed
    {
        return Broadcast::auth($request);
    }
}
