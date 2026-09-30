# Laravel Scout + Algolia Full-Text Search Setup

This guide explains how to set up and use Laravel Scout with Algolia for advanced full-text search capabilities in the Talk Proposals API.

## 📋 Prerequisites

- Laravel Scout is already installed (`laravel/scout`)
- Algolia PHP client is already installed (`algolia/algoliasearch-client-php`)

## 🔧 Configuration

### 1. Get Algolia Credentials

1. Sign up for a free Algolia account at https://www.algolia.com/
2. Create a new application
3. Get your **Application ID** and **Admin API Key** from the Algolia dashboard

### 2. Configure Environment Variables

Add the following to your `.env` file:

```env
SCOUT_DRIVER=algolia
ALGOLIA_APP_ID=your_application_id_here
ALGOLIA_SECRET=your_admin_api_key_here
SCOUT_PREFIX=talk_proposals_
SCOUT_QUEUE=false
```

**Note:** 
- `SCOUT_QUEUE=false` means proposals will be indexed synchronously. Set to `true` if you want async indexing (requires queue worker).
- `SCOUT_PREFIX` is optional but recommended to avoid conflicts if you have multiple applications.

### 3. Clear Configuration Cache

```bash
php artisan config:clear
```

## 🚀 Import Existing Proposals

After configuring Algolia, import all existing proposals to the search index:

```bash
php artisan scout:import-proposals
```

This command will:
- Load all proposals with their relationships (user, tags)
- Index them in Algolia
- Show progress as it imports

**Options:**
- `--chunk=500` - Number of proposals to process per chunk (default: 500)

## 🔍 How It Works

### Searchable Fields

Proposals are indexed with the following searchable fields:
- **title** - Proposal title (primary search field)
- **description** - Full proposal description
- **user_name** - Author's name
- **tags** - Array of tag names

### Faceted Filters

The following fields are available for filtering:
- **status** - Filter by proposal status (pending, approved, rejected)
- **user_id** - Filter by author
- **tag_ids** - Filter by tag IDs

### Custom Ranking

Results are ranked by:
1. Average rating (descending)
2. Reviews count (descending)
3. Creation date (descending)

## 📊 Search Behavior

### Automatic Fallback

The system automatically falls back to database search if:
- Scout driver is set to `collection` or `null`
- No search query is provided
- Algolia is not configured

### Hybrid Search

When a search query is provided and Algolia is configured:
1. **Full-text search** is performed using Algolia (searches title, description, tags, author)
2. **Filters** (status, tags, user_id) are applied in Algolia
3. Results are ranked by relevance and custom ranking
4. Database is used to load full relationships

When no search query is provided:
- Standard database queries are used
- Faster for simple filtering without search

## 🎯 Usage Examples

### Basic Search

```bash
GET /api/proposals?search=Laravel
```

Searches for "Laravel" across title, description, tags, and author name.

### Search with Filters

```bash
GET /api/proposals?search=framework&status=approved&tags=1,2
```

Searches for "framework" in approved proposals with specific tags.

### Filter Only (No Search)

```bash
GET /api/proposals?status=pending&tags=3
```

Uses database queries (no Algolia) since no search query is provided.

## 🔄 Keeping Index Updated

Proposals are automatically indexed when:
- A new proposal is created
- A proposal is updated
- Tags are added/removed from a proposal

**Note:** If you're using queues (`SCOUT_QUEUE=true`), make sure your queue worker is running:

```bash
php artisan queue:work
```

## 🛠️ Manual Indexing

To manually index a proposal:

```php
$proposal = Proposal::find(1);
$proposal->searchable(); // Index it
$proposal->unsearchable(); // Remove from index
```

To re-index all proposals:

```bash
php artisan scout:import-proposals
```

## 📝 Algolia Dashboard

You can monitor and configure your search index in the Algolia dashboard:
- View indexed records
- Configure search settings
- Monitor search analytics
- Adjust ranking and relevance

## 🔒 Security

- **Admin API Key**: Keep this secret! Never expose it in frontend code.
- **Search-Only API Key**: For frontend direct search (if needed), create a search-only API key in Algolia dashboard.

## 🐛 Troubleshooting

### Proposals not appearing in search

1. Check if Algolia is configured: `php artisan tinker` → `config('scout.driver')`
2. Import proposals: `php artisan scout:import-proposals`
3. Check Algolia dashboard for indexed records
4. Verify API keys are correct

### Search returns no results

1. Check if proposals are indexed in Algolia dashboard
2. Verify search query is being sent
3. Check Algolia logs for errors
4. Try a simple search query first

### Slow search performance

1. Check Algolia dashboard for index size
2. Consider using queues for indexing: `SCOUT_QUEUE=true`
3. Optimize searchable array in `Proposal::toSearchableArray()`

## 📚 Additional Resources

- [Laravel Scout Documentation](https://laravel.com/docs/scout)
- [Algolia Documentation](https://www.algolia.com/doc/)
- [Algolia PHP Client](https://github.com/algolia/algoliasearch-client-php)

## ✅ Verification

After setup, verify everything works:

1. Import proposals: `php artisan scout:import-proposals`
2. Test search: `GET /api/proposals?search=test`
3. Check Algolia dashboard for indexed records
4. Verify search results are relevant and fast

