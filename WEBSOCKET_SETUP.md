# WebSocket Real-Time Updates Setup Guide

This project uses Laravel Broadcasting with Pusher for real-time WebSocket connections. Follow these steps to set up real-time notifications.

## Prerequisites

1. A Pusher account (free tier available at https://pusher.com)
2. Laravel 11 with Broadcasting enabled
3. Vue.js frontend with Laravel Echo

## Backend Setup (Laravel)

### 1. Install Pusher PHP SDK (Optional - for production)

```bash
cd talk-proposals-api
composer require pusher/pusher-php-server
```

### 2. Configure Broadcasting

Add these environment variables to your `.env` file:

```env
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1
```

**Note**: In Laravel 12, use `BROADCAST_CONNECTION` instead of `BROADCAST_DRIVER`.

### 3. Configure Queue (Required for Broadcasting)

Broadcasting events are queued by default. Configure your queue driver:

```env
QUEUE_CONNECTION=database
```

Or use Redis for better performance:

```env
QUEUE_CONNECTION=redis
```

### 4. Run Migrations for Queue

If using database queue:

```bash
php artisan queue:table
php artisan migrate
```

### 5. Start Queue Worker

```bash
php artisan queue:work
```

Or use the dev script which includes queue worker:

```bash
composer run dev
```

## Frontend Setup (Vue.js)

### 1. Environment Variables

Add these to your `.env` file in the frontend directory:

```env
VITE_PUSHER_APP_KEY=your-app-key
VITE_PUSHER_APP_CLUSTER=mt1
```

### 2. Packages Installed

The following packages are already installed:
- `laravel-echo`
- `pusher-js`

### 3. Configuration

Echo is configured in `src/config/echo.js` and automatically initialized in `src/main.js`.

## How It Works

### Events Broadcasted

1. **ProposalSubmitted** - When a new proposal is created
   - Broadcasts to: `proposals` channel, `user.{userId}` channel
   - Notifies: Admins, Reviewers, and the proposal owner

2. **ProposalReviewed** - When a review is added to a proposal
   - Broadcasts to: `proposals` channel, `proposal.{proposalId}` channel, `user.{userId}` channel
   - Notifies: Admins, Reviewers, and the proposal owner

3. **ProposalStatusChanged** - When admin changes proposal status
   - Broadcasts to: `proposals` channel, `proposal.{proposalId}` channel, `user.{userId}` channel
   - Notifies: Admins, Reviewers, and the proposal owner

### Channels

- **`proposals`** - All authenticated users (admins and reviewers)
- **`proposal.{proposalId}`** - Users who can view the specific proposal
- **`user.{userId}`** - User-specific notifications

### Frontend Integration

The `useRealtime()` composable automatically:
- Initializes when user is authenticated
- Listens to appropriate channels based on user role
- Shows toast notifications for events
- Dispatches custom events for components to refresh data

Components automatically refresh when events are received:
- **Proposals.vue** - Refreshes proposal list
- **AdminProposals.vue** - Refreshes admin dashboard
- **ReviewProposals.vue** - Refreshes reviewer dashboard
- **ProposalDetail.vue** - Updates proposal status and reviews in real-time

## Testing

1. Start the Laravel queue worker: `php artisan queue:work`
2. Open the application in multiple browser tabs/windows
3. Submit a proposal in one tab
4. Watch for real-time notifications in other tabs
5. Change proposal status as admin
6. Watch for real-time updates in speaker's view

## Troubleshooting

### Events Not Broadcasting

1. Check queue worker is running: `php artisan queue:work`
2. Check `.env` has correct Pusher credentials
3. Check browser console for WebSocket connection errors
4. Verify `BROADCAST_CONNECTION=pusher` in `.env` (Laravel 12) or `BROADCAST_DRIVER=pusher` (Laravel 11)

### Authentication Errors

1. Ensure `/api/broadcasting/auth` route is accessible
2. Check that user is authenticated (Sanctum session)
3. Verify channel authorization in `routes/channels.php`

### Connection Issues

1. Check Pusher dashboard for connection logs
2. Verify `VITE_PUSHER_APP_KEY` and `VITE_PUSHER_APP_CLUSTER` in frontend `.env`
3. Check browser console for WebSocket errors

## Alternative: Laravel WebSockets (Development)

For local development without Pusher, you can use Laravel WebSockets:

```bash
composer require beyondcode/laravel-websockets
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="migrations"
php artisan migrate
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="config"
```

Then update `.env`:
```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=local
PUSHER_APP_KEY=local
PUSHER_APP_SECRET=local
PUSHER_APP_CLUSTER=mt1
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
```

And run:
```bash
php artisan websockets:serve
```

Update frontend Echo config to use `wsHost` and `wsPort` for local development.

