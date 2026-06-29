<?php

namespace Degrinthorst\CmsEditor\Models;

use Degrinthorst\CmsEditor\Concerns\InteractsWithEditorMedia;
use Degrinthorst\CmsEditor\Contracts\HasEditorMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Package-owned "upload bucket" (ADR-009).
 *
 * Freshly-uploaded editor images need a persisted MediaLibrary owner, but a new
 * article has no record yet. Rather than forcing the host to pre-create a draft,
 * uploads attach to this always-present bucket. Because images are resolved
 * everywhere by their global Media id (ContentRenderer + the picker), the host
 * article never needs to own the row for the editor to work.
 *
 * One row per scope (default: per authenticated user). The optional
 * AdoptsEditorMedia trait re-points bucket media onto the host article on save,
 * after which it becomes part of the shared, host-owned pool.
 */
class EditorUpload extends Model implements HasEditorMedia
{
    use InteractsWithEditorMedia;
    use InteractsWithMedia;

    protected $table = 'cms_editor_uploads';

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        $this->registerEditorMediaCollection();
    }

    /**
     * The bucket for the current scope, creating it if needed (upload path).
     */
    public static function current(): self
    {
        $scope = static::resolveScope();

        try {
            return static::firstOrCreate(['scope' => $scope]);
        } catch (UniqueConstraintViolationException) {
            // Lost a create race — the winner's row now exists.
            return static::where('scope', $scope)->firstOrFail();
        }
    }

    /**
     * The current scope's bucket id without creating one (read/query path).
     */
    public static function currentKey(): ?int
    {
        return static::query()->where('scope', static::resolveScope())->value('id');
    }

    /**
     * Resolve the bucket scope from config. 'user' (default) isolates each
     * uploader's not-yet-adopted images; 'singleton' shares one global bucket;
     * a callable lets hosts compute their own (e.g. per tenant).
     */
    public static function resolveScope(): ?string
    {
        $scope = config('cms-editor.upload_bucket.scope', 'user');

        if (is_callable($scope)) {
            return $scope();
        }

        return match ($scope) {
            'singleton' => 'singleton',
            // Per authenticated user; fall back to a per-session bucket so two
            // unauthenticated browsers never share (and leak) pending uploads.
            default => ($id = auth()->id()) !== null
                ? (string) $id
                : 'guest:' . session()->getId(),
        };
    }
}
