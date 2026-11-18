<?php

namespace App\Support;

/**
 * Inline Tabler SVG icons from resources/icons (copied there by `npm run icons`).
 */
class Icon
{
    /** @var array<string,string> */
    protected static array $cache = [];

    public static function svg(string $name, string $class = 'size-5', array $attributes = []): string
    {
        $raw = static::$cache[$name] ??= is_file($file = resource_path("icons/{$name}.svg"))
            ? file_get_contents($file)
            : '';

        if ($raw === '') {
            return app()->hasDebugModeEnabled() ? "<!-- missing icon: {$name} (run npm run icons) -->" : '';
        }

        $extra = '';
        foreach ($attributes as $key => $value) {
            $extra .= ' ' . $key . '="' . e($value) . '"';
        }

        // Swap Tabler's fixed size and classes for ours; decorative unless given a label.
        $a11y = isset($attributes['aria-label']) ? 'role="img"' : 'aria-hidden="true"';

        return preg_replace(
            ['/\s(?:width|height)="24"/', '/\sclass="[^"]*"/', '/<svg\b/'],
            ['', '', '<svg class="' . e($class) . '" ' . $a11y . ' focusable="false"' . $extra],
            $raw
        );
    }
}
