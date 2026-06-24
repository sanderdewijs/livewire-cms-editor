<?php

namespace Degrinthorst\CmsEditor\Support;

/**
 * Allowlist sanitizer for rendered editor HTML (ADR-008).
 *
 * This is a deliberately small, dependency-free implementation. For hardened
 * production use, swap this for HTMLPurifier (mews/purifier) by binding a
 * different implementation in the container — see README.
 */
class ContentSanitizer
{
    public function clean(string $html): string
    {
        if (! config('cms-editor.sanitize.enabled', true)) {
            return $html;
        }

        // Strip <script>/<style> blocks outright.
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        // Strip inline event handlers (onerror=, onclick=, ...).
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;

        // Neutralise javascript: URLs in href/src.
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2#i', '$1="#"', $html) ?? $html;

        // Filter inline styles down to the allowed property allowlist.
        $allowedStyles = config('cms-editor.sanitize.allowed_styles', []);
        $html = $this->filterStyles($html, $allowedStyles);

        return $html;
    }

    protected function filterStyles(string $html, array $allowed): string
    {
        if (empty($allowed)) {
            return $html;
        }

        return preg_replace_callback(
            '#\sstyle\s*=\s*"([^"]*)"#i',
            function (array $m) use ($allowed): string {
                $kept = [];

                foreach (explode(';', $m[1]) as $decl) {
                    if (! str_contains($decl, ':')) {
                        continue;
                    }

                    [$prop, $value] = array_map('trim', explode(':', $decl, 2));

                    // Drop CSS expressions / url(javascript:) tricks.
                    if (preg_match('#expression\s*\(|url\s*\(\s*[\'"]?\s*javascript:#i', $value)) {
                        continue;
                    }

                    if (in_array(strtolower($prop), $allowed, true)) {
                        $kept[] = "{$prop}: {$value}";
                    }
                }

                return $kept ? ' style="' . implode('; ', $kept) . '"' : '';
            },
            $html
        ) ?? $html;
    }
}
