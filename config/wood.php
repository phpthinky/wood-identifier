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
    | AI Vision Provider
    |--------------------------------------------------------------------------
    | Which Prism provider to use for the AI fallback.
    | Options: anthropic (Claude), google (Gemini)
    */
    'ai_provider' => env('WOOD_AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | AI Vision Model
    |--------------------------------------------------------------------------
    | The specific model to use for vision identification.
    */
    'ai_model' => env('WOOD_AI_MODEL', 'claude-opus-4-6'),

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
