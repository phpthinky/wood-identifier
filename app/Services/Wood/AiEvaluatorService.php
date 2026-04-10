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
 * Uses Laravel Prism (prism-php/prism) — the built-in AI SDK in Laravel 13
 * to query Claude Vision or Gemini Vision for a "second opinion".
 *
 * Flow:
 *   1. Build a context-aware prompt with the OpenCV feature data
 *   2. Send the image + prompt to the configured Vision LLM
 *   3. Parse the JSON response for species match + confidence
 *   4. Return structured result back to the WoodBrainService
 *
 * Why structured prompt?
 * - Forces the LLM to respond in JSON — parseable, not free text
 * - Injects OpenCV features so the LLM confirms (or corrects) our local brain
 * - Catches "hallucinations" — if AI says Oak but features show Mahogany → forensic flag
 */
class AiEvaluatorService
{
    /**
     * Ask the AI Vision model to identify the wood species.
     *
     * @param  string $imagePath     Absolute path to the image
     * @param  array  $fingerprint   OpenCV feature fingerprint (context for the AI)
     * @return array                 Structured AI result
     */
    public function evaluate(string $imagePath, array $fingerprint): array
    {
        $species    = $this->loadSpeciesReference();
        $prompt     = $this->buildPrompt($fingerprint, $species);
        $imageData  = $this->encodeImage($imagePath);

        try {
            $response = Prism::text()
                ->using(Provider::Anthropic, 'claude-opus-4-6')
                ->withMessages([
                    new UserMessage(
                        $prompt,
                        additionalContent: [
                            Image::fromBase64($imageData, $this->detectMime($imagePath)),
                        ]
                    ),
                ])
                ->withMaxTokens(512)
                ->asText();

            return $this->parseResponse($response->text, $fingerprint);

        } catch (\Throwable $e) {
            // AI call failed — return a graceful degraded result, not a 500
            return [
                'source'          => 'ai_vision',
                'ai_provider'     => 'claude',
                'success'         => false,
                'error'           => $e->getMessage(),
                'species_id'      => null,
                'species_name'    => null,
                'confidence_score' => 0,
                'confidence_level' => 'very_low',
                'recommendation'  => 'retake',
                'forensic_flag'   => false,
            ];
        }
    }

    /**
     * Build a structured prompt that combines OpenCV features with the image.
     * The LLM acts as a "second opinion" that must respond in JSON.
     */
    private function buildPrompt(array $fingerprint, array $speciesList): string
    {
        $speciesJson  = json_encode(array_column($speciesList, 'name'), JSON_PRETTY_PRINT);
        $woodType     = $fingerprint['wood_type'] ?? 'unknown';
        $grainPattern = $fingerprint['grain_pattern'] ?? 'unknown';
        $poreType     = $fingerprint['pore_type'] ?? 'unknown';
        $colorHex     = $fingerprint['color_hex'] ?? 'unknown';
        $surfaceTexture = $fingerprint['surface_texture'] ?? 'unknown';

        return <<<PROMPT
You are an expert wood anatomist and forensic wood identification specialist.

LOCAL OPENCV ANALYSIS already detected:
- Wood cut type: {$woodType}
- Grain pattern: {$grainPattern}
- Pore structure: {$poreType}
- Dominant color: {$colorHex}
- Surface texture: {$surfaceTexture}
- Photo quality: {$fingerprint['photo_quality']}

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

    /** Detect MIME type from file extension for Prism Image */
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
     * Parse the AI's JSON response into a structured result.
     * If the AI returns garbled output, degrade gracefully.
     */
    private function parseResponse(string $text, array $fingerprint): array
    {
        // Extract JSON block (handle case where AI adds extra text despite instructions)
        preg_match('/\{.*\}/s', $text, $matches);
        $json = $matches[0] ?? $text;

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return [
                'source'           => 'ai_vision',
                'ai_provider'      => 'claude',
                'success'          => false,
                'error'            => 'AI returned unparseable response',
                'raw_response'     => substr($text, 0, 500),
                'species_id'       => null,
                'species_name'     => null,
                'confidence_score' => 0,
                'confidence_level' => 'very_low',
                'recommendation'   => 'retake',
                'forensic_flag'    => false,
            ];
        }

        // Resolve species name → id
        $speciesId = null;
        $speciesName = $data['species_name'] ?? null;

        if ($speciesName && $speciesName !== 'unknown') {
            $species   = WoodSpecies::where('name', 'like', "%{$speciesName}%")->first();
            $speciesId = $species?->id;
        }

        // Detect conflict between AI and OpenCV (forensic hallucination check)
        $conflictFlag = $this->detectConflict($data, $fingerprint);

        return [
            'source'           => 'ai_vision',
            'ai_provider'      => 'claude',
            'success'          => true,
            'species_id'       => $speciesId,
            'species_name'     => $speciesName,
            'confidence_score' => (float)($data['confidence_score'] ?? 0),
            'confidence_level' => $data['confidence_level'] ?? 'low',
            'grain_pattern'    => $data['grain_pattern'] ?? null,
            'pore_type'        => $data['pore_type'] ?? null,
            'color_description' => $data['color_description'] ?? null,
            'matches_opencv'   => (bool)($data['matches_opencv'] ?? true),
            'forensic_flag'    => (bool)($data['forensic_flag'] ?? false) || $conflictFlag,
            'forensic_reason'  => $conflictFlag
                ? 'AI and OpenCV results conflict — possible mislabeling or substitution.'
                : ($data['forensic_reason'] ?? null),
            'advice'           => $data['advice'] ?? null,
            'recommendation'   => $this->determineRecommendation($data, $conflictFlag),
        ];
    }

    /**
     * Detect conflict between AI output and OpenCV features.
     * Example: AI says "hardwood" but OpenCV found plywood layers → forensic flag.
     */
    private function detectConflict(array $aiData, array $fingerprint): bool
    {
        // If AI says matches_opencv=false → conflict
        if (isset($aiData['matches_opencv']) && $aiData['matches_opencv'] === false) {
            return true;
        }

        // If OpenCV says plywood but AI identifies a solid species → conflict
        if (($fingerprint['wood_type'] ?? '') === 'plywood' && !empty($aiData['species_name']) && $aiData['species_name'] !== 'unknown') {
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
}
