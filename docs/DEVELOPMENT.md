# Development Guide

This guide covers development setup, testing, and API documentation for the Virtual Media Folders AI Organizer plugin.

## Building Assets

```bash
# Install via Composer
composer require soderlind/vmfa-ai-organizer

# Install dependencies
npm install
composer install

# Build for production
npm run build

# Development with watch
npm run start
```

## Running Tests

```bash
# Run PHPUnit tests (13 tests)
./vendor/bin/phpunit

# Run JavaScript tests (12 tests)
npm test

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Code Standards

```bash
# Check coding standards
./vendor/bin/phpcs

# Auto-fix issues
./vendor/bin/phpcbf
```

## REST API Endpoints

All endpoints require `manage_options` capability.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/vmfa/v1/scan` | Start a new scan |
| GET | `/vmfa/v1/scan/status` | Get current scan status |
| POST | `/vmfa/v1/scan/cancel` | Cancel running scan |
| POST | `/vmfa/v1/scan/reset` | Reset scan progress |
| POST | `/vmfa/v1/analyze/{id}` | Analyze single attachment |
| GET | `/vmfa/v1/backup` | Get backup info |
| POST | `/vmfa/v1/restore` | Restore from backup |
| DELETE | `/vmfa/v1/backup` | Delete backup |
| GET | `/vmfa/v1/stats` | Get media statistics |

## Hooks

### Filters

```php
// Modify AI prompt
add_filter( 'vmfa_ai_prompt', function( $prompt, $metadata, $folders ) {
    return $prompt . "\nAdditional context: ...";
}, 10, 3 );

// Filter analysis result
add_filter( 'vmfa_analysis_result', function( $result, $attachment_id ) {
    return $result;
}, 10, 2 );

// Customize folder path building
add_filter( 'vmfa_folder_path', function( $path, $term ) {
    return $path;
}, 10, 2 );
```

### Actions

```php
// After media is assigned to folder
add_action( 'vmfa_media_assigned', function( $attachment_id, $folder_id, $result ) {
    // Log or trigger additional actions
}, 10, 3 );

// After scan completes
add_action( 'vmfa_scan_completed', function( $stats ) {
    // Send notification, etc.
}, 10, 1 );
```
