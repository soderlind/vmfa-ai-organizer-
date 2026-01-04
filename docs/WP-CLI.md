# WP-CLI Commands

Virtual Media Folders AI Organizer provides a comprehensive WP-CLI interface for automating media organization via the command line.

## Prerequisites

- [Virtual Media Folders](https://wordpress.org/plugins/virtual-media-folders/) plugin must be installed and activated
- An AI provider must be configured (via settings, constants, or CLI parameters)

## Quick Start

```bash
# 1. Start a preview scan of unassigned media
wp vmfa-ai scan start

# 2. Monitor progress with live updates (runs Action Scheduler queue)
wp vmfa-ai scan status --watch

# 3. Review the results
wp vmfa-ai scan results

# 4. Apply the changes
wp vmfa-ai scan apply
```

> **Note:** The `--watch` option automatically runs the Action Scheduler queue, so scans will process while you watch. Without `--watch`, you need to ensure the WordPress cron or Action Scheduler is running.

## Commands Reference

### Scan Commands

#### `wp vmfa-ai scan start`

Start a new media scan in preview mode. Uses Action Scheduler for background processing.

```bash
wp vmfa-ai scan start [--mode=<mode>] [--provider=<provider>] [--model=<model>] [--api-key=<key>] [--endpoint=<url>] [--timeout=<seconds>] [--porcelain]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--mode` | Scan mode: `organize_unassigned`, `reanalyze_all`, or `reorganize_all` | `organize_unassigned` |
| `--provider` | Override AI provider (openai, anthropic, gemini, ollama, grok, exo) | Configured provider |
| `--model` | Override AI model | Provider default |
| `--api-key` | Override API key | Configured key |
| `--endpoint` | Override endpoint URL (for ollama/exo) | Configured endpoint |
| `--timeout` | Request timeout in seconds | Provider default |
| `--porcelain` | Machine-readable output | false |

**Scan Modes:**

| Mode | Description |
|------|-------------|
| `organize_unassigned` | Only analyze media not already in folders |
| `reanalyze_all` | Re-analyze all media (keeps existing folder structure) |
| `reorganize_all` | Delete all folders and rebuild from scratch |

**Examples:**

```bash
# Scan unassigned media using configured provider
wp vmfa-ai scan start

# Scan all media with a specific provider/model
wp vmfa-ai scan start --mode=reanalyze_all --provider=ollama --model=llava:34b

# Full reorganization (warning: deletes existing folders)
wp vmfa-ai scan start --mode=reorganize_all

# Override for one-time test
wp vmfa-ai scan start --provider=openai --api-key=sk-xxx --model=gpt-4o
```

---

#### `wp vmfa-ai scan status`

Show current scan status and progress.

```bash
wp vmfa-ai scan status [--watch] [--format=<format>] [--porcelain]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--watch` | Continuously monitor with live updates | false |
| `--format` | Output format: `table`, `json`, `yaml` | `table` |
| `--porcelain` | Machine-readable output: `status:processed:total` | false |

**Examples:**

```bash
# Check current status
wp vmfa-ai scan status

# Watch with live updates (refreshes every 2 seconds)
wp vmfa-ai scan status --watch

# Get status as JSON for scripting
wp vmfa-ai scan status --format=json
```

**Live Watch Display:**

When using `--watch`, you'll see a live-updating display with:
- Progress bar with percentage
- Elapsed time and ETA
- Real-time results table showing recent AI suggestions
- Color-coded confidence levels (green ≥80%, yellow ≥50%, red <50%)

> **Important:** The `--watch` option is designed for **interactive terminal use only**. It automatically runs pending Action Scheduler tasks while displaying live progress. For scripts or background automation, use `--porcelain` to poll status instead (see Scripting Example below).

---

#### `wp vmfa-ai scan apply`

Apply the previewed scan results.

```bash
wp vmfa-ai scan apply [--yes] [--porcelain]
```

**Options:**

| Option | Description |
|--------|-------------|
| `--yes` | Skip confirmation prompt |
| `--porcelain` | Machine-readable output |

**Examples:**

```bash
# Apply with confirmation prompt
wp vmfa-ai scan apply

# Skip confirmation (for scripts)
wp vmfa-ai scan apply --yes
```

---

#### `wp vmfa-ai scan cancel`

Cancel a running scan.

```bash
wp vmfa-ai scan cancel [--porcelain]
```

---

#### `wp vmfa-ai scan reset`

Reset scan progress to idle state. Use if the scan appears stuck.

```bash
wp vmfa-ai scan reset [--yes] [--porcelain]
```

---

#### `wp vmfa-ai scan results`

Display cached preview results.

```bash
wp vmfa-ai scan results [--format=<format>] [--porcelain]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--format` | Output format: `table`, `json`, `csv`, `yaml` | `table` |
| `--porcelain` | Output only attachment IDs | false |

---

### Single Media Analysis

#### `wp vmfa-ai analyze <attachment_id>`

Analyze a single media attachment.

```bash
wp vmfa-ai analyze <attachment_id> [--apply] [--provider=<provider>] [--model=<model>] [--api-key=<key>] [--endpoint=<url>] [--format=<format>] [--porcelain]
```

**Options:**

| Option | Description |
|--------|-------------|
| `<attachment_id>` | The WordPress attachment ID to analyze (required) |
| `--apply` | Apply the suggested folder immediately |
| `--provider` | Override AI provider |
| `--model` | Override AI model |
| `--format` | Output format: `table`, `json`, `yaml` |
| `--porcelain` | Machine-readable output |

**Examples:**

```bash
# Analyze attachment 123 (preview only)
wp vmfa-ai analyze 123

# Analyze and apply immediately
wp vmfa-ai analyze 123 --apply

# Test with a different provider
wp vmfa-ai analyze 123 --provider=anthropic --model=claude-3-5-sonnet-latest

# Get JSON output
wp vmfa-ai analyze 123 --format=json
```

---

### Backup Commands

#### `wp vmfa-ai backup <action>`

Manage folder structure backups.

```bash
wp vmfa-ai backup <action> [--yes] [--format=<format>] [--porcelain]
```

**Actions:**

| Action | Description |
|--------|-------------|
| `export` | Create a backup of current folder structure and assignments |
| `info` | Show information about the existing backup |
| `restore` | Restore folders and assignments from backup |
| `delete` | Delete the stored backup |

**Examples:**

```bash
# Create a backup before making changes
wp vmfa-ai backup export

# Check backup info
wp vmfa-ai backup info

# Restore from backup (with confirmation)
wp vmfa-ai backup restore

# Restore without confirmation
wp vmfa-ai backup restore --yes

# Delete backup
wp vmfa-ai backup delete --yes
```

---

### Provider Commands

#### `wp vmfa-ai provider <action>`

Manage and test AI providers.

```bash
wp vmfa-ai provider <action> [--provider=<provider>] [--model=<model>] [--api-key=<key>] [--endpoint=<url>] [--format=<format>] [--porcelain]
```

**Actions:**

| Action | Description |
|--------|-------------|
| `list` | Show all available providers with their configuration status |
| `test` | Test connection to the configured (or specified) provider |
| `info` | Show detailed info about the current provider |

**Examples:**

```bash
# List all providers
wp vmfa-ai provider list

# Test the configured provider
wp vmfa-ai provider test

# Test a specific provider
wp vmfa-ai provider test --provider=ollama --endpoint=http://192.168.1.100:11434

# Get current provider info
wp vmfa-ai provider info
```

---

### Statistics

#### `wp vmfa-ai stats`

Show media library statistics.

```bash
wp vmfa-ai stats [--format=<format>] [--porcelain]
```

**Examples:**

```bash
# Show stats
wp vmfa-ai stats

# Get stats as JSON
wp vmfa-ai stats --format=json
```

**Output includes:**
- Total media count
- Assigned media count
- Unassigned media count
- Number of folders
- Organization percentage with progress bar

---

## Global Options

These options work with all commands:

| Option | Description |
|--------|-------------|
| `--porcelain` | Minimal machine-readable output for scripting |
| `--format=<format>` | Output format where applicable |
| `--no-color` | Disable colored output (WP-CLI built-in) |
| `--quiet` | Suppress informational messages (WP-CLI built-in) |

---

## Configuration Priority

When using CLI parameters, the configuration priority is:

1. **CLI parameters** (`--provider`, `--model`, `--api-key`, `--endpoint`)
2. **PHP constants** (`VMFA_AI_PROVIDER`, `VMFA_AI_OPENAI_KEY`, etc.)
3. **Environment variables** (`VMFA_AI_PROVIDER`, etc.)
4. **Database settings** (configured via admin UI)
5. **Defaults**

This allows you to temporarily override settings for testing without changing your configuration.

---

## Workflow Examples

### Complete Organization Workflow

```bash
# 1. Check current statistics
wp vmfa-ai stats

# 2. Create a backup first
wp vmfa-ai backup export

# 3. Test your AI provider
wp vmfa-ai provider test

# 4. Start the scan (preview mode)
wp vmfa-ai scan start --mode=organize_unassigned

# 5. Monitor progress (runs Action Scheduler automatically)
wp vmfa-ai scan status --watch

# 6. Review results
wp vmfa-ai scan results

# 7. If satisfied, apply
wp vmfa-ai scan apply --yes

# 8. Verify final statistics
wp vmfa-ai stats
```

### Scripting Example

```bash
#!/bin/bash

# Automated media organization script

# Check if VMF is active
if ! wp vmfa-ai stats --porcelain > /dev/null 2>&1; then
    echo "Error: Virtual Media Folders not active"
    exit 1
fi

# Get unassigned count
STATS=$(wp vmfa-ai stats --porcelain)
UNASSIGNED=$(echo $STATS | cut -d: -f3)

if [ "$UNASSIGNED" -eq 0 ]; then
    echo "No unassigned media to process"
    exit 0
fi

echo "Processing $UNASSIGNED unassigned media items..."

# Create backup
wp vmfa-ai backup export

# Start scan
wp vmfa-ai scan start --mode=organize_unassigned

# Wait for completion (use --watch or run Action Scheduler manually)
# Option 1: Use watch which runs the queue automatically
# wp vmfa-ai scan status --watch

# Option 2: Poll status while running Action Scheduler separately
while true; do
    STATUS=$(wp vmfa-ai scan status --porcelain)
    STATE=$(echo $STATUS | cut -d: -f1)
    
    if [ "$STATE" = "completed" ]; then
        break
    elif [ "$STATE" = "cancelled" ] || [ "$STATE" = "idle" ]; then
        echo "Scan ended unexpectedly: $STATE"
        exit 1
    fi
    
    sleep 5
done

# Apply results
wp vmfa-ai scan apply --yes

echo "Organization complete!"
wp vmfa-ai stats
```

### Testing Different Providers

```bash
# Test with OpenAI
wp vmfa-ai analyze 123 --provider=openai --model=gpt-4o

# Test with Anthropic
wp vmfa-ai analyze 123 --provider=anthropic --model=claude-3-5-sonnet-latest

# Test with local Ollama
wp vmfa-ai analyze 123 --provider=ollama --endpoint=http://localhost:11434 --model=llava:13b

# Compare results
for provider in openai anthropic ollama; do
    echo "=== $provider ==="
    wp vmfa-ai analyze 123 --provider=$provider --format=json | jq '.folder_name, .confidence'
done
```

---

## Troubleshooting

### Scan appears stuck

```bash
# Check status
wp vmfa-ai scan status

# Reset if needed
wp vmfa-ai scan reset --yes

# Restart
wp vmfa-ai scan start
```

### Provider connection issues

```bash
# Test provider connection
wp vmfa-ai provider test

# Test with verbose output
wp vmfa-ai provider test --debug
```

### View Action Scheduler jobs

```bash
# List pending VMFA actions
wp action-scheduler list --group=vmfa-ai-organizer --status=pending

# Run pending actions manually
wp action-scheduler run --group=vmfa-ai-organizer
```

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (missing dependencies, failed operation, etc.) |

---

## Related Documentation

- [AI Providers Configuration](AI-PROVIDERS.md)
- [Development Guide](DEVELOPMENT.md)
