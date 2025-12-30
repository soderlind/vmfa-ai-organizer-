=== Virtual Media Folders AI Organizer ===
Contributors: starter
Donate link: https://developer.yoast.com/blog/real-world-implementation-of-wordpresss-plugin-dependencies-feature/
Tags: media, folders, ai, organization, virtual folders
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 0.1.1
Requires PHP: 8.3
Requires Plugins: virtual-media-folders
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered media organization add-on for Virtual Media Folders. Uses vision AI to analyze images and organize them into virtual folders.

== Description ==

Virtual Media Folders AI Organizer is a powerful add-on for the [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin that uses AI vision capabilities to automatically organize your WordPress media library.

Unlike traditional media organizers that rely only on metadata, this plugin actually **analyzes what's in your images** using state-of-the-art AI vision models to make intelligent folder suggestions.

= Key Features =

* **Vision-Based AI Analysis** – Analyzes actual image content (objects, scenes, people, colors) - not just filenames
* **Multiple AI Providers** – Choose from OpenAI, Azure OpenAI, Anthropic Claude, Google Gemini, Ollama, Grok, Exo, or a free heuristic fallback
* **Azure OpenAI Support** – Full support for enterprise Azure-hosted OpenAI deployments
* **Three Scan Modes**:
  * Organize Unassigned – Only process media not already in a folder
  * Re-analyze All – Re-analyze all media and update assignments
  * Reorganize All – Remove all folders and rebuild from scratch
* **Preview Mode** – Dry-run to see proposed changes before applying
* **Backup & Restore** – Automatic backup before reorganization with one-click restore
* **Background Processing** – Uses Action Scheduler for efficient chunked processing
* **Real-time Progress** – Live progress updates in the admin UI

= How It Works =

1. Select your preferred AI provider and configure your API key
2. Choose a scan mode (unassigned only, re-analyze, or full reorganize)
3. Optionally preview the results first with dry-run mode
4. Apply the suggestions to automatically organize your media

The AI analyzes each image in this priority order:

1. **Image Content** (primary) – What's actually visible in the image
2. **EXIF/Metadata** – Camera info, date taken, GPS location, keywords
3. **Text Metadata** – Title, alt text, caption, description
4. **Filename** – Used only as a last resort hint

= Supported AI Providers =

* **OpenAI** – GPT-4o, GPT-4o-mini, GPT-4-turbo
* **Azure OpenAI** – Enterprise Azure-hosted deployments
* **Anthropic** – Claude 3 Haiku, Sonnet, Opus, Claude 3.5 Sonnet
* **Google Gemini** – Gemini 1.5 Flash, Gemini 1.5 Pro, Gemini 2.0 Flash
* **Ollama** – Local LLMs including LLaVA for vision
* **Grok (xAI)** – Grok Beta, Grok 2
* **Exo** – Distributed local inference
* **Heuristic** – Free pattern-matching fallback (no API required)

= Requirements =

* WordPress 6.8 or higher
* PHP 8.3 or higher
* [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin (required)

== Installation ==

1. Install and activate the [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin first
2. Upload the `vmfa-ai-organizer` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Media → AI Organizer** to configure your AI provider
5. Enter your API key and select your preferred model
6. Start organizing your media!

= Configuration via Constants =

You can also configure the plugin using PHP constants in your `wp-config.php`:

`
// Provider Selection
define( 'VMFA_AI_PROVIDER', 'openai' );

// OpenAI / Azure OpenAI
define( 'VMFA_AI_OPENAI_TYPE', 'openai' ); // 'openai' or 'azure'
define( 'VMFA_AI_OPENAI_KEY', 'sk-...' );
define( 'VMFA_AI_OPENAI_MODEL', 'gpt-4o-mini' );

// Azure-specific
define( 'VMFA_AI_AZURE_ENDPOINT', 'https://your-resource.openai.azure.com' );
define( 'VMFA_AI_AZURE_API_VERSION', '2024-02-15-preview' );
`

== Frequently Asked Questions ==

= Does this plugin require an AI API key? =

For best results, yes. However, the plugin includes a free "Heuristic" provider that uses pattern matching on filenames and metadata – no API key required.

= Which AI provider should I use? =

For most users, we recommend GPT-4o-mini (OpenAI) for the best balance of speed, cost, and accuracy. For enterprise users, Azure OpenAI provides the same capabilities with enterprise compliance.

= Does it actually look at my images? =

Yes! Unlike metadata-only solutions, this plugin sends your images to vision-capable AI models that can understand what's actually in the image – people, objects, scenes, text, and more.

= Is my data safe? =

Images are sent to your chosen AI provider for analysis. No data is stored by this plugin beyond the folder assignments. Review your AI provider's privacy policy for their data handling practices.

= Can I use local AI models? =

Yes! Ollama and Exo providers support running AI models locally on your own hardware. This keeps all data on your server.

= What image formats are supported? =

JPEG, PNG, GIF, and WebP images up to 10MB. SVG and other formats are processed using metadata only.

= How long does scanning take? =

It depends on your media library size and AI provider speed. The plugin processes media in batches using background jobs, so you can continue working while it runs.

== Screenshots ==

1. Media Scanner interface with real-time progress
2. AI Provider configuration with multiple options
3. Scan results preview before applying
4. Backup and restore functionality

== Changelog ==

= 0.1.1 =
* Fixed JavaScript translations not loading for non-English locales
* Removed redundant dependency check (now handled by WordPress Requires Plugins header)
* Added i18n build scripts for translation workflow

= 0.1.0 =
* Initial release
* Vision-based AI analysis for image content
* Support for OpenAI, Azure OpenAI, Anthropic, Gemini, Ollama, Grok, Exo providers
* Heuristic fallback for metadata-based organization
* Three scan modes: unassigned, re-analyze, reorganize
* Preview mode with dry-run capability
* Backup and restore functionality
* Background processing with Action Scheduler
* Real-time progress tracking
* Settings tabs for Media Scanner and AI Provider configuration
* Full Azure OpenAI support with endpoint and API version configuration

== Upgrade Notice ==

= 0.1.0 =
Initial release of Virtual Media Folders AI Organizer.
