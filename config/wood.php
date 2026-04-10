<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Python Binary
    |--------------------------------------------------------------------------
    | Path to the Python 3 binary used to run OpenCV scripts.
    | Override via WOOD_PYTHON_BINARY in .env for virtualenv support.
    */
    'python_binary' => env('WOOD_PYTHON_BINARY', 'python3'),

    /*
    |--------------------------------------------------------------------------
    | Scripts Path
    |--------------------------------------------------------------------------
    | Absolute path to the OpenCV Python scripts directory.
    | Defaults to <project_root>/scripts/
    */
    'scripts_path' => env('WOOD_SCRIPTS_PATH', base_path('scripts')),

    /*
    |--------------------------------------------------------------------------
    | Confidence Thresholds
    |--------------------------------------------------------------------------
    | Controls when the system escalates from cache → AI Vision.
    | Cache entries below this level will NOT be used as a cache hit.
    */
    'min_cache_confidence' => env('WOOD_MIN_CACHE_CONFIDENCE', 'high'),

    /*
    |--------------------------------------------------------------------------
    | AI Vision Provider (Primary)
    |--------------------------------------------------------------------------
    | Which Prism provider to use for the AI fallback.
    |
    | Options:
    |   anthropic  → Claude Vision (ANTHROPIC_API_KEY required)
    |   gemini     → Gemini Vision (GEMINI_API_KEY required)
    |
    | Set whichever API key you have. The system will skip providers
    | with no key configured and try the fallback automatically.
    */
    'ai_provider' => env('WOOD_AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | AI Vision Model (Primary)
    |--------------------------------------------------------------------------
    | The specific model for the primary provider.
    | Leave blank to use the provider default.
    |
    |   Anthropic: claude-opus-4-6, claude-sonnet-4-6
    |   Gemini:    gemini-2.0-flash, gemini-1.5-pro
    */
    'ai_model' => env('WOOD_AI_MODEL', ''),

    /*
    |--------------------------------------------------------------------------
    | AI Vision Fallback Provider
    |--------------------------------------------------------------------------
    | If the primary provider has no API key or fails, the system
    | automatically tries this provider instead.
    |
    | Example: primary=anthropic, fallback=gemini
    |   → Use Claude if key exists, otherwise use Gemini.
    |
    | Leave blank to disable fallback (single provider mode).
    */
    'ai_fallback_provider' => env('WOOD_AI_FALLBACK_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | AI Vision Fallback Model
    |--------------------------------------------------------------------------
    | Model for the fallback provider. Leave blank to use provider default.
    */
    'ai_fallback_model' => env('WOOD_AI_FALLBACK_MODEL', ''),

    /*
    |--------------------------------------------------------------------------
    | Max Temp Image Size (bytes)
    |--------------------------------------------------------------------------
    | Maximum image size accepted from Base64 or file upload.
    | Default: 10 MB
    */
    'max_image_bytes' => env('WOOD_MAX_IMAGE_BYTES', 10 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Temp Storage Disk
    |--------------------------------------------------------------------------
    | Laravel storage disk used for temp scan images.
    | Images are deleted after each scan — this is just the decode buffer.
    */
    'temp_disk' => env('WOOD_TEMP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | CIEDE2000 Confidence Thresholds
    |--------------------------------------------------------------------------
    | delta_e values that map to confidence levels.
    | Used by FeatureExtractorService to rate the color match quality.
    */
    'ciede2000' => [
        'very_high' => 2.0,
        'high'      => 5.0,
        'medium'    => 10.0,
        'low'       => 20.0,
        // above 20 = very_low
    ],

];
