# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
  - Heuristic fallback (pattern matching, no API required)

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

[0.1.0]: https://github.com/soderlind/vmfa-ai-organizer/releases/tag/v0.1.0
