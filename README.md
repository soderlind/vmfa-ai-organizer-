# Virtual Media Folders AI Organizer

AI-powered media organization add-on for the [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin. Uses vision-capable AI models to analyze actual image content and automatically organize your media library into virtual folders.

## Features

- **Vision-Based AI Analysis**: Analyzes actual image content (objects, scenes, colors) - not just metadata
- **Multiple AI Providers**: OpenAI/Azure, Anthropic Claude, Google Gemini, Ollama, Grok, Exo
- **Azure OpenAI Support**: Full support for Azure-hosted OpenAI deployments
- **Automatic File Handling**: Documents go to "Documents", videos go to "Videos" - no AI needed
- **Three Scan Modes**:
  - **Organize Unassigned**: Only process media not already in a folder
  - **Re-analyze All**: Re-analyze all media and update assignments
  - **Reorganize All**: Remove all folders and rebuild from scratch
- **Preview Mode**: Dry-run to see proposed changes before applying
- **Backup & Restore**: Automatic backup before reorganization with one-click restore
- **Background Processing**: Uses Action Scheduler for efficient chunked processing
- **Real-time Progress**: Live progress updates in the admin UI

<video src="https://www.youtube.com/watch?v=Rn7otDZ1RxM" controls></video>

## Documentation

- **[AI Provider Guide](docs/AI-PROVIDERS.md)** - Detailed guide on choosing and configuring AI providers, recommended models, and cost comparison

## Requirements

- WordPress 6.8+
- PHP 8.3+
- [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin

## Installation

1. Ensure Virtual Media Folders is installed and activated
2. Upload the plugin to `/wp-content/plugins/vmfa-ai-organizer`
3. Run `composer install` to install dependencies
4. Run `npm install && npm run build` to build assets
5. Activate the plugin

## Configuration

Navigate to **Media → AI Organizer** to configure:

### AI Provider Tab

- **AI Provider**: Select which AI service to use
- **OpenAI Type**: Choose between OpenAI or Azure OpenAI
- **API Keys**: Enter API keys for your chosen provider
- **Model/Deployment**: Specify which model (or Azure deployment) to use
- **Azure Endpoint**: Your Azure OpenAI resource endpoint (for Azure)

### Organization Settings

- **Max Folder Depth**: Limit folder hierarchy depth (1-5)
- **Allow New Folders**: Enable AI to suggest new folder structures
- **Batch Size**: Number of items to process per batch

### Configuration Priority

Settings are resolved in this order:
1. PHP Constants (e.g., `VMFA_AI_OPENAI_KEY`)
2. Environment Variables (e.g., `VMFA_AI_OPENAI_KEY`)
3. Database Options (Settings page)
4. Default Values

### Environment Variables / Constants

```php
// Provider Selection
define( 'VMFA_AI_PROVIDER', 'openai' );

// OpenAI / Azure OpenAI
define( 'VMFA_AI_OPENAI_TYPE', 'openai' ); // 'openai' or 'azure'
define( 'VMFA_AI_OPENAI_KEY', 'sk-...' );
define( 'VMFA_AI_OPENAI_MODEL', 'gpt-4o-mini' );
define( 'VMFA_AI_AZURE_ENDPOINT', 'https://your-resource.openai.azure.com' );
define( 'VMFA_AI_AZURE_API_VERSION', '2024-02-15-preview' );

// Anthropic Claude
define( 'VMFA_AI_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'VMFA_AI_ANTHROPIC_MODEL', 'claude-3-haiku-20240307' );

// Google Gemini
define( 'VMFA_AI_GEMINI_KEY', '...' );
define( 'VMFA_AI_GEMINI_MODEL', 'gemini-1.5-flash' );

// Grok (xAI)
define( 'VMFA_AI_GROK_KEY', '...' );
define( 'VMFA_AI_GROK_MODEL', 'grok-beta' );

// Ollama (Local)
define( 'VMFA_AI_OLLAMA_URL', 'http://localhost:11434' );
define( 'VMFA_AI_OLLAMA_MODEL', 'llama3.2' );

// Exo (Distributed Local)
define( 'VMFA_AI_EXO_URL', 'http://localhost:52415' );
define( 'VMFA_AI_EXO_MODEL', 'llama-3.2-3b' );

// Organization Settings
define( 'VMFA_AI_MAX_FOLDER_DEPTH', 3 );
define( 'VMFA_AI_ALLOW_NEW_FOLDERS', true );
define( 'VMFA_AI_BATCH_SIZE', 20 );
```

## Vision API Support

The plugin uses vision-capable AI models to analyze actual image content. When processing images, the AI receives:

1. **Image Content** (primary): The actual visual content of the image
2. **EXIF/Metadata**: Camera info, date taken, GPS location, keywords
3. **Text metadata**: Title, alt text, caption, description
4. **Filename**: As a last resort hint

Supported image formats: JPEG, PNG, GIF, WebP (max 10MB per image).

### Vision-Capable Models

| Provider | Models with Vision |
|----------|-------------------|
| OpenAI | GPT-4o, GPT-4o-mini, GPT-4-turbo |
| Azure OpenAI | GPT-4o, GPT-4-turbo deployments |
| Anthropic | Claude 3 (Haiku, Sonnet, Opus), Claude 3.5 Sonnet |
| Gemini | Gemini 1.5 Flash, Gemini 1.5 Pro, Gemini 2.0 Flash |
| Ollama | LLaVA, BakLLaVA (vision-capable models) |

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
# Run PHPUnit tests (13 tests)
./vendor/bin/phpunit

# Run JavaScript tests (12 tests)
npm test

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

## License

GPL v2 or later
