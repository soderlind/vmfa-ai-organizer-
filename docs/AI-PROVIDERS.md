# AI Provider Guide

This guide explains how to configure and choose the right AI provider for the Virtual Media Folders AI Organizer plugin.

## Table of Contents

- [Overview](#overview)
- [Choosing a Model](#choosing-a-model)
- [Provider Setup](#provider-setup)
  - [OpenAI](#openai)
  - [Azure OpenAI](#azure-openai)
  - [Anthropic Claude](#anthropic-claude)
  - [Google Gemini](#google-gemini)
  - [Ollama (Local)](#ollama-local)
  - [Grok (xAI)](#grok-xai)
  - [Exo (Distributed Local)](#exo-distributed-local)
- [Troubleshooting](#troubleshooting)

## Overview

This plugin uses **vision-capable AI models** to analyze actual image content. The AI looks at what's in the image (objects, scenes, people, colors, activities) to determine the best folder for each media file.

**Important**: You must configure an AI provider before scanning. Documents (PDF, Word, etc.) and videos are automatically assigned to "Documents" and "Videos" folders without requiring AI.

### Quick Provider Comparison

| Provider | Type | Best For |
|----------|------|----------|
| **OpenAI** | Cloud | General use, easiest setup |
| **Azure OpenAI** | Cloud | Enterprise, data residency, compliance |
| **Anthropic** | Cloud | Nuanced understanding, detailed analysis |
| **Gemini** | Cloud | Cost-effective, free tier available |
| **Ollama** | Local | Privacy, no API costs, offline use |
| **Grok** | Cloud | xAI ecosystem |
| **Exo** | Local | Distributed computing across devices |

## Choosing a Model

### The Most Important Requirement: Vision Support

This plugin requires a **vision-capable model**. Models without vision support cannot analyze images.

How to identify vision-capable models:
- Look for "vision" in the model name
- Check for "multimodal" capabilities in documentation
- Confirm the model accepts image input

### Model Tiers Explained

Most providers offer models in different tiers. Understanding these helps you choose wisely:

| Tier | Characteristics | Best For |
|------|-----------------|----------|
| **Flagship** | Highest quality, more expensive, sometimes slower | Complex categorization needing nuanced understanding |
| **Balanced** | Good quality at moderate cost | Most typical use cases |
| **Fast/Mini** | Lower cost, faster, slightly reduced quality | Large media libraries, simple categorization |

**Recommendation**: Start with a "balanced" or "mini/flash" tier model. You can upgrade later if needed.

### What to Consider When Choosing

#### 1. Cost vs. Quality

- **Budget-conscious**: Use the provider's cheapest vision-capable model
- **Quality-focused**: Use the flagship vision model
- **Large libraries (1000s of images)**: Cheaper models keep costs manageable

#### 2. Speed

- Mini/Flash models process faster
- Flagship models may be slower but more accurate
- Local models (Ollama) depend entirely on your hardware

#### 3. Privacy

- **Cloud providers**: Images are sent to external servers
- **Local providers (Ollama, Exo)**: All processing stays on your infrastructure

#### 4. Reliability

- Major cloud providers (OpenAI, Anthropic, Google) offer high availability
- Local solutions require your server to be capable and running

### Finding Current Models

Model offerings change frequently. Always check your provider's official documentation for the latest vision-capable models:

| Provider | Documentation |
|----------|--------------|
| OpenAI | [platform.openai.com/docs/models](https://platform.openai.com/docs/models) |
| Anthropic | [docs.anthropic.com/en/docs/models](https://docs.anthropic.com/en/docs/about-claude/models) |
| Google Gemini | [ai.google.dev/gemini-api/docs/models](https://ai.google.dev/gemini-api/docs/models/gemini) |
| Ollama | [ollama.com/library](https://ollama.com/library) (filter for "vision") |
| Grok/xAI | [docs.x.ai](https://docs.x.ai/) |

**Tip**: When reviewing model lists, look for terms like "vision", "multimodal", "image input", or "visual understanding".

---

## Provider Setup

### OpenAI

OpenAI is the most popular choice and easiest to set up.

#### Getting Started

1. Create an account at [platform.openai.com](https://platform.openai.com/)
2. Generate an API key at [API Keys](https://platform.openai.com/api-keys)
3. Check [Models documentation](https://platform.openai.com/docs/models) for current vision-capable models

#### Configuration (Admin UI)

In **Media → AI Organizer → AI Provider**:
- Set **AI Provider** to "OpenAI"
- Set **OpenAI Type** to "OpenAI"
- Enter your **API Key**
- Set **Model** to a current vision-capable model

#### Configuration (wp-config.php)

```php
define( 'VMFA_AI_PROVIDER', 'openai' );
define( 'VMFA_AI_OPENAI_KEY', 'sk-...' );
define( 'VMFA_AI_OPENAI_MODEL', 'your-chosen-model' );
```

#### Tips

- Start with a "mini" or budget-tier model for testing
- Upgrade to flagship models if you need better accuracy
- Check OpenAI's pricing page for current costs

---

### Azure OpenAI

Azure OpenAI is ideal for enterprises needing data residency, compliance, and Azure integration.

#### Getting Started

1. Have an Azure subscription
2. Request access to Azure OpenAI Service
3. Create an Azure OpenAI resource in Azure Portal
4. Deploy a vision-capable model with a deployment name
5. Note your endpoint URL and key

#### Configuration (Admin UI)

In **Media → AI Organizer → AI Provider**:
- Set **AI Provider** to "OpenAI"
- Set **OpenAI Type** to "Azure"
- Enter your **Azure Endpoint** (e.g., `https://your-resource.openai.azure.com`)
- Enter your **API Key**
- Set **Model/Deployment** to your deployment name (not the model name)

#### Configuration (wp-config.php)

```php
define( 'VMFA_AI_PROVIDER', 'openai' );
define( 'VMFA_AI_OPENAI_TYPE', 'azure' );
define( 'VMFA_AI_OPENAI_KEY', 'your-azure-api-key' );
define( 'VMFA_AI_OPENAI_MODEL', 'your-deployment-name' );
define( 'VMFA_AI_AZURE_ENDPOINT', 'https://your-resource.openai.azure.com' );
define( 'VMFA_AI_AZURE_API_VERSION', '2024-02-15-preview' );
```

#### Tips

- The **Model** field must be your **deployment name**, not the underlying model name
- Use a recent API version for vision support (check Azure docs for current versions)
- Deploy a vision-capable model in your Azure OpenAI resource

---

### Anthropic Claude

Anthropic's Claude models are known for nuanced understanding and detailed analysis.

#### Getting Started

1. Create an account at [console.anthropic.com](https://console.anthropic.com/)
2. Generate an API key
3. Check [Models documentation](https://docs.anthropic.com/en/docs/about-claude/models) for current vision-capable models

#### Configuration (Admin UI)

In **Media → AI Organizer → AI Provider**:
- Set **AI Provider** to "Anthropic"
- Enter your **API Key**
- Set **Model** to a current vision-capable model (Claude 3+ models support vision)

#### Configuration (wp-config.php)

```php
define( 'VMFA_AI_PROVIDER', 'anthropic' );
define( 'VMFA_AI_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'VMFA_AI_ANTHROPIC_MODEL', 'your-chosen-model' );
```

#### Tips

- All Claude 3 and later models support vision
- Sonnet models offer a good balance of quality and cost
- Haiku models are faster and cheaper for high-volume use

---

### Google Gemini

Google Gemini offers competitive vision capabilities with a generous free tier.

#### Getting Started

1. Get an API key from [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Check [Models documentation](https://ai.google.dev/gemini-api/docs/models/gemini) for current options

#### Configuration (Admin UI)

In **Media → AI Organizer → AI Provider**:
- Set **AI Provider** to "Gemini"
- Enter your **API Key**
- Set **Model** to a current vision-capable model (Flash models recommended for cost)

#### Configuration (wp-config.php)

```php
define( 'VMFA_AI_PROVIDER', 'gemini' );
define( 'VMFA_AI_GEMINI_KEY', 'your-api-key' );
define( 'VMFA_AI_GEMINI_MODEL', 'your-chosen-model' );
```

#### Tips

- Gemini has a **free tier** with rate limits - great for testing
- Flash models are optimized for speed and cost
- Pro models offer higher capability for complex tasks

---

### Ollama (Local)

Ollama runs AI models entirely on your own hardware - completely free and private.

#### Prerequisites

1. A server with sufficient RAM (8GB+ recommended) and ideally a GPU
2. [Ollama](https://ollama.com/) installed

#### Getting Started

```bash
# Install Ollama (macOS/Linux)
curl -fsSL https://ollama.com/install.sh | sh

# Browse available models at ollama.com/library
# Look for vision-capable models (e.g., models with "llava" or "vision")

# Pull a vision model
ollama pull <model-name>

# Verify Ollama is running
curl http://localhost:11434/api/tags
```

#### Configuration (Admin UI)

In **Media → AI Organizer → AI Provider**:
- Set **AI Provider** to "Ollama"
- Set **Ollama URL** to `http://localhost:11434`
- Click **Refresh Models** to populate the model dropdown from your running Ollama server
- Select a model from the dropdown
- Optionally adjust **Ollama Timeout** (default 120 seconds) for larger models or slower hardware

#### Configuration (wp-config.php)

```php
define( 'VMFA_AI_PROVIDER', 'ollama' );
define( 'VMFA_AI_OLLAMA_URL', 'http://localhost:11434' );
define( 'VMFA_AI_OLLAMA_MODEL', 'your-vision-model' );
define( 'VMFA_AI_OLLAMA_TIMEOUT', 120 ); // Optional: timeout in seconds (10-600)
```

#### Tips

- Click **Refresh Models** to see all models installed in your Ollama instance
- Search the [Ollama library](https://ollama.com/library) for "vision" to find compatible models
- Larger models need more VRAM/RAM but produce better results
- Increase the timeout setting for larger models or slower hardware
- If WordPress is in Docker, use `http://host.docker.internal:11434`
- Processing is slower than cloud APIs but completely free and private

---

### Grok (xAI)

Grok is xAI's AI assistant with vision capabilities.

#### Getting Started

1. Get an API key from [xAI Console](https://console.x.ai/)
2. Check their documentation for current vision-capable models

#### Configuration (Admin UI)

In **Media → AI Organizer → AI Provider**:
- Set **AI Provider** to "Grok"
- Enter your **API Key**
- Set **Model** to a vision-capable Grok model

#### Configuration (wp-config.php)

```php
define( 'VMFA_AI_PROVIDER', 'grok' );
define( 'VMFA_AI_GROK_KEY', 'your-api-key' );
define( 'VMFA_AI_GROK_MODEL', 'your-chosen-model' );
```

---

### Exo (Distributed Local)

Exo allows running AI models across multiple local devices, pooling their compute power. It exposes an OpenAI-compatible API locally.

#### Getting Started

1. Install [Exo](https://github.com/exo-explore/exo)
2. Start the Exo cluster on your device(s)
3. Note the API endpoint (default: `http://localhost:52415`)

#### Configuration (Admin UI)

In **Media → AI Organizer → AI Provider**:

1. Set **AI Provider** to "Exo (Distributed Local)"
2. Enter your **Exo Endpoint** (e.g., `http://localhost:52415`)
3. Click **Check Connection** to verify connectivity (you'll see ✅ or ❌)
4. Click **Refresh Models** to populate the model dropdown from your running cluster
5. Select a model from the dropdown

**Features**:
- **Health Check Button**: Visual indicator (✅/❌) shows connection status
- **Dynamic Model List**: Models are fetched directly from your running Exo cluster
- **No API Key Required**: Exo runs locally on your network

#### Configuration (wp-config.php)

```php
define( 'VMFA_AI_PROVIDER', 'exo' );
define( 'VMFA_AI_EXO_ENDPOINT', 'http://localhost:52415' );
define( 'VMFA_AI_EXO_MODEL', 'llama-3.2-3b' );
```

#### Tips

- Ensure at least one model is loaded in your Exo cluster before configuring
- The timeout for Exo requests is 60 seconds (local inference can be slower)
- Vision support depends on the model being used in your cluster

---

## Troubleshooting

### "No AI provider configured"

You must select and configure an AI provider in **Media → AI Organizer → AI Provider**.

### "API key not configured" or authentication errors

1. Verify your API key is entered correctly
2. For Azure, ensure the endpoint URL format is correct
3. Check that your API key has the necessary permissions

### Empty responses or parse errors

1. Ensure you're using a **vision-capable** model
2. Check that images are in a supported format (JPEG, PNG, GIF, WebP)
3. Try a different model - some handle certain images better
4. Check your provider's status page for outages

### Ollama connection refused

1. Ensure Ollama is running: `ollama serve`
2. Verify the URL is correct (default: `http://localhost:11434`)
3. If WordPress is in Docker, use `http://host.docker.internal:11434`
4. Check firewall settings if accessing remotely

### Ollama timeout errors

1. Increase the **Ollama Timeout** setting (default is 120 seconds)
2. Larger models take longer to load into memory on first request
3. Consider using a smaller/faster model for quicker responses
4. Ensure your hardware meets the model's requirements (RAM/VRAM)

### Exo connection issues

1. Ensure your Exo cluster is running: check with `curl http://localhost:52415/v1/models`
2. Verify the endpoint URL matches your Exo configuration (default port is 52415)
3. Use the **Check Connection** button in settings to verify connectivity
4. Click **Refresh Models** to see available models from your cluster
5. Ensure at least one model is loaded in your Exo cluster
6. Check firewall allows local connections on the configured port
7. If WordPress is in Docker, use `http://host.docker.internal:52415`

### Rate limiting

1. Reduce the **Batch Size** in settings
2. The plugin automatically handles rate limits with retries
3. Consider upgrading your API plan for higher limits

### High costs

1. Switch to a cheaper/mini model tier
2. Use **Organize Unassigned** mode instead of re-analyzing everything
3. Use **Preview Mode** first to verify results before applying
4. Consider Ollama for free local processing

### Model not found

1. Verify the model name is spelled correctly
2. Check if the model is available in your region/account
3. For Azure, ensure you're using the deployment name, not the model name
4. Check the provider's documentation for current available models
