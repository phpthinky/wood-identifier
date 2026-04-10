<?php

namespace App\Services\Wood;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Module 3 — Image Decoder Service
 *
 * Responsibility: Accept raw Base64 or a multipart file upload,
 * validate it, and write it to a temp path the OpenCV scripts can read.
 *
 * Why a dedicated service?
 * - React mobile sends Base64 strings (camera capture)
 * - Blade/web form sends multipart FormData
 * - OpenCV scripts need an absolute file path
 * - This service bridges the gap — callers never care about the source format.
 */
class ImageDecoderService
{
    // Max file size: 10 MB
    private const MAX_BYTES = 10 * 1024 * 1024;

    // Allowed MIME types
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Decode a raw Base64 string → write to temp storage → return absolute path.
     *
     * Expected input formats:
     *   "data:image/jpeg;base64,/9j/4AAQ..."  (data URI — from React camera)
     *   "/9j/4AAQ..."                          (raw base64 — stripped URI)
     *
     * @throws RuntimeException on invalid input
     */
    public function decodeBase64(string $base64Input): string
    {
        // Strip the data URI prefix if present
        $raw = $this->stripDataUri($base64Input, $mimeFromUri);

        // Decode
        $binary = base64_decode($raw, strict: true);
        if ($binary === false) {
            throw new RuntimeException('Invalid Base64 string: cannot decode.');
        }

        // Validate size
        if (strlen($binary) > self::MAX_BYTES) {
            throw new RuntimeException('Image exceeds maximum allowed size of 10 MB.');
        }

        // Detect MIME from binary header
        $mime = $this->detectMime($binary);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("Unsupported image type: {$mime}. Allowed: JPEG, PNG, WebP.");
        }

        return $this->writeToDisk($binary, $mime);
    }

    /**
     * Accept a Laravel UploadedFile (Blade/FormData) → move to temp storage → return absolute path.
     *
     * @throws RuntimeException on invalid file
     */
    public function decodeUploadedFile(\Illuminate\Http\UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Image exceeds maximum allowed size of 10 MB.');
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("Unsupported image type: {$mime}. Allowed: JPEG, PNG, WebP.");
        }

        $ext      = $this->extFromMime($mime);
        $filename = 'scan_' . Str::uuid() . '.' . $ext;
        $path     = $file->storeAs('scans/temp', $filename, 'local');

        return Storage::disk('local')->path($path);
    }

    /**
     * Strip the data URI prefix and capture the MIME type from it.
     * Returns the raw base64 string.
     */
    private function stripDataUri(string $input, ?string &$mime): string
    {
        if (str_starts_with($input, 'data:')) {
            // data:image/jpeg;base64,<data>
            [$header, $data] = explode(',', $input, 2);
            preg_match('/data:([^;]+);base64/', $header, $m);
            $mime = $m[1] ?? null;
            return $data;
        }

        $mime = null;
        return $input;
    }

    /**
     * Read the binary magic bytes to determine the actual MIME type.
     * Prevents clients from lying about the content type.
     */
    private function detectMime(string $binary): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($binary) ?: 'application/octet-stream';
    }

    /**
     * Write binary data to local temp storage and return the absolute path.
     */
    private function writeToDisk(string $binary, string $mime): string
    {
        $ext      = $this->extFromMime($mime);
        $filename = 'scan_' . Str::uuid() . '.' . $ext;
        $relative = 'scans/temp/' . $filename;

        Storage::disk('local')->put($relative, $binary);

        return Storage::disk('local')->path($relative);
    }

    private function extFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }

    /**
     * Delete a temp file after scanning is complete.
     * Call this in the controller's finally block.
     */
    public function cleanup(string $absolutePath): void
    {
        $relative = str_replace(
            Storage::disk('local')->path('') . DIRECTORY_SEPARATOR,
            '',
            $absolutePath
        );

        if (Storage::disk('local')->exists($relative)) {
            Storage::disk('local')->delete($relative);
        }
    }
}
