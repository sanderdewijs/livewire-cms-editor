<?php

namespace Degrinthorst\CmsEditor\Tests;

use Degrinthorst\CmsEditor\CmsEditorServiceProvider;
use Degrinthorst\CmsEditor\Tests\Fixtures\Article;
use Illuminate\Database\Schema\Blueprint;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            LivewireServiceProvider::class,
            CmsEditorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Point the editor at our fixture model.
        $app['config']->set('cms-editor.article_model', Article::class);

        // Use a fake, locally-writable disk for MediaLibrary uploads.
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => 'http://localhost/storage',
            'visibility' => 'public',
        ]);
        $app['config']->set('media-library.disk_name', 'public');
    }

    protected function defineDatabaseMigrations(): void
    {
        // Spatie MediaLibrary ships its migration as a .stub, which the normal
        // migrator won't pick up — run it directly.
        $media = include __DIR__ . '/../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub';
        $media->up();

        // A minimal host "articles" table for the fixture model.
        $this->app['db']->connection()->getSchemaBuilder()->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->json('body')->nullable();
            $table->timestamps();
        });
    }
}
