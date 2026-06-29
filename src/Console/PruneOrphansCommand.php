<?php

namespace Degrinthorst\CmsEditor\Console;

use Degrinthorst\CmsEditor\Support\DocumentMediaIds;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Deletes editor media that no documents reference any more (ADR-009,
 * Onderhoudsrisico #2):
 *
 *   - host-owned media that was adopted and later de-referenced (always pruned);
 *   - bucket-owned uploads that were never inserted, once older than the TTL
 *     grace window (so in-progress uploads are never yanked mid-edit).
 */
class PruneOrphansCommand extends Command
{
    protected $signature = 'cms-editor:prune-orphans
        {--dry-run : List what would be deleted without deleting}
        {--ttl= : Also prune un-inserted bucket uploads older than N days}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete editor media no longer referenced by any stored document.';

    public function handle(): int
    {
        $collection = config('cms-editor.collection', 'article_body');
        $referenced = $this->referencedIds();
        $keep = $referenced === [] ? [0] : $referenced;

        // Host-owned orphans: adopted, then the reference was removed.
        $orphans = Media::query()
            ->where('collection_name', $collection)
            ->whereIn('model_type', $this->hostMorphTypes())
            ->whereNotIn('id', $keep)
            ->get();

        // Bucket-owned uploads never inserted: only past the TTL grace window.
        $ttl = $this->option('ttl') ?? config('cms-editor.prune.bucket_ttl_days');

        if ($ttl !== null && ($bucketType = $this->bucketMorphType()) !== null) {
            $stale = Media::query()
                ->where('collection_name', $collection)
                ->where('model_type', $bucketType)
                ->where('created_at', '<', now()->subDays((int) $ttl))
                ->whereNotIn('id', $keep)
                ->get();

            $orphans = $orphans->merge($stale)->unique('id')->values();
        }

        if ($orphans->isEmpty()) {
            $this->components->info('No orphaned editor media found.');

            return self::SUCCESS;
        }

        $this->listOrphans($orphans);

        if ($this->option('dry-run')) {
            $this->components->info("Dry run — {$orphans->count()} media would be deleted.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$orphans->count()} orphaned media (and their files)?")) {
            return self::SUCCESS;
        }

        $orphans->each(fn (Media $media) => $media->delete());

        $this->components->info("Deleted {$orphans->count()} orphaned editor media.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>  ids referenced by any stored document
     */
    protected function referencedIds(): array
    {
        $ids = [];

        foreach ($this->sources() as [$model, $column]) {
            $model::query()
                ->select(['id', $column])
                ->cursor()
                ->each(function ($row) use (&$ids, $column): void {
                    foreach (DocumentMediaIds::extract($row->{$column} ?? []) as $id) {
                        $ids[$id] = true;
                    }
                });
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * Model + json-column pairs to scan for live references. Defaults to the
     * single configured article model + json column.
     *
     * @return array<int, array{0: class-string, 1: string}>
     */
    protected function sources(): array
    {
        $sources = config('cms-editor.prune.sources') ?: [];

        if ($sources === []) {
            $sources = [[
                config('cms-editor.article_model'),
                config('cms-editor.columns.json', 'body'),
            ]];
        }

        return $sources;
    }

    /**
     * @return array<int, string>
     */
    protected function hostMorphTypes(): array
    {
        return array_values(array_unique(array_map(
            fn (array $source) => (new $source[0])->getMorphClass(),
            $this->sources(),
        )));
    }

    protected function bucketMorphType(): ?string
    {
        $bucketModel = config('cms-editor.upload_bucket.model');

        return $bucketModel ? (new $bucketModel)->getMorphClass() : null;
    }

    /**
     * @param  EloquentCollection<int, Media>  $orphans
     */
    protected function listOrphans(EloquentCollection $orphans): void
    {
        foreach ($orphans as $media) {
            $this->line("  #{$media->id}  {$media->name}  ({$media->model_type})");
        }
    }
}
