<?php

namespace Degrinthorst\CmsEditor;

use Degrinthorst\CmsEditor\Console\InstallCommand;
use Degrinthorst\CmsEditor\Console\PruneOrphansCommand;
use Degrinthorst\CmsEditor\Livewire\Editor;
use Degrinthorst\CmsEditor\Livewire\MediaPicker;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CmsEditorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cms-editor.php', 'cms-editor');
    }

    public function boot(): void
    {
        // Views: <x-cms-editor::...> and livewire views.
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cms-editor');

        // Anonymous Blade component namespace: <x-cms-editor::editor />
        // (the convenience <x-cms-editor /> alias is registered below)
        $this->registerBladeComponents();

        // Livewire components.
        Livewire::component('cms-editor.editor', Editor::class);
        Livewire::component('cms-editor.media-picker', MediaPicker::class);

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();

            $this->commands([
                InstallCommand::class,
                PruneOrphansCommand::class,
            ]);
        }
    }

    protected function registerBladeComponents(): void
    {
        $this->loadViewComponentsAs('cms-editor', []);

        // Component alias so hosts can write <x-cms-editor :model="$article" />
        \Illuminate\Support\Facades\Blade::component(
            'cms-editor::components.editor',
            'cms-editor'
        );
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/cms-editor.php' => config_path('cms-editor.php'),
        ], 'cms-editor-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/cms-editor'),
        ], 'cms-editor-views');

        // Pre-built JS bundle (ADR-007) + base stylesheet. The JS is an ES
        // module — load it with <script type="module">.
        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/cms-editor'),
            __DIR__ . '/../resources/css/cms-editor.css' => public_path('vendor/cms-editor/cms-editor.css'),
        ], 'cms-editor-assets');

        // Upload-bucket table (ADR-009).
        $this->publishes([
            __DIR__ . '/../database/migrations/create_cms_editor_uploads_table.php.stub'
                => database_path('migrations/' . date('Y_m_d_His') . '_create_cms_editor_uploads_table.php'),
        ], 'cms-editor-migrations');
    }
}
