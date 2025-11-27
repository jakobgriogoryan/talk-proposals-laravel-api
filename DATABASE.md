# Database Documentation

Complete documentation of database migrations, seeders, and schema for the Talk Proposals system.

## 📋 Table of Contents

- [Database Schema](#database-schema)
- [Migrations](#migrations)
- [Seeders](#seeders)
- [Relationships](#relationships)
- [Indexes](#indexes)
- [Constraints](#constraints)

## 🗄 Database Schema

### Entity Relationship Diagram

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│    users    │         │  proposals   │         │    tags     │
├─────────────┤         ├──────────────┤         ├─────────────┤
│ id (PK)     │◄──┐     │ id (PK)      │         │ id (PK)     │
│ name        │   │     │ user_id (FK) │         │ name (UK)   │
│ email (UK)  │   │     │ title        │         │ timestamps  │
│ password    │   │     │ description  │         └─────────────┘
│ role        │   │     │ file_path    │                │
│ timestamps  │   │     │ status        │                │
└─────────────┘   │     │ timestamps   │                │
       │          │     └──────────────┘                │
       │          │            │                         │
       │          │            │                         │
       │          │     ┌──────┴──────┐                  │
       │          │     │            │                  │
       │          └──────┤            │                  │
       │                │            │                  │
       │         ┌──────▼──────┐     │                  │
       │         │   reviews   │     │                  │
       │         ├─────────────┤     │                  │
       │         │ id (PK)     │     │                  │
       │         │ proposal_id │     │                  │
       │         │ reviewer_id │     │                  │
       │         │ rating      │     │                  │
       │         │ comment     │     │                  │
       │         │ timestamps  │     │                  │
       │         │             │     │                  │
       │         │ UK(proposal,│     │                  │
       │         │    reviewer)│     │                  │
       │         └─────────────┘     │                  │
       │                             │                  │
       │                    ┌────────▼─────────┐       │
       │                    │ proposal_tag     │       │
       │                    ├──────────────────┤       │
       │                    │ proposal_id (FK) │◄──────┘
       │                    │ tag_id (FK)      │
       │                    │ PK(proposal, tag)│
       │                    └──────────────────┘
       │
       │
       └──────────────────────────────────────────────┐
                                                       │
                                            ┌──────────▼──────────┐
                                            │ personal_access_    │
                                            │      tokens         │
                                            ├─────────────────────┤
                                            │ id (PK)             │
                                            │ tokenable_type      │
                                            │ tokenable_id        │
                                            │ name                │
                                            │ token               │
                                            │ abilities           │
                                            │ last_used_at        │
                                            │ expires_at          │
                                            │ timestamps          │
                                            └─────────────────────┘
```

## 📝 Migrations

### Migration Order

Migrations are executed in the following order:

1. `0001_01_01_000000_create_users_table.php` - Base users table
2. `2024_01_01_000001_add_role_to_users_table.php` - Add role column
3. `2024_01_01_000002_create_proposals_table.php` - Proposals table
4. `2024_01_01_000003_create_tags_table.php` - Tags table
5. `2024_01_01_000004_create_proposal_tag_table.php` - Pivot table
6. `2024_01_01_000005_create_reviews_table.php` - Reviews table
7. `2025_11_26_223605_create_personal_access_tokens_table.php` - Sanctum tokens
8. `0001_01_01_000001_create_cache_table.php` - Cache table (optional)
9. `0001_01_01_000002_create_jobs_table.php` - Jobs table (optional)

### Detailed Migration Documentation

#### 1. Users Table

**File**: `0001_01_01_000000_create_users_table.php`

**Purpose**: Base user authentication table

**Columns**:
- `id` (bigint, unsigned, primary key, auto-increment)
- `name` (string, 255)
- `email` (string, 255, unique)
- `email_verified_at` (timestamp, nullable)
- `password` (string, 255)
- `remember_token` (string, 100, nullable)
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

**Indexes**:
- Primary key on `id`
- Unique index on `email`

#### 2. Add Role to Users

**File**: `2024_01_01_000001_add_role_to_users_table.php`

**Purpose**: Add role-based access control

**Columns Added**:
- `role` (enum: 'speaker', 'reviewer', 'admin', default: 'speaker')

**Notes**:
- Placed after `email` column
- Default role is 'speaker'
- Used for authorization throughout the application

#### 3. Proposals Table

**File**: `2024_01_01_000002_create_proposals_table.php`

**Purpose**: Store talk proposals

**Columns**:
- `id` (bigint, unsigned, primary key, auto-increment)
- `user_id` (bigint, unsigned, foreign key → users.id, cascade delete)
- `title` (string, 255)
- `description` (text)
- `file_path` (string, 255, nullable)
- `status` (enum: 'pending', 'approved', 'rejected', default: 'pending')
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

**Indexes**:
- Primary key on `id`
- Foreign key index on `user_id`

**Constraints**:
- Foreign key constraint: `user_id` references `users.id` ON DELETE CASCADE

**Notes**:
- `file_path` stores relative path to uploaded PDF files
- `status` defaults to 'pending' on creation
- Cascade delete ensures proposals are deleted when user is deleted

#### 4. Tags Table

**File**: `2024_01_01_000003_create_tags_table.php`

**Purpose**: Store proposal tags/categories

**Columns**:
- `id` (bigint, unsigned, primary key, auto-increment)
- `name` (string, 255, unique)
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

**Indexes**:
- Primary key on `id`
- Unique index on `name`

**Notes**:
- Tags are shared across all proposals
- Unique constraint prevents duplicate tag names

#### 5. Proposal-Tag Pivot Table

**File**: `2024_01_01_000004_create_proposal_tag_table.php`

**Purpose**: Many-to-many relationship between proposals and tags

**Columns**:
- `proposal_id` (bigint, unsigned, foreign key → proposals.id, cascade delete)
- `tag_id` (bigint, unsigned, foreign key → tags.id, cascade delete)

**Indexes**:
- Primary key on (`proposal_id`, `tag_id`)
- Foreign key index on `proposal_id`
- Foreign key index on `tag_id`

**Constraints**:
- Foreign key: `proposal_id` references `proposals.id` ON DELETE CASCADE
- Foreign key: `tag_id` references `tags.id` ON DELETE CASCADE
- Primary key constraint ensures unique proposal-tag pairs

**Notes**:
- Composite primary key prevents duplicate tag assignments
- Cascade delete removes relationships when proposal or tag is deleted

#### 6. Reviews Table

**File**: `2024_01_01_000005_create_reviews_table.php`

**Purpose**: Store reviewer ratings and comments

**Columns**:
- `id` (bigint, unsigned, primary key, auto-increment)
- `proposal_id` (bigint, unsigned, foreign key → proposals.id, cascade delete)
- `reviewer_id` (bigint, unsigned, foreign key → users.id, cascade delete)
- `rating` (integer) - Valid values: 1, 2, 3, 4, 5, or 10
- `comment` (text, nullable)
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

**Indexes**:
- Primary key on `id`
- Foreign key index on `proposal_id`
- Foreign key index on `reviewer_id`
- Unique index on (`proposal_id`, `reviewer_id`)

**Constraints**:
- Foreign key: `proposal_id` references `proposals.id` ON DELETE CASCADE
- Foreign key: `reviewer_id` references `users.id` ON DELETE CASCADE
- Unique constraint: One review per reviewer per proposal

**Notes**:
- Unique constraint prevents duplicate reviews
- Rating can be 1-5 (normal) or 10 (exceptional)
- Comment is optional
- Cascade delete removes reviews when proposal or reviewer is deleted

#### 7. Personal Access Tokens (Sanctum)

**File**: `2025_11_26_223605_create_personal_access_tokens_table.php`

**Purpose**: Laravel Sanctum token storage

**Columns**:
- `id` (bigint, unsigned, primary key, auto-increment)
- `tokenable_type` (string, 255) - Model class name
- `tokenable_id` (bigint, unsigned) - Model ID
- `name` (string, 255) - Token name
- `token` (string, 64, unique) - Hashed token
- `abilities` (text, nullable) - JSON array of abilities
- `last_used_at` (timestamp, nullable)
- `expires_at` (timestamp, nullable)
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

**Indexes**:
- Primary key on `id`
- Unique index on `token`
- Composite index on (`tokenable_type`, `tokenable_id`)

**Notes**:
- Used by Laravel Sanctum for API token authentication
- Polymorphic relationship to any model
- Not used in SPA mode (uses session cookies instead)

## 🌱 Seeders

### DatabaseSeeder

**File**: `database/seeders/DatabaseSeeder.php`

**Purpose**: Populate database with sample data for development and testing

#### Seeded Data

1. **Admin User** (1)
   - Email: `admin@example.com`
   - Name: `Admin User`
   - Role: `admin`
   - Password: `password`

2. **Reviewer Users** (3)
   - Randomly generated names and emails
   - Role: `reviewer`
   - Password: `password` (via factory)

3. **Speaker Users** (5)
   - Randomly generated names and emails
   - Role: `speaker`
   - Password: `password` (via factory)

4. **Tags** (10)
   - Randomly generated tag names
   - Examples: "Technology", "Health", "Business", etc.

5. **Proposals** (20)
   - Randomly assigned to speaker users
   - Random titles and descriptions
   - Random status (pending, approved, rejected)
   - Each proposal has 1-3 random tags attached

6. **Reviews** (15 proposals reviewed)
   - 15 out of 20 proposals have reviews
   - Each reviewed proposal has 1-3 reviews from different reviewers
   - Random ratings (1-5 or 10)
   - Random comments (some nullable)

#### Seeding Process

```php
// 1. Create admin user
$admin = User::factory()->create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'role' => UserRole::ADMIN->value,
]);

// 2. Create reviewers
$reviewers = User::factory()->count(3)->create([
    'role' => UserRole::REVIEWER->value,
]);

// 3. Create speakers
$speakers = User::factory()->count(5)->create([
    'role' => UserRole::SPEAKER->value,
]);

// 4. Create tags
$tags = Tag::factory()->count(10)->create();

// 5. Create proposals
$proposals = Proposal::factory()
    ->count(20)
    ->create([
        'user_id' => fn () => $speakers->random()->id,
    ]);

// 6. Attach tags to proposals
foreach ($proposals as $proposal) {
    $proposal->tags()->attach(
        $tags->random(rand(1, 3))->pluck('id')->toArray()
    );
}

// 7. Create reviews
foreach ($proposals->take(15) as $proposal) {
    $reviewerSample = $reviewers->random(
        rand(1, min(3, $reviewers->count()))
    );
    
    foreach ($reviewerSample as $reviewer) {
        Review::factory()->create([
            'proposal_id' => $proposal->id,
            'reviewer_id' => $reviewer->id,
        ]);
    }
}
```

### Running Seeders

```bash
# Seed database
php artisan db:seed

# Reset and reseed (WARNING: Drops all data)
php artisan migrate:fresh --seed

# Seed specific seeder
php artisan db:seed --class=DatabaseSeeder
```

## 🔗 Relationships

### Eloquent Relationships

#### User Model
```php
// One-to-Many: User has many Proposals
public function proposals(): HasMany

// One-to-Many: User has many Reviews (as reviewer)
public function reviews(): HasMany
```

#### Proposal Model
```php
// Many-to-One: Proposal belongs to User
public function user(): BelongsTo

// Many-to-Many: Proposal has many Tags
public function tags(): BelongsToMany

// One-to-Many: Proposal has many Reviews
public function reviews(): HasMany
```

#### Tag Model
```php
// Many-to-Many: Tag belongs to many Proposals
public function proposals(): BelongsToMany
```

#### Review Model
```php
// Many-to-One: Review belongs to Proposal
public function proposal(): BelongsTo

// Many-to-One: Review belongs to User (reviewer)
public function reviewer(): BelongsTo
```

## 📊 Indexes

### Primary Keys
- `users.id`
- `proposals.id`
- `tags.id`
- `reviews.id`
- `personal_access_tokens.id`
- `proposal_tag` (composite: `proposal_id`, `tag_id`)

### Foreign Keys
- `proposals.user_id` → `users.id`
- `reviews.proposal_id` → `proposals.id`
- `reviews.reviewer_id` → `users.id`
- `proposal_tag.proposal_id` → `proposals.id`
- `proposal_tag.tag_id` → `tags.id`

### Unique Indexes
- `users.email`
- `tags.name`
- `reviews` (composite: `proposal_id`, `reviewer_id`)
- `personal_access_tokens.token`

## 🔒 Constraints

### Foreign Key Constraints

All foreign keys use `ON DELETE CASCADE`:
- Deleting a user deletes their proposals and reviews
- Deleting a proposal deletes its reviews and tag relationships
- Deleting a tag removes it from all proposals
- Deleting a proposal removes all tag relationships

### Unique Constraints

1. **Users**: Email must be unique
2. **Tags**: Name must be unique
3. **Reviews**: One review per reviewer per proposal
4. **Proposal-Tag**: No duplicate tag assignments per proposal

### Check Constraints (Application Level)

1. **Proposal Status**: Must be 'pending', 'approved', or 'rejected'
2. **User Role**: Must be 'speaker', 'reviewer', or 'admin'
3. **Review Rating**: Must be 1, 2, 3, 4, 5, or 10

## 📈 Database Statistics

After seeding with default seeder:

- **Users**: 9 (1 admin, 3 reviewers, 5 speakers)
- **Tags**: 10
- **Proposals**: 20
- **Reviews**: ~15-45 (depending on random assignment)
- **Proposal-Tag Relationships**: ~20-60

## 🔄 Migration Commands

```bash
# Run pending migrations
php artisan migrate

# Run migrations with output
php artisan migrate --verbose

# Rollback last migration
php artisan migrate:rollback

# Rollback all migrations
php artisan migrate:reset

# Rollback and re-run
php artisan migrate:refresh

# Drop all tables and re-run
php artisan migrate:fresh

# Show migration status
php artisan migrate:status
```

## 🧪 Testing with Database

### Refresh Database for Tests

```bash
# Reset database before tests
php artisan migrate:fresh --seed
```

### Factories

Model factories are available for testing:

- `UserFactory` - Generate test users
- `ProposalFactory` - Generate test proposals
- `TagFactory` - Generate test tags
- `ReviewFactory` - Generate test reviews

### Example Usage

```php
// Create a test user
$user = User::factory()->create(['role' => 'speaker']);

// Create a proposal for the user
$proposal = Proposal::factory()->create(['user_id' => $user->id]);

// Create a review
$review = Review::factory()->create([
    'proposal_id' => $proposal->id,
    'reviewer_id' => User::factory()->create(['role' => 'reviewer'])->id,
]);
```

## 📝 Notes

1. **Cascade Deletes**: All foreign keys use CASCADE to maintain referential integrity
2. **Soft Deletes**: Not implemented (can be added if needed)
3. **Timestamps**: All tables include `created_at` and `updated_at`
4. **File Storage**: Proposal files are stored in `storage/app/private/proposals/`
5. **Indexes**: Optimized for common query patterns (user_id, proposal_id, etc.)

---

For more information, see [../README.md](../README.md) and [../PROJECT_STRUCTURE.md](../PROJECT_STRUCTURE.md).

