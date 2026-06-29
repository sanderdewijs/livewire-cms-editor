<?php

use Degrinthorst\CmsEditor\Models\EditorUpload;

it('returns the same bucket row for the same scope', function () {
    config()->set('cms-editor.upload_bucket.scope', 'singleton');

    $a = EditorUpload::current();
    $b = EditorUpload::current();

    expect($a->id)->toBe($b->id);
    expect(EditorUpload::query()->count())->toBe(1);
});

it('isolates buckets per user scope', function () {
    config()->set('cms-editor.upload_bucket.scope', function () {
        return 'user-7';
    });
    $first = EditorUpload::current();

    config()->set('cms-editor.upload_bucket.scope', function () {
        return 'user-9';
    });
    $second = EditorUpload::current();

    expect($first->id)->not->toBe($second->id);
    expect(EditorUpload::query()->count())->toBe(2);
});

it('does not create a bucket when only reading the current key', function () {
    config()->set('cms-editor.upload_bucket.scope', 'singleton');

    expect(EditorUpload::currentKey())->toBeNull();
    expect(EditorUpload::query()->count())->toBe(0);
});
