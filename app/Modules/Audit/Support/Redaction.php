<?php

declare(strict_types=1);

namespace App\Modules\Audit\Support;

/**
 * The single definition of what counts as a secret, and what to write instead.
 *
 * CLAUDE.md is absolute: no token, key, password, private key, client-secret
 * value, bearer token, production connection string, `.env` value or customer
 * data extract may reach a committed file, a log, a screenshot or a chat
 * summary. Release 1 section 3 says the same of the audit trail.
 *
 * That rule is only as good as its weakest caller, so there is exactly one
 * implementation of it and every writer - the audit trail, the health probe,
 * the diagnostics screen and, from gate 5, the integration clients - goes
 * through this class rather than each deciding for itself.
 *
 * Two jobs:
 *
 *  - `summarise()` turns a structured before/after into something that records
 *    WHAT CHANGED without recording the value that changed.
 *  - `scrub()` sweeps free text - an exception message, a provider error - for
 *    things that look like credentials, because a message nobody meant to be
 *    sensitive is the usual way one escapes.
 *
 * The design bias is to over-redact. A trail that says "[redacted]" where it
 * did not strictly need to costs an investigator a question; a trail that
 * printed a bearer token costs a credential rotation and a disclosure.
 */
class Redaction
{
    /** What replaces a sensitive value. One string, so it is greppable. */
    public const PLACEHOLDER = '[redacted]';

    /**
     * Substrings that make a KEY sensitive, matched case-insensitively against
     * the key with separators removed, so `client_secret`, `clientSecret` and
     * `CLIENT-SECRET` are all caught by `clientsecret`.
     *
     * Deliberately broad. `key` catches `api_key` and also catches `sort_key`,
     * which is a false positive we accept: the cost is a redacted sort order.
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password', 'passwd', 'pwd',
        'secret', 'token', 'credential', 'key',
        'authorization', 'authorisation', 'auth',
        'cookie', 'session',
        'private', 'certificate', 'cert',
        'signature', 'salt', 'nonce',
        'connectionstring', 'dsn',
        'clientassertion',
    ];

    /**
     * Free-text patterns that look like a credential wherever they appear.
     *
     * Order matters: the JWT pattern runs before the generic long-token one so
     * a JWT is reported as a JWT rather than as an anonymous blob.
     */
    private const TEXT_PATTERNS = [
        /* A bearer or basic credential in an Authorization header. */
        '/\b(bearer|basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i',
        /* A JSON Web Token: three base64url segments. */
        '/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9._-]{8,}/',
        /* A key or password spelled out inline: `password=...`, `secret: ...`. */
        '/\b(password|passwd|pwd|secret|token|api[_-]?key|client[_-]?secret)\b\s*[=:]\s*\S+/i',
        /* A driver-style connection string. */
        '/\b[a-z]+:\/\/[^\s:@]+:[^\s@]+@\S+/i',
    ];

    /**
     * Whether a key's VALUE must never be recorded.
     */
    public static function isSensitiveKey(string $key): bool
    {
        $normalised = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalised, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A recordable summary of a structured value.
     *
     * Sensitive keys become the placeholder. Long strings become a description
     * plus a truncated SHA-256, which is enough to answer "did this change" and
     * "is this the same value as that one" without holding the value itself.
     *
     * The depth limit is not decoration: an audit writer handed a deeply nested
     * structure would otherwise write an unbounded blob into a column that is
     * supposed to hold a summary.
     *
     * @param  array<array-key, mixed>|null  $value
     * @return array<array-key, mixed>|null
     */
    public static function summarise(?array $value, int $depth = 0): ?array
    {
        if ($value === null) {
            return null;
        }

        $summary = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $summary[$key] = self::PLACEHOLDER;

                continue;
            }

            $summary[$key] = self::summariseValue($item, $depth);
        }

        return $summary;
    }

    /**
     * Remove anything credential-shaped from free text.
     *
     * Applied to every message that reaches a log, a screen or the trail from
     * outside this application: an exception, an HTTP error body, a driver
     * message. Those are the strings nobody audited and the usual way a secret
     * escapes.
     */
    public static function scrub(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        foreach (self::TEXT_PATTERNS as $pattern) {
            $text = (string) preg_replace($pattern, self::PLACEHOLDER, $text);
        }

        /*
         * Bound the length as well. A provider that answers an error with a
         * whole HTML page must not put a whole HTML page in the trail.
         */
        return mb_strlen($text) > 1000 ? mb_substr($text, 0, 1000).' [truncated]' : $text;
    }

    /**
     * A short, non-reversible fingerprint of a value.
     *
     * Used where the trail must be able to say "this is the same value as
     * before" without holding it. Truncated because the full digest of a short,
     * low-entropy value is closer to reversible than it looks, and 16 hex
     * characters are ample for equality within one trail.
     */
    public static function fingerprint(string $value): string
    {
        return 'sha256:'.substr(hash('sha256', $value), 0, 16);
    }

    /**
     * One value, summarised. Recurses into arrays up to the depth limit.
     */
    private static function summariseValue(mixed $item, int $depth): mixed
    {
        if ($item === null || is_bool($item) || is_int($item) || is_float($item)) {
            return $item;
        }

        if (is_array($item)) {
            /* Three levels is enough to read a configuration change and shallow
             * enough that the column stays a summary. */
            return $depth >= 3 ? '[nested, '.count($item).' entries]' : self::summarise($item, $depth + 1);
        }

        if (! is_string($item)) {
            /* An object or a resource has no safe generic rendering, so its
             * type is all that is recorded. */
            return '['.get_debug_type($item).']';
        }

        $item = self::scrub($item) ?? '';

        /*
         * A short string is recorded as itself, which is what makes a trail
         * readable: "name: Acme Ltd" beats a hash. A long one is fingerprinted,
         * because length is where pasted secrets and pasted customer data live.
         */
        return mb_strlen($item) <= 120
            ? $item
            : '[text, '.mb_strlen($item).' characters, '.self::fingerprint($item).']';
    }
}
