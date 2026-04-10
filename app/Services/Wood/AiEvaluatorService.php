<?php

namespace App\Services\Wood;

use App\Models\WoodSpecies;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\Support\Image;
use RuntimeException;

/**
 * Module 7 — AI Evaluator Service (Human-in-the-Loop Fallback)
 *
 * Responsibility: Level 3 of the Brain-First pipeline.
 * Only triggered when Levels 1 (OpenCV) and 2 (Cache) cannot produce
 * a high-confidence result.
 *
 * Provider selection (config-driven, NOT hardcoded):
 * ┌─────────────────────────────────────────────────────┐
 * │  .env WOOD_AI_PROVIDER=anthropic  → Claude Vision   │
 * │  .env WOOD_AI_PROVIDER=gemini     → Gemini Vision   │
 * │  .env WOOD_AI_FALLBACK=gemini     → if Claude fails │
 * │  .env WOOD_AI_FALLBACK=anthropic  → if Gemini fails │
 * └─────────────────────────────────────────────────────┘
 *
 * Fallback logic:
 *   1. Try primary provider (WOOD_AI_PROVIDER)
 *   2. If API key missing or call fails → try fallback provider (WOOD_AI_FALLBACK)
 *   3. If both fail → return degraded result (no crash, no 500)
 *
 * This means: if you only have a Gemini key right now, set WOOD_AI_PROVIDER=gemini
 * and the system works. When you get a Claude key later, just swap the .env value.
 */
class AiEvaluatorService
{
    /**
     * Provider key → Prism Provider enum.
     * PHP does not allow enum values in class constants (non-scalar),
     * so this is a method instead of const.
     */
    private function providerMap(): array
    {
        return [
            'anthropic' => Provider::Anthropic,
            'claude'    => Provider::Anthropic, // alias
            'gemini'    => Provider::Gemini,
            'google'    => Provider::Gemini,    // alias
        ];
    }

    /**
     * Default vision model per provider.
     * Overridden by WOOD_AI_MODEL / WOOD_AI_FALLBACK_MODEL in .env.
     */
    private function defaultModels(): array
    {
        return [
            'anthropic' => 'claude-opus-4-6',
            'claude'    => 'claude-opus-4-6',
            'gemini'    => 'gemini-2.0-flash',
            'google'    => 'gemini-2.0-flash',
        ];
    }

    /**
     * Ask the configured AI Vision model to identify the wood species.
     * Automatically falls back to the secondary provider if the primary fails.
     *
     * @param  string $imagePath    Absolute path to the image
     * @param  array  $fingerprint  OpenCV feature fingerprint (context for the AI)
     * @return array                Structured AI result
     */
    public function evaluate(string $imagePath, array $fingerprint): array
    {
        $species   = $this->loadSpeciesReference();
        $prompt    = $this->buildPrompt($fingerprint, $species);
        $imageData = $this->encodeImage($imagePath);
        $mime      = $this->detectMime($imagePath);

        // Determine provider chain: [primary, fallback (if configured)]
        $chain = $this->buildProviderChain();

        $lastError = null;

        foreach ($chain as $attempt) {
            [$providerKey, $model, $prismProvider] = $attempt;

            // Skip if no API key configured for this provider
            if (!$this->hasApiKey($providerKey)) {
                $lastError = "No API key configured for provider: {$providerKey}";
                continue;
            }

            try {
                $response = Prism::text()
                    ->using($prismProvider, $model)
                    ->withMessages([
                        new UserMessage(
                            $prompt,
                            additionalContent: [
                                Image::fromBase64($imageData, $mime),
                            ]
                        ),
                    ])
                    ->withMaxTokens(512)
                    ->asText();

                return $this->parseResponse($response->text, $fingerprint, $providerKey);

            } catch (\Throwable $e) {
                $lastError = "[{$providerKey}] {$e->getMessage()}";
                // Continue to next provider in chain
            }
        }

        // All providers failed — return graceful degraded result
        return $this->failedResponse($lastError ?? 'No AI provider available.');
    }

    // ──────────────────────────────────────────────────
    // Provider Resolution
    // ──────────────────────────────────────────────────

    /**
     * Build the ordered provider chain: [primary, fallback?]
     * Each item: [providerKey, model, Prism Provider enum]
     */
    private function buildProviderChain(): array
    {
        $chain = [];

        // Primary
        $primary = strtolower(config('wood.ai_provider', 'anthropic'));
        $chain[] = $this->resolveProviderTuple($primary, config('wood.ai_model'));

        // Fallback (only if configured and different from primary)
        $fallback = strtolower(config('wood.ai_fallback_provider', ''));
        if ($fallback && $fallback !== $primary && isset($this->providerMap()[$fallback])) {
            $chain[] = $this->resolveProviderTuple($fallback, config('wood.ai_fallback_model'));
        }

        return $chain;
    }

    /**
     * Resolve a provider key + optional model override into a [key, model, enum] tuple.
     */
    private function resolveProviderTuple(string $key, ?string $modelOverride): array
    {
        $prismProvider = $this->providerMap()[$key] ?? Provider::Anthropic;
        $defaults      = $this->defaultModels();
        $model         = ($modelOverride ?: null) ?: ($defaults[$key] ?? 'claude-opus-4-6');

        return [$key, $model, $prismProvider];
    }

    /**
     * Check if an API key is present in the Prism config for this provider.
     * Prevents making a call that will immediately 401.
     */
    private function hasApiKey(string $providerKey): bool
    {
        // Normalize alias
        $key = match ($providerKey) {
            'claude' => 'anthropic',
            'google' => 'gemini',
            default  => $providerKey,
        };

        $apiKey = config("prism.providers.{$key}.api_key", '');
        return !empty($apiKey);
    }

    // ──────────────────────────────────────────────────
    // Prompt Builder
    // ──────────────────────────────────────────────────

    /**
     * Build a structured prompt that combines OpenCV features with the image.
     * The LLM acts as a "second opinion" and must respond in JSON.
     */
    private function buildPrompt(array $fingerprint, array $speciesList): string
    {
        $speciesJson    = json_encode(array_column($speciesList, 'name'), JSON_PRETTY_PRINT);
        $woodType       = $fingerprint['wood_type'] ?? 'unknown';
        $grainPattern   = $fingerprint['grain_pattern'] ?? 'unknown';
        $poreType       = $fingerprint['pore_type'] ?? 'unknown';
        $colorHex       = $fingerprint['color_hex'] ?? 'unknown';
        $surfaceTexture = $fingerprint['surface_texture'] ?? 'unknown';
        $photoQuality   = $fingerprint['photo_quality'] ?? 'unknown';

        return <<<PROMPT
You are an expert wood anatomist and forensic wood identification specialist.

LOCAL OPENCV ANALYSIS already detected:
- Wood cut type: {$woodType}
- Grain pattern: {$grainPattern}
- Pore structure: {$poreType}
- Dominant color: {$colorHex}
- Surface texture: {$surfaceTexture}
- Photo quality: {$photoQuality}

KNOWN SPECIES DATABASE:
{$speciesJson}

TASK: Examine the wood image and identify the species. Cross-reference the OpenCV features above.

RESPOND ONLY IN THIS EXACT JSON FORMAT (no markdown, no extra text):
{
  "species_name": "Narra",
  "confidence_score": 87,
  "confidence_level": "high",
  "grain_pattern": "straight",
  "pore_type": "diffuse_porous",
  "color_description": "golden brown heartwood",
  "matches_opencv": true,
  "forensic_flag": false,
  "forensic_reason": null,
  "advice": "High confidence match based on pore structure and color."
}

Rules:
- species_name MUST be from the KNOWN SPECIES DATABASE list above, or "unknown"
- confidence_score: 0-100 integer
- confidence_level: very_high|high|medium|low|very_low
- matches_opencv: true if your answer agrees with the OpenCV features, false if they conflict
- forensic_flag: true ONLY if you detect painted surface, plywood, or illegal species substitution
PROMPT;
    }

    // ──────────────────────────────────────────────────
    // Response Parsing
    // ──────────────────────────────────────────────────

    /**
     * Parse the AI's JSON response into a structured result.
     * If the AI returns garbled output, degrade gracefully.
     */
    private function parseResponse(string $text, array $fingerprint, string $providerKey): array
    {
        // Extract JSON block (handle AI adding extra text despite instructions)
        preg_match('/\{.*\}/s', $text, $matches);
        $json = $matches[0] ?? $text;

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return array_merge($this->failedResponse('AI returned unparseable response'), [
                'ai_provider'  => $providerKey,
                'raw_response' => substr($text, 0, 500),
            ]);
        }

        // Resolve species name → DB id
        $speciesId   = null;
        $speciesName = $data['species_name'] ?? null;

        if ($speciesName && $speciesName !== 'unknown') {
            $species   = WoodSpecies::where('name', 'like', "%{$speciesName}%")->first();
            $speciesId = $species?->id;
        }

        $conflictFlag = $this->detectConflict($data, $fingerprint);

        return [
            'source'            => 'ai_vision',
            'ai_provider'       => $providerKey,
            'success'           => true,
            'species_id'        => $speciesId,
            'species_name'      => $speciesName,
            'confidence_score'  => (float)($data['confidence_score'] ?? 0),
            'confidence_level'  => $data['confidence_level'] ?? 'low',
            'grain_pattern'     => $data['grain_pattern'] ?? null,
            'pore_type'         => $data['pore_type'] ?? null,
            'color_description' => $data['color_description'] ?? null,
            'matches_opencv'    => (bool)($data['matches_opencv'] ?? true),
            'forensic_flag'     => (bool)($data['forensic_flag'] ?? false) || $conflictFlag,
            'forensic_reason'   => $conflictFlag
                ? 'AI and OpenCV results conflict — possible mislabeling or substitution.'
                : ($data['forensic_reason'] ?? null),
            'advice'            => $data['advice'] ?? null,
            'recommendation'    => $this->determineRecommendation($data, $conflictFlag),
        ];
    }

    // ──────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────

    /** Load species names from DB for the prompt context */
    private function loadSpeciesReference(): array
    {
        return WoodSpecies::select('id', 'name', 'hardness', 'is_protected')
            ->get()
            ->toArray();
    }

    /** Read image file and encode as Base64 */
    private function encodeImage(string $imagePath): string
    {
        $binary = file_get_contents($imagePath);
        if ($binary === false) {
            throw new RuntimeException("Cannot read image for AI evaluation: {$imagePath}");
        }
        return base64_encode($binary);
    }

    /** Detect MIME type from file extension */
    private function detectMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => 'image/jpeg',
        };
    }

    /**
     * Detect conflict between AI output and OpenCV features.
     * Example: OpenCV detects plywood but AI names a solid species → flag.
     */
    private function detectConflict(array $aiData, array $fingerprint): bool
    {
        if (isset($aiData['matches_opencv']) && $aiData['matches_opencv'] === false) {
            return true;
        }

        if (($fingerprint['wood_type'] ?? '') === 'plywood'
            && !empty($aiData['species_name'])
            && $aiData['species_name'] !== 'unknown') {
            return true;
        }

        return false;
    }

    private function determineRecommendation(array $data, bool $conflictFlag): string
    {
        if ($conflictFlag || ($data['forensic_flag'] ?? false)) {
            return 'flag';
        }

        return match ($data['confidence_level'] ?? 'low') {
            'very_high', 'high' => 'accept',
            'medium'            => 'verify',
            default             => 'retake',
        };
    }

    private function failedResponse(string $error): array
    {
        return [
            'source'           => 'ai_vision',
            'ai_provider'      => config('wood.ai_provider', 'none'),
            'success'          => false,
            'error'            => $error,
            'species_id'       => null,
            'species_name'     => null,
            'confidence_score' => 0,
            'confidence_level' => 'very_low',
            'recommendation'   => 'retake',
            'forensic_flag'    => false,
        ];
    }
}
