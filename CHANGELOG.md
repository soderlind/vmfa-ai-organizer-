# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [0.1.9] - 2026-01-02

### Fixed

- **Documents/Videos Folder Creation**: Documents and Videos folders are now always created when needed during "Reorganize All", regardless of "Allow New Folders" setting (these are type-based folders, not AI-suggested)


## [0.1.8] - 2026-01-02

### Added

- **Hierarchy Inversion Prevention**: AI now detects and prevents creating inverted folder hierarchies
  - If `Events/Outdoor` exists, AI will not create `Outdoor/Events`
  - Automatic remapping to existing paths when conflicts are detected
  - Enhanced system prompt with explicit anti-inversion rules and examples
- **Folder Name Caching**: New `get_folder_name_map()` method with session caching for efficient lookups
- **Flexible Folder Matching**: `find_folder_by_name()` method to locate folders anywhere in hierarchy
  - Prefers shallowest depth (top-level first)
  - Enables Documents/Videos folders to be found regardless of position

### Fixed

- **Document/Video Assignment**: Documents and videos now correctly assigned even when their target folders exist as subfolders (e.g., `Media/Documents` instead of just `Documents`)


## [0.1.7] - 2026-01-02

### Added

- **Ollama Dynamic Model List**: Model dropdown now populated dynamically from running Ollama server
  - "Refresh Models" button to fetch available models via `/api/tags`
  - New `OllamaController` REST endpoint (`POST /vmfa/v1/ollama-models`)
- **Ollama Configurable Timeout**: New setting for Ollama request timeout (10-600 seconds, default 120)
  - Useful for larger models or slower hardware
  - Configurable via settings UI or `VMFA_AI_OLLAMA_TIMEOUT` env var/constant
- **Improved JSON Parsing**: Better extraction of JSON from AI responses wrapped in markdown code blocks


### Fixed

- **WordPress 6.7 Deprecation**: Added `__nextHasNoMarginBottom` prop to CheckboxControl


## [0.1.6] - 2026-01-02

### Added

- **Exo Settings Enhancements**: Improved Exo provider configuration inspired by content-poll
  - Health check button with visual status indicator (✅/❌)
  - Dynamic model dropdown populated from running Exo cluster
  - "Refresh Models" button to fetch available models
  - Renamed `exo_url` to `exo_endpoint` for consistency
- **ExoController REST API**: New REST endpoints for Exo configuration
  - `POST /vmfa/v1/exo-health` - Check connection to Exo cluster
  - `POST /vmfa/v1/exo-models` - Fetch available models from Exo cluster

### Changed

- **Environment Variables**: `VMFA_AI_EXO_URL` renamed to `VMFA_AI_EXO_ENDPOINT`

## [0.1.5] - 2026-01-01

### Changed

- **Reorganized Settings Tabs**: Split settings into three tabs for better organization
  - Media Scanner: Scan and organize your media library
  - Settings: Organization settings (folder depth, new folders, batch size)
  - AI Provider: Provider selection and API configuration
- **Simplified README**: Removed specific model lists, now points to AI Provider Guide
- **New Development Guide**: Moved development documentation to separate file (`docs/DEVELOPMENT.md`)

## [0.1.4] - 2025-12-31

### Improved

- **Better User Messaging**: Scan started notices now inform users they can leave the page and return later
- **Initialization Hint**: Added message indicating that AI provider initialization may take a couple of minutes

## [0.1.3] - 2025-12-31

### Improved

- **Alphabetical Folder Sorting**: Folders are now sorted alphabetically in the preview modal and when creating virtual folders
- **Full-width Scan Progress**: The scan progress component now uses the full available width for better visibility

## [0.1.2] - 2025-12-31

### Fixed

- **Namespace Fix**: Fixed `GitHubPluginUpdater` class namespace to match PSR-4 autoloading (`VmfaAiOrganizer\Update`)
- **Translated Folder Names**: Documents and Videos folders are now translatable using gettext

### Added

- **Norwegian Translations**: Complete Norwegian Bokmål (nb_NO) translation
  - All formats: .po, .mo, .l10n.php (WP 6.5+), and JSON for JavaScript
  - Updated .pot template with all translatable strings

- **Expandable Scan Results**: Recent Results now shows all processed files in an expandable accordion
  - Click any result to see details: Attachment ID, Action, Folder, Confidence, Reason
  - Color-coded by action type (blue=assign, green=create, gray=skip)
  - Scrollable list with fixed header

- **Session Folder Tracking**: AI remembers folders suggested during the current scan session
  - Prevents duplicate/synonymous folders like "Nature/Flowers" vs "Nature/Plants/Flowers"
  - Session is cleared when a new scan starts
  - Suggested folders are passed to AI with instructions to reuse them

- **Automatic File Type Folders**: Documents and videos are now auto-assigned without AI
  - Documents (PDF, Word, Excel, PowerPoint, text files, etc.) → "Documents" folder
  - Videos (MP4, WebM, MOV, AVI, etc.) → "Videos" folder
  - Creates folders automatically if "Allow New Folders" is enabled

- **Localized Folder Names**: AI providers now return folder names in the WordPress site language
  - Automatically detects WordPress locale (e.g., nb_NO, de_DE, fr_FR)
  - Supports 35+ languages with human-readable names
  - Falls back to locale code for unsupported languages

- **Dry-Run Result Caching**: Preview results are now cached so applying them doesn't require re-running AI analysis
  - Saves time and API costs by reusing cached analysis when accepting preview results
  - Cache is automatically cleared when starting a new preview scan
  - New REST endpoints: `/vmfa/v1/scan/apply-cached` and `/vmfa/v1/scan/cached-count`

### Changed

- **AI Provider Required**: Removed HeuristicProvider - an AI provider must now be configured to scan images
  - Scanning will not start without a configured AI provider
  - Clear error message guides users to configure provider in settings
  - Documents and videos still work without AI (type-based assignment)

- **Improved AI Prompts**: Enhanced system prompt to avoid similar/synonymous folder names
  - Explicit rules against creating synonym folders (e.g., "Wildlife" if "Animals" exists)
  - Standard category examples provided (Animals, Nature, People, Buildings, etc.)
  - Enforces one or two word folder names maximum
  - Strongly prefers existing folders over creating new ones

- **VMF Compatibility**: Fixed folder assignment to match Virtual Media Folders behavior
  - Uses `append=true` with `wp_set_object_terms()` to match VMF
  - Fires `vmfo_media_moved` action for VMF compatibility
  - Improved parent folder matching with proper `get_terms()` query

### Fixed

- Fixed folders being created but empty after applying changes
- Fixed progress bar exceeding 100% during scanning
- Fixed preview modal not showing all results (now shows actual count)
- Fixed modal buttons not visible when content overflows

## [0.1.0] - 2024-12-30

### Added

- **Vision-Based AI Analysis**: Analyzes actual image content using vision-capable AI models
  - Prioritizes visual content over metadata for folder suggestions
  - Supports JPEG, PNG, GIF, and WebP formats (up to 10MB)
  - Uses resized images when available to reduce API costs

- **AI Provider Support**:
  - OpenAI (GPT-4o, GPT-4o-mini, GPT-4-turbo, GPT-3.5-turbo)
  - Azure OpenAI with full endpoint and API version configuration
  - Anthropic Claude (Claude 3 Haiku, Sonnet, Opus, Claude 3.5 Sonnet)
  - Google Gemini (Gemini 1.5 Flash, Gemini 1.5 Pro, Gemini 2.0 Flash)
  - Ollama for local LLM inference (including LLaVA for vision)
  - Grok (xAI) - Grok Beta, Grok 2, Grok 2 Mini
  - Exo for distributed local inference

- **Scan Modes**:
  - Organize Unassigned: Process only media not in any folder
  - Re-analyze All: Re-analyze all media and update assignments
  - Reorganize All: Clear all folders and rebuild from scratch

- **Preview Mode**: Dry-run capability to preview changes before applying

- **Backup & Restore**: Automatic backup before reorganization with one-click restore

- **Background Processing**: Uses Action Scheduler for chunked batch processing

- **Admin UI**:
  - Tabbed interface (Media Scanner / AI Provider)
  - Real-time progress tracking with live updates
  - Statistics dashboard showing media organization status
  - Provider-specific settings with dynamic field visibility

- **Configuration Options**:
  - Support for PHP constants and environment variables
  - Database settings via admin UI
  - Priority-based configuration resolution

- **REST API Endpoints**:
  - `/vmfa/v1/scan` - Start, status, cancel, reset scan operations
  - `/vmfa/v1/analyze/{id}` - Single attachment analysis
  - `/vmfa/v1/backup` - Backup info and restore
  - `/vmfa/v1/stats` - Media statistics

- **Developer Features**:
  - Filters: `vmfa_ai_prompt`, `vmfa_analysis_result`, `vmfa_folder_path`
  - Actions: `vmfa_media_assigned`, `vmfa_scan_completed`
  - Full test coverage (13 PHP tests, 12 JavaScript tests)

### Dependencies

- Requires WordPress 6.8+
- Requires PHP 8.3+
- Requires Virtual Media Folders plugin
- Bundles Action Scheduler 3.9.3 for background processing


[0.1.9]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.8...v0.1.9
[0.1.8]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.7...v0.1.8
[0.1.7]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.6...v0.1.7
[0.1.6]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/soderlind/vmfa-ai-organizer/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/soderlind/vmfa-ai-organizer/releases/tag/v0.1.0
