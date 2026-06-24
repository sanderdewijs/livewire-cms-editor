<?php

namespace Degrinthorst\CmsEditor\Extensions;

use Tiptap\Core\Node;

/**
 * PHP-side mirror of the JS `mediaImage` TipTap node (resources/js/extensions).
 * Keeps server rendering (tiptap-php) in sync with the client schema so the
 * preview and the stored output match (Onderhoudsrisico #3).
 */
class MediaImage extends Node
{
    public static $name = 'mediaImage';

    public function addAttributes(): array
    {
        return [
            'mediaId' => ['default' => null],
            'src' => ['default' => null],
            'alt' => ['default' => ''],
            'width' => ['default' => null],
            'height' => ['default' => null],
            'class' => ['default' => null],
            'style' => ['default' => null],
        ];
    }

    public function parseHTML(): array
    {
        return [
            ['tag' => 'img[data-media-id]'],
        ];
    }

    public function renderHTML($node): array
    {
        $attrs = $node->attrs ?? new \stdClass();

        $htmlAttrs = array_filter([
            'src' => $attrs->src ?? null,
            'alt' => $attrs->alt ?? '',
            'width' => $attrs->width ?? null,
            'height' => $attrs->height ?? null,
            'class' => $attrs->class ?? null,
            'style' => $attrs->style ?? null,
            'data-media-id' => $attrs->mediaId ?? null,
            'loading' => 'lazy',
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'img',
            $htmlAttrs,
        ];
    }
}
