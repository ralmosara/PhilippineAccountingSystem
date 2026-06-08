<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services\Eis;

use InvalidArgumentException;
use JsonException;

/**
 * RFC 8785 JSON Canonicalization Scheme (JCS), pragmatic subset.
 *
 * BIR's EIS verifier needs a deterministic byte stream to recompute the
 * signature against. Two systems that "see the same payload" must produce
 * the same bytes — so we sort object keys, strip insignificant whitespace,
 * and pin the JSON encoding flags.
 *
 * Caller MUST submit monetary values as STRINGS, not floats. Float ↔ ES6
 * shortest-round-trip serialization (RFC 8785 § 3.2.2.3) cannot be implemented
 * portably in PHP and is undefined for the BIR amounts we sign. Strings sidestep
 * that entirely and match how the rest of the domain represents money.
 *
 *   ✓  ["amount" => "1234.56"]
 *   ✗  ["amount" => 1234.56]   ← will throw
 */
final class JsonCanonicalizer
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function canonicalize(array $payload): string
    {
        $this->assertNoFloats($payload, '$');
        $sorted = $this->recursivelySortKeys($payload);

        try {
            return json_encode(
                $sorted,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                'Payload is not JSON-encodable: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * SHA-256 digest of the canonical bytes. Used as `payload_hash` for
     * audit cross-checks and as the message digest passed to the signer.
     */
    public function digest(array $payload): string
    {
        return hash('sha256', $this->canonicalize($payload));
    }

    /**
     * Recursively sort associative array keys ascending (UTF-16 code-unit
     * lexicographic; PHP's `ksort(SORT_STRING)` matches for ASCII-only keys,
     * which the BIR schema uses).
     *
     * Indexed arrays preserve element order (JCS § 3.2.3).
     */
    private function recursivelySortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);

        if ($isList) {
            return array_map(fn ($v) => $this->recursivelySortKeys($v), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $k => $v) {
            $value[$k] = $this->recursivelySortKeys($v);
        }
        return $value;
    }

    private function assertNoFloats(mixed $value, string $path): void
    {
        if (is_float($value)) {
            throw new InvalidArgumentException(
                "Float values are forbidden in EIS payloads (path: {$path}). "
                .'Pass monetary amounts as decimal strings (e.g. "1234.56").',
            );
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $this->assertNoFloats($v, $path.'.'.$k);
            }
        }
    }
}
