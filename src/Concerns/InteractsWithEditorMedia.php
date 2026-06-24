<?php

namespace Degrinthorst\CmsEditor\Concerns;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Adds editor-media behaviour to a host model. Expects the model to also use
 * Spatie\MediaLibrary\InteractsWithMedia.
 *
 * @mixin \Spatie\MediaLibrary\InteractsWithMedia
 */
trait InteractsWithEditorMedia
{
    public function editorMediaCollection(): string
    {
        return config('cms-editor.collection', 'article_body');
    }

    /**
     * Register the editor media collection. Call from registerMediaCollections().
     */
    public function registerEditorMediaCollection(): void
    {
        $collection = $this->addMediaCollection($this->editorMediaCollection());

        if ($conversion = config('cms-editor.default_conversion')) {
            // Host app is responsible for defining the actual conversion;
            // this just documents the intended default.
            $collection->useDisk(config('cms-editor.disk', config('media-library.disk_name')));
        }
    }

    /**
     * Media inserted into this model's body, newest first.
     */
    public function editorMedia()
    {
        return $this->getMedia($this->editorMediaCollection());
    }

    /**
     * Resolve a single editor Media record by id, scoped to this model's
     * collection (defensive: returns null if not found / not ours).
     */
    public function resolveEditorMedia(int|string $mediaId): ?Media
    {
        return $this->getMedia($this->editorMediaCollection())
            ->firstWhere('id', $mediaId);
    }
}
