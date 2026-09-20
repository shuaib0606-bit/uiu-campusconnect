<?php

namespace App\Support;

use App\Models\Post;
use App\Models\PostLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Builds the preview card for the first link in a post.
 *
 * Everything here is best-effort and quiet. The site being linked to belongs
 * to somebody else: it can be slow, down, enormous, or serve something that is
 * not a web page at all. None of that is allowed to slow a post down
 * noticeably or stop it being published, so the timeout is short and every
 * failure ends with no preview rather than an error.
 */
class LinkPreview
{
    /** Seconds to wait on somebody else's server before giving up. */
    public const TIMEOUT = 4;

    /** Bytes of the response to read. Meta tags live in the head. */
    public const MAX_BYTES = 262144;

    /** Schemes worth following. */
    private const SCHEMES = ['http', 'https'];

    /**
     * Hosts that are not on the public internet. Fetching these would let
     * somebody use the site as a probe of whatever else is on its network,
     * so a link to one is recorded without being fetched.
     */
    private const PRIVATE_PATTERNS = [
        '/^localhost$/i',
        '/^127\./',
        '/^10\./',
        '/^192\.168\./',
        '/^172\.(1[6-9]|2[0-9]|3[01])\./',
        '/^169\.254\./',
        '/^\[?::1\]?$/',
        '/\.local$/i',
        '/\.internal$/i',
    ];

    /** The first http(s) address in a body, or null. */
    public static function firstUrl(?string $body): ?string
    {
        if (! $body) {
            return null;
        }

        if (! preg_match('~\bhttps?://[^\s<>"\']+~i', $body, $m)) {
            return null;
        }

        // Trailing punctuation is almost always sentence punctuation.
        return rtrim($m[0], '.,);:!?');
    }

    /**
     * Fetches and stores the preview for a post, if its body has a link.
     * Returns the row when one was made.
     */
    public static function attach(Post $post): ?PostLink
    {
        $url = static::firstUrl($post->body);

        if (! $url || ! static::isFetchable($url)) {
            return null;
        }

        $meta = static::fetch($url);

        $link = PostLink::updateOrCreate(
            ['post_id' => $post->id],
            [
                'url' => Str::limit($url, 990, ''),
                'title' => $meta['title'] ?? null,
                'description' => $meta['description'] ?? null,
                'image_url' => $meta['image'] ?? null,
                'site_name' => $meta['site_name'] ?? null,
            ]
        );

        // A card with no title, description or image says nothing the link
        // itself does not, so it is not kept.
        if (! $link->isUseful()) {
            $link->delete();

            return null;
        }

        return $link;
    }

    /** Whether this address is a public web page worth asking for. */
    public static function isFetchable(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), self::SCHEMES, true)) {
            return false;
        }

        $host = $parts['host'] ?? '';

        if ($host === '') {
            return false;
        }

        foreach (self::PRIVATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $host)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Open Graph tags where a page provides them, ordinary <title> and
     * <meta name="description"> where it does not.
     *
     * @return array<string, string>
     */
    private static function fetch(string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => 'UIU-CampusConnect/1.0 (+link preview)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            $type = strtolower($response->header('Content-Type'));

            if ($type && ! str_contains($type, 'html')) {
                return [];
            }

            $html = substr($response->body(), 0, self::MAX_BYTES);
        } catch (\Throwable) {
            // Unreachable, refused, timed out, bad TLS. No preview, no noise.
            return [];
        }

        return array_filter([
            'title' => static::meta($html, ['og:title', 'twitter:title']) ?: static::titleTag($html),
            'description' => static::meta($html, ['og:description', 'twitter:description', 'description']),
            'image' => static::absolute(static::meta($html, ['og:image', 'twitter:image']), $url),
            'site_name' => static::meta($html, ['og:site_name']),
        ]);
    }

    /** @param list<string> $names */
    private static function meta(string $html, array $names): ?string
    {
        foreach ($names as $name) {
            $quoted = preg_quote($name, '~');

            // property= and name= are both used in the wild, and the two
            // attributes appear in either order.
            $patterns = [
                '~<meta[^>]+(?:property|name)\s*=\s*["\']'.$quoted.'["\'][^>]*content\s*=\s*["\']([^"\']*)["\']~i',
                '~<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*(?:property|name)\s*=\s*["\']'.$quoted.'["\']~i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $m) && trim($m[1]) !== '') {
                    return static::clean($m[1]);
                }
            }
        }

        return null;
    }

    private static function titleTag(string $html): ?string
    {
        return preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)
            ? static::clean($m[1])
            : null;
    }

    private static function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $value)), 400, '');
    }

    /** Turns a relative og:image into something a browser can load. */
    private static function absolute(?string $image, string $base): ?string
    {
        if (! $image) {
            return null;
        }

        if (preg_match('~^https?://~i', $image)) {
            return Str::limit($image, 990, '');
        }

        $parts = parse_url($base);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $root = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return Str::limit($root.'/'.ltrim($image, '/'), 990, '');
    }
}
