<?php

namespace Degrinthorst\CmsEditor\Support;

use Degrinthorst\CmsEditor\Extensions\MediaImage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tiptap\Editor as TiptapEditor;

/**
 * Renders a stored ProseMirror-JSON document to HTML (ADR-003).
 *
 * Re-resolves each image's <src> from its mediaId at render time so that
 * renamed/replaced Media always render their current URL, then hands the
 * output to the sanitizer (ADR-008).
 */
class ContentRenderer
{
    public function __construct(
        protected ContentSanitizer $sanitizer,
    ) {}

    /**
     * @param  array|string  $document  ProseMirror JSON (array or json string)
     */
    public function toHtml(array|string $document): string
    {
        $json = is_string($document) ? json_decode($document, true) : $document;

        if (empty($json)) {
            return '';
        }

        $json = $this->reresolveImages($json);

        // StarterKit is loaded by default; passing a custom array replaces the
        // defaults, so we re-add it explicitly alongside our custom node.
        $html = (new TiptapEditor([
            'extensions' => [
                new \Tiptap\Extensions\StarterKit(),
                new \Tiptap\Marks\Underline(), // not in StarterKit; mirrors the JS schema
                new \Tiptap\Marks\Link(),
                new MediaImage(), // PHP-side mirror of the JS custom node
            ],
        ]))->setContent($json)->getHTML();

        return $this->sanitizer->clean($html);
    }

    /**
     * Walk the document tree and refresh image src/alt from the Media record.
     * Gracefully degrades when a Media record was deleted (Onderhoudsrisico #7).
     */
    protected function reresolveImages(array $node): array
    {
        if (($node['type'] ?? null) === 'mediaImage') {
            $mediaId = $node['attrs']['mediaId'] ?? null;

            if ($mediaId && $media = Media::query()->find($mediaId)) {
                $conversion = config('cms-editor.default_conversion');

                $node['attrs']['src'] = $conversion && $media->hasGeneratedConversion($conversion)
                    ? $media->getUrl($conversion)
                    : $media->getUrl();

                // Intrinsic data lives on Media (ADR-004); presentation attrs
                // (width/height/class/style) are left untouched on the node.
                $node['attrs']['alt'] = $media->getCustomProperty('alt', $node['attrs']['alt'] ?? '');
            } elseif ($mediaId) {
                // Media gone: blank the src so the renderer can drop/placeholder it.
                $node['attrs']['src'] = null;
            }
        }

        if (! empty($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn ($child) => is_array($child) ? $this->reresolveImages($child) : $child,
                $node['content']
            );
        }

        return $node;
    }
}
