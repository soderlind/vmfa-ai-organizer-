# AI Provider Guide

This guide explains how to configure and choose the right AI provider for the Virtual Media Folders AI Organizer plugin.

## Table of Contents

- [Overview](#overview)
- [Choosing a Provider](#choosing-a-provider)
- [OpenAI](#openai)
- [Azure OpenAI](#azure-openai)
- [Anthropic Claude](#anthropic-claude)
- [Google Gemini](#google-gemini)
- [Ollama (Local)](#ollama-local)
- [Grok (xAI)](#grok-xai)
- [Exo (Distributed Local)](#exo-distributed-local)
- [Cost Comparison](#cost-comparison)
- [Troubleshooting](#troubleshooting)

## Overview

This plugin uses **vision-capable AI models** to analyze actual image content. The AI looks at what's in the image (objects, scenes, people, colors, activities) to determine the best folder for each media file.

**Important**: You must configure an AI provider before scanning. Documents (PDF, Word, etc.) and videos are automatically assigned to "Documents" and "Videos" folders without requiring AI.

## Choosing a Provider

| Use Case | Recommended Provider | Why |
|----------|---------------------|-----|
| **Best accuracy** | OpenAI GPT-4o or Claude 3.5 Sonnet | Most capable vision models |
| **Best value** | OpenAI GPT-4o-mini or Gemini 1.5 Flash | Low cost, good quality |
| **Enterprise/Compliance** | Azure OpenAI | Data residency, enterprise SLAs |
| **Privacy-first** | Ollama (LLaVA) | Runs 100% locally |
| **Free option** | Ollama or Gemini (free tier) | No API costs |

---

## OpenAI

OpenAI provides the most widely-used vision AI models.

### Recommended Models

| Model | Best For | Vision | Cost | Speed |
|-------|----------|--------|------|-------|
| **gpt-4o-mini** ⭐ | General use, best value | ✅ | $0.15/1M input | Fast |
| **gpt-4o** | Maximum accuracy | ✅ | $2.50/1M input | Fast |
| **gpt-4-turbo** | Complex analysis | ✅ | $10/1M input | Medium |

### Setup

1. Get an API key from [OpenAI Platform](https://platform.openai.com/api-keys)
2. In **Media → AI Organizer → AI Provider**:
   - Set **AI Provider** to "OpenAI"
   - Set **OpenAI Type** to "OpenAI"
   - Enter your **API Key**
   - Set **Model** to `gpt-4o-mini` (recommended)

### Configuration

```php
define( 'VMFA_AI_PROVIDER', 'openai' );
define( 'VMFA_AI_OPENAI_TYPE', 'openai' );
define( 'VMFA_AI_OPENAI_KEY', 'sk-...' );
define( 'VMFA_AI_OPENAI_MODEL', 'gpt-4o-mini' );
```

### Tips

- **gpt-4o-mini** offers excellent vision capabilities at 1/16th the cost of gpt-4o
- Set a [usage limit](https://platform.openai.com/account/limits) to avoid unexpected charges
- The plugin automatically resizes images to reduce token usage

---

## Azure OpenAI

Azure OpenAI is ideal for enterprise deployments with compliance requirements.

### Prerequisites

1. Azure subscription with Azure OpenAI access approved
2. Create an Azure OpenAI resource
3. Deploy a vision-capable model (GPT-4o or GPT-4-turbo)

### Recommended Deployments

| Deployment Model | Best For |
|------------------|----------|
| **gpt-4o** | Best balance of speed and accuracy |
| **gpt-4o-mini** | Cost-effective option |
| **gpt-4-turbo** | Complex image analysis |

### Setup

1. In Azure Portal, find your OpenAI resource endpoint (e.g., `https://your-resource.openai.azure.com`)
2. Get an API key from "Keys and Endpoint"
3. Note your deployment name (the name you gave when deploying the model)
4. In **Media → AI Organizer → AI Provider**:
   - Set **AI Provider** to "OpenAI"
   - Set **OpenAI Type** to "Azure"
   - Enter your **Azure Endpoint**
   - Enter your **API Key**
   - Set **Model/Deployment** to your deployment name (e.g., `my-gpt4o-deployment`)

### Configuration

```php
define( 'VMFA_AI_PROVIDER', 'openai' );
define( 'VMFA_AI_OPENAI_TYPE', 'azure' );
define( 'VMFA_AI_OPENAI_KEY', 'your-azure-api-key' );
define( 'VMFA_AI_OPENAI_MODEL', 'your-deployment-name' );
define( 'VMFA_AI_AZURE_ENDPOINT', 'https://your-resource.openai.azure.com' );
define( 'VMFA_AI_AZURE_API_VERSION', '2024-02-15-preview' );
```

### Tips

- The **Model** field should be your **deployment name**, not the model name
- Use API version `2024-02-15-preview` or later for vision support
- Azure pricing varies by region and commitment tier

---

## Anthropic Claude

Anthropic's Claude models excel at nuanced image understanding.

### Recommended Models

| Model | Best For | Vision | Cost | Speed |
|-------|----------|--------|------|-------|
| **claude-3-5-sonnet-20241022** ⭐ | Best overall | ✅ | $3/1M input | Fast |
| **claude-3-haiku-20240307** | Budget option | ✅ | $0.25/1M input | Fastest |
| **claude-3-opus-20240229** | Maximum capability | ✅ | $15/1M input | Slower |
| **claude-3-sonnet-20240229** | Balanced | ✅ | $3/1M input | Fast |

### Setup

1. Get an API key from [Anthropic Console](https://console.anthropic.com/)
2. In **Media → AI Organizer → AI Provider**:
   - Set **AI Provider** to "Anthropic"
   - Enter your **API Key**
   - Set **Model** to `claude-3-5-sonnet-20241022` (recommended)

### Configuration

```php
define( 'VMFA_AI_PROVIDER', 'anthropic' );
define( 'VMFA_AI_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'VMFA_AI_ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022' );
```

### Tips

- Claude 3.5 Sonnet is currently the best Claude model for vision tasks
- Haiku is extremely fast and cost-effective for simpler categorization
- All Claude 3+ models support vision

---

## Google Gemini

Google Gemini offers competitive vision capabilities with a generous free tier.

### Recommended Models

| Model | Best For | Vision | Cost | Speed |
|-------|----------|--------|------|-------|
| **gemini-2.0-flash** ⭐ | Latest, fastest | ✅ | $0.10/1M input | Very fast |
| **gemini-1.5-flash** | Cost-effective | ✅ | $0.075/1M input | Fast |
| **gemini-1.5-pro** | Complex analysis | ✅ | $1.25/1M input | Medium |

### Setup

1. Get an API key from [Google AI Studio](https://aistudio.google.com/app/apikey)
2. In **Media → AI Organizer → AI Provider**:
   - Set **AI Provider** to "Gemini"
   - Enter your **API Key**
   - Set **Model** to `gemini-2.0-flash` (recommended)

### Configuration

```php
define( 'VMFA_AI_PROVIDER', 'gemini' );
define( 'VMFA_AI_GEMINI_KEY', 'your-api-key' );
define( 'VMFA_AI_GEMINI_MODEL', 'gemini-2.0-flash' );
```

### Tips

- Gemini has a **free tier** with rate limits - great for testing
- Flash models are optimized for speed and cost
- Gemini 2.0 Flash is newer and generally better than 1.5 Flash

---

## Ollama (Local)

Ollama runs AI models entirely on your own hardware - completely free and private.

### Prerequisites

1. Install [Ollama](https://ollama.com/)
2. Pull a vision-capable model

### Recommended Models

| Model | Best For | Vision | VRAM Required |
|-------|----------|--------|---------------|
| **llava:13b** ⭐ | Best local vision | ✅ | ~10GB |
| **llava:7b** | Lighter option | ✅ | ~6GB |
| **bakllava** | Alternative | ✅ | ~6GB |

### Setup

```bash
# Install Ollama (macOS/Linux)
curl -fsSL https://ollama.com/install.sh | sh

# Pull a vision model
ollama pull llava:13b

# Verify it's running
curl http://localhost:11434/api/tags
```

Then in **Media → AI Organizer → AI Provider**:
- Set **AI Provider** to "Ollama"
- Set **Ollama URL** to `http://localhost:11434`
- Set **Model** to `llava:13b`

### Configuration

```php
define( 'VMFA_AI_PROVIDER', 'ollama' );
define( 'VMFA_AI_OLLAMA_URL', 'http://localhost:11434' );
define( 'VMFA_AI_OLLAMA_MODEL', 'llava:13b' );
```

### Tips

- **LLaVA** is the most popular local vision model
- Requires a decent GPU (or patience with CPU-only)
- If WordPress is in Docker, use `http://host.docker.internal:11434`
- Processing is slower than cloud APIs but completely free

---

## Grok (xAI)

Grok is xAI's AI assistant with vision capabilities.

### Recommended Models

| Model | Best For | Vision |
|-------|----------|--------|
| **grok-2-vision-1212** ⭐ | Best vision | ✅ |
| **grok-2-1212** | Text analysis | ❌ |
| **grok-beta** | Older version | ✅ |

### Setup

1. Get an API key from [xAI Console](https://console.x.ai/)
2. In **Media → AI Organizer → AI Provider**:
   - Set **AI Provider** to "Grok"
   - Enter your **API Key**
   - Set **Model** to `grok-2-vision-1212`

### Configuration

```php
define( 'VMFA_AI_PROVIDER', 'grok' );
define( 'VMFA_AI_GROK_KEY', 'your-api-key' );
define( 'VMFA_AI_GROK_MODEL', 'grok-2-vision-1212' );
```

---

## Exo (Distributed Local)

Exo allows running AI models across multiple local devices.

### Setup

1. Install [Exo](https://github.com/exo-explore/exo)
2. Start the Exo cluster
3. In **Media → AI Organizer → AI Provider**:
   - Set **AI Provider** to "Exo"
   - Set **Exo URL** to `http://localhost:52415`
   - Set **Model** based on your cluster capabilities

### Configuration

```php
define( 'VMFA_AI_PROVIDER', 'exo' );
define( 'VMFA_AI_EXO_URL', 'http://localhost:52415' );
define( 'VMFA_AI_EXO_MODEL', 'llama-3.2-3b' );
```

---

## Cost Comparison

Estimated cost to organize 1,000 images (assuming ~500 tokens per image):

| Provider | Model | Est. Cost | Notes |
|----------|-------|-----------|-------|
| Ollama | LLaVA | **$0** | Free, runs locally |
| Gemini | 1.5 Flash | **~$0.04** | Free tier available |
| OpenAI | GPT-4o-mini | **~$0.08** | Best value cloud |
| Anthropic | Haiku | **~$0.13** | Fast and capable |
| Gemini | 2.0 Flash | **~$0.05** | Latest, fast |
| OpenAI | GPT-4o | **~$1.25** | Premium quality |
| Anthropic | Claude 3.5 Sonnet | **~$1.50** | Premium quality |
| Anthropic | Opus | **~$7.50** | Maximum capability |

*Costs are approximate and may vary based on image complexity and response length.*

---

## Troubleshooting

### "No AI provider configured"

You must select and configure an AI provider in **Media → AI Organizer → AI Provider**.

### "API key not configured" or similar

1. Check that your API key is entered correctly
2. For Azure, ensure you're using the correct endpoint format
3. Try the "Test Connection" if available

### Empty responses or parse errors

1. Ensure you're using a **vision-capable** model
2. Check that images are in a supported format (JPEG, PNG, GIF, WebP)
3. Try a different model - some handle certain images better

### Ollama connection refused

1. Ensure Ollama is running: `ollama serve`
2. Check the URL is correct (default: `http://localhost:11434`)
3. If WordPress is in Docker, use `http://host.docker.internal:11434`

### Rate limiting

1. Reduce the **Batch Size** in settings
2. The plugin automatically handles rate limits with retries
3. Consider upgrading your API plan for higher limits

### High costs

1. Switch to a cheaper model (GPT-4o-mini, Gemini Flash, Haiku)
2. Use **Organize Unassigned** mode instead of re-analyzing everything
3. Use **Preview Mode** first to verify results before applying
4. Consider Ollama for free local processing

---

## Model Comparison Matrix

| Provider | Model | Vision | Quality | Speed | Cost |
|----------|-------|--------|---------|-------|------|
| OpenAI | GPT-4o | ✅ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | $$ |
| OpenAI | GPT-4o-mini | ✅ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $ |
| Anthropic | Claude 3.5 Sonnet | ✅ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | $$ |
| Anthropic | Claude 3 Haiku | ✅ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $ |
| Gemini | 2.0 Flash | ✅ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $ |
| Gemini | 1.5 Pro | ✅ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | $$ |
| Ollama | LLaVA 13B | ✅ | ⭐⭐⭐ | ⭐⭐ | Free |
| Grok | grok-2-vision | ✅ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | $$ |

**Legend**: $ = Low cost, $$ = Medium cost, $$$ = High cost
