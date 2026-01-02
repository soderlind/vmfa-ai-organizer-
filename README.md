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



<a href="https://www.youtube.com/watch?v=Rn7otDZ1RxM"><img src="assets/vmfa-order-in-chaos.png" alt="Virtual Media Folders AI Organizer - Order in Chaos" style="max-width:100%;height:auto;"/></a>

<p align="center"><em>Watch: <a href="https://www.youtube.com/watch?v=Rn7otDZ1RxM">See how Virtual Media Folders AI Organizer brings order to your media library chaos.</a></em></p>



## Documentation

- **[AI Provider Guide](docs/AI-PROVIDERS.md)** - Detailed guide on choosing and configuring AI providers.

## Requirements

- WordPress 6.8+
- PHP 8.3+
- [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin

## Installation

1. Download [`vmfa-ai-organizer.zip`](https://github.com/soderlind/vmfa-ai-organizer/releases/latest/download/vmfa-ai-organizer.zip)
2. Upload via  `Plugins → Add New → Upload Plugin`
3. Activate via `WordPress Admin → Plugins`

Plugin [updates are handled automatically](https://github.com/soderlind/wordpress-plugin-github-updater#readme) via GitHub. No need to manually download and install updates.

## Configuration

Navigate to **Media → AI Organizer** to configure:

### Media Scanner Tab

Use this tab to scan and organize your media library. See scan modes and preview options.

### Settings Tab

- **Max Folder Depth**: Limit folder hierarchy depth (1-5)
- **Allow New Folders**: Enable AI to suggest new folder structures
- **Batch Size**: Number of items to process per batch

### AI Provider Tab

Configure your AI provider for image analysis. See the **[AI Provider Guide](docs/AI-PROVIDERS.md)** for detailed setup instructions, model recommendations, and cost comparison.

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
define( 'VMFA_AI_EXO_ENDPOINT', 'http://localhost:52415' );
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

For detailed information about supported AI providers and vision-capable models, see the **[AI Provider Guide](docs/AI-PROVIDERS.md)**.

## Development

For development setup, testing, REST API endpoints, and hooks documentation, see the **[Development Guide](docs/DEVELOPMENT.md)**.

## License

Virtual Media Folders AI Organizer is free software licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Copyright 2025 Per Soderlind
