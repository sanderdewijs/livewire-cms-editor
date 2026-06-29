<?php

namespace Degrinthorst\CmsEditor\Concerns;

use Degrinthorst\CmsEditor\Support\DocumentMediaIds;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Optional: gives the host model ownership of the editor images it references
 * (ADR-009). On save, any bucket-owned Media referenced in the document is
 * re-parented onto this model — so deleting the model cascades its images and
 * the picker shows them in the shared pool.
 *
 * Add this trait to your model when you want per-article ownership. Without it
 * (or with `upload_binding=model`), images simply stay on the shared bucket.
 */
trait AdoptsEditorMedia
{
    public static function bootAdoptsEditorMedia(): void
    {
        static::saved(function ($model): void {
            $jsonColumn = config('cms-editor.columns.json', 'body');

            // Nothing to adopt unless the document was (re)written.
            if (! $model->wasRecentlyCreated && ! $model->wasChanged($jsonColumn)) {
                return;
            }

            $ids = DocumentMediaIds::extract($model->{$jsonColumn} ?? []);

            if ($ids === []) {
                return;
            }

            $bucketModel = config('cms-editor.upload_bucket.model');

            if (! $bucketModel) {
                return;
            }

            // Only ever adopt from the SAVER'S OWN bucket. Scoping to the current
            // scope's bucket id (not just model_type=bucket) stops a crafted
            // document from re-parenting another user's pending uploads onto this
            // host — a client controls the mediaIds, but not whose bucket they
            // live in. No bucket for this scope (e.g. an unauthenticated/queued
            // save) => nothing of ours to adopt.
            $bucketId = $bucketModel::currentKey();

            if (! $bucketId) {
                return;
            }

            Media::query()
                ->whereIn('id', $ids)
                ->where('collection_name', config('cms-editor.collection', 'article_body'))
                ->where('model_type', (new $bucketModel)->getMorphClass())
                ->where('model_id', $bucketId)
                ->get()
                ->each(function (Media $media) use ($model): void {
                    // Re-point the morph IN PLACE. Do NOT use Media::move(): it
                    // copies + force-deletes the record, minting a new id and
                    // invalidating every mediaId already stored in documents.
                    $media->model()->associate($model);
                    $media->save();
                });
        });
    }
}
