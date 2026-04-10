<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that an uploaded image is exactly one of the allowed dimensions.
 *
 * Used for admin reference image uploads to enforce consistent resolution
 * across the species library. Allowed: 1200×1200 or 1600×1600 pixels.
 *
 * Why exact square dimensions?
 *  - OpenCV scripts work best with consistent, square reference images
 *  - Prevents low-quality or oddly-cropped photos from polluting the library
 *  - Ensures the CIEDE2000 center-crop samples the correct region
 */
class AllowedImageDimension implements ValidationRule
{
    private const ALLOWED = [
        [1200, 1200],
        [1600, 1600],
    ];

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            $fail('The :attribute must be a valid uploaded file.');
            return;
        }

        $size = @getimagesize($value->getRealPath());

        if ($size === false) {
            $fail('The :attribute could not be read. Please upload a valid JPEG, PNG, or WebP image.');
            return;
        }

        [$width, $height] = $size;

        foreach (self::ALLOWED as [$allowedW, $allowedH]) {
            if ($width === $allowedW && $height === $allowedH) {
                return; // dimension matches — passes validation
            }
        }

        $allowedList = implode(' or ', array_map(
            fn($d) => "{$d[0]}×{$d[1]}",
            self::ALLOWED
        ));

        $fail("Reference images must be exactly {$allowedList} pixels. Your image is {$width}×{$height}px. Please resize and re-upload.");
    }
}
