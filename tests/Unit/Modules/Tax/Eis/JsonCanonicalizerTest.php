<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Services\Eis\JsonCanonicalizer;

/**
 * RFC 8785 JCS conformance — every byte of the canonical form is signed,
 * so two systems that produce different bytes for the same logical payload
 * will produce different signatures and BIR will reject one of them.
 */
beforeEach(function () {
    $this->c = new JsonCanonicalizer();
});

it('sorts object keys lexicographically (UTF-16 code-unit order)', function () {
    $payload = ['banana' => 1, 'apple' => 2, 'cherry' => 3];
    expect($this->c->canonicalize($payload))
        ->toBe('{"apple":2,"banana":1,"cherry":3}');
});

it('sorts nested object keys recursively', function () {
    $payload = [
        'z' => ['b' => 1, 'a' => 2],
        'a' => ['y' => 3, 'x' => 4],
    ];
    expect($this->c->canonicalize($payload))
        ->toBe('{"a":{"x":4,"y":3},"z":{"a":2,"b":1}}');
});

it('preserves array element order (lists are NOT sorted)', function () {
    // RFC 8785 § 3.2.3 — only objects are reordered, arrays keep insertion order
    $payload = ['lines' => [3, 1, 2]];
    expect($this->c->canonicalize($payload))->toBe('{"lines":[3,1,2]}');
});

it('does not escape forward slashes (BIR URLs would break otherwise)', function () {
    $payload = ['url' => 'https://eis.bir.gov.ph/invoice/ABC'];
    expect($this->c->canonicalize($payload))
        ->toBe('{"url":"https://eis.bir.gov.ph/invoice/ABC"}');
});

it('preserves Unicode without ASCII escaping', function () {
    $payload = ['name' => 'GARCÍA, ROBERTO'];
    expect($this->c->canonicalize($payload))
        ->toBe('{"name":"GARCÍA, ROBERTO"}');
});

it('rejects float values to prevent ES6 round-trip ambiguity', function () {
    // RFC 8785 § 3.2.2.3 mandates ES6 shortest-round-trip for numbers; PHP
    // can't implement that portably, so we ban floats at the canonicalizer.
    expect(fn () => $this->c->canonicalize(['amount' => 1234.56]))
        ->toThrow(InvalidArgumentException::class, 'Float values are forbidden');
});

it('rejects floats deep inside nested structures', function () {
    expect(fn () => $this->c->canonicalize([
        'lines' => [
            ['n' => 1, 'price' => '100.00'],
            ['n' => 2, 'price' => 99.5],            // ← buried float
        ],
    ]))->toThrow(InvalidArgumentException::class);
});

it('accepts decimal strings, integers, booleans, and nulls', function () {
    $bytes = $this->c->canonicalize([
        'amount'   => '1234.5678',
        'quantity' => 100,
        'is_vat'   => true,
        'is_pwd'   => false,
        'note'     => null,
    ]);
    expect($bytes)->toBe('{"amount":"1234.5678","is_pwd":false,"is_vat":true,"note":null,"quantity":100}');
});

it('produces a stable SHA-256 digest', function () {
    $a = ['z' => 1, 'a' => 2];
    $b = ['a' => 2, 'z' => 1];          // same payload, different insertion order
    expect($this->c->digest($a))->toBe($this->c->digest($b))
        ->and($this->c->digest($a))->toHaveLength(64);
});

it('produces different digests for different payloads', function () {
    expect($this->c->digest(['amount' => '100.00']))
        ->not->toBe($this->c->digest(['amount' => '100.01']));
});

it('handles empty objects and empty arrays distinctly', function () {
    expect($this->c->canonicalize(['lines' => []]))->toBe('{"lines":[]}')
        ->and($this->c->canonicalize(['meta'  => (object) []]))->toBe('{"meta":{}}');
});
