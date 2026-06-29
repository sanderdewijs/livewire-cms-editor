<?php

namespace Degrinthorst\CmsEditor\Support;

/**
 * Walks a ProseMirror-JSON document and collects the Media ids referenced by
 * its `mediaImage` nodes. Single source of truth for the adopt trait, the
 * prune command, and (potentially) the renderer.
 */
class DocumentMediaIds
{
    /**
     * @param  array<string, mixed>|string|null  $document  ProseMirror JSON (array or json string)
     * @return array<int, int>  unique referenced media ids
     */
    public static function extract(array|string|null $document): array
    {
        $json = is_string($document) ? json_decode($document, true) : $document;

        if (empty($json) || ! is_array($json)) {
            return [];
        }

        $ids = [];
        static::walk($json, $ids);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, int>  $ids
     */
    protected static function walk(array $node, array &$ids): void
    {
        if (($node['type'] ?? null) === 'mediaImage') {
            $mediaId = $node['attrs']['mediaId'] ?? null;

            if ($mediaId !== null) {
                $ids[] = (int) $mediaId;
            }
        }

        if (! empty($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $child) {
                if (is_array($child)) {
                    static::walk($child, $ids);
                }
            }
        }
    }
}
