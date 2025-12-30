# Virtual Media Folders AI Organizer

AI-powered media organization add-on for the Virtual Media Folders plugin.

## Requirements

- WordPress 6.8+
- PHP 8.3+
- [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin

## Features

- **AI-Powered Analysis**: Uses AI to analyze media metadata (filename, alt text, caption, EXIF data) and suggest appropriate folder assignments
- **Multiple AI Providers**: Support for OpenAI, Anthropic, Gemini, Ollama, Grok, Exo, and a heuristic fallback
- **Scan Modes**:
  - **Organize Unassigned**: Only process media not already in a folder
  - **Re-analyze All**: Re-analyze all media and update assignments
  - **Reorganize All**: Remove all folders and rebuild from scratch
- **Preview Mode**: Dry-run to see proposed changes before applying
- **Backup & Restore**: Automatic backup before reorganization with one-click restore
- **Chunked Processing**: Uses Action Scheduler for efficient background processing
- **Progress Tracking**: Real-time progress updates in the admin UI

## Installation

1. Ensure Virtual Media Folders is installed and activated
2. Upload the plugin to `/wp-content/plugins/vmfa-ai-organizer`
3. Run `composer install` to install dependencies
4. Run `npm install && npm run build` to build assets
5. Activate the plugin

## Configuration

### Settings

Navigate to **Media > Virtual Media Folders > AI Organizer** to configure:

- **AI Provider**: Select which AI service to use
- **API Keys**: Enter API keys for your chosen provider
- **Model**: Specify which model to use
- **Max Folder Depth**: Limit folder hierarchy depth (1-5)
- **Allow New Folders**: Enable AI to suggest new folder structures
- **Batch Size**: Number of items to process per batch

### Configuration Priority

Settings are resolved in this order:
1. PHP Constants (e.g., `VMFA_OPENAI_KEY`)
2. Environment Variables (e.g., `VMFA_OPENAI_KEY`)
3. Database Options
4. Default Values

### Environment Variables / Constants

```php
// API Keys
define( 'VMFA_OPENAI_KEY', 'sk-...' );
define( 'VMFA_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'VMFA_GEMINI_KEY', '...' );
define( 'VMFA_GROK_KEY', '...' );

// Local AI Hosts
define( 'VMFA_OLLAMA_HOST', 'http://localhost:11434' );
define( 'VMFA_EXO_HOST', 'http://localhost:52415' );

// Models
define( 'VMFA_OPENAI_MODEL', 'gpt-4o-mini' );
define( 'VMFA_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514' );

// General Settings
define( 'VMFA_AI_PROVIDER', 'openai' );
define( 'VMFA_MAX_FOLDER_DEPTH', 3 );
define( 'VMFA_ALLOW_NEW_FOLDERS', true );
define( 'VMFA_BATCH_SIZE', 20 );
```

## Development

### Building Assets

```bash
# Install dependencies
npm install
composer install

# Build for production
npm run build

# Development with watch
npm run start
```

### Running Tests

```bash
# Install dev dependencies
composer install

# Run PHPUnit tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Code Standards

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

### Start Scan Request

```json
{
  "mode": "organize_unassigned",
  "dry_run": true
}
```

### Scan Status Response

```json
{
  "status": "running",
  "mode": "organize_unassigned",
  "dry_run": false,
  "total": 100,
  "processed": 45,
  "percentage": 45,
  "applied": 40,
  "failed": 5,
  "results": [],
  "started_at": 1704067200,
  "completed_at": null
}
```

## Hooks

### Filters

```php
// Modify AI prompt
add_filter( 'vmfa_ai_prompt', function( $prompt, $metadata, $folders ) {
    return $prompt . "\nAdditional context: ...";
}, 10, 3 );

// Filter analysis result
add_filter( 'vmfa_analysis_result', function( $result, $attachment_id ) {
    // Modify or override result
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

## License

GPL v2 or later
