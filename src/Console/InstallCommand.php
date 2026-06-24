<?php

namespace Degrinthorst\CmsEditor\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'cms-editor:install';

    protected $description = 'Install the CMS editor: publish config/assets and scaffold the body migration.';

    public function handle(Filesystem $files): int
    {
        $this->info('Installing degrinthorst/livewire-cms-editor…');

        // 1. Config.
        $this->call('vendor:publish', ['--tag' => 'cms-editor-config']);

        // 2. Pre-built assets (optional).
        if ($this->confirm('Publish the pre-built JS/CSS assets to public/vendor/cms-editor?', true)) {
            $this->call('vendor:publish', ['--tag' => 'cms-editor-assets']);
        }

        // 3. Which model holds the content. Stored without a leading slash so it
        // matches MediaLibrary's morph `model_type` (ADR-005).
        $model = ltrim($this->ask('Which Eloquent model holds the editable content?', 'App\\Models\\Article'), '\\');

        // 4. Columns.
        $jsonColumn = $this->ask('Column for the ProseMirror JSON document?', 'body');

        $htmlColumn = null;
        if ($this->confirm('Also store a cached rendered-HTML column? (faster front-end reads)', false)) {
            $htmlColumn = $this->ask('Column for the cached HTML?', 'body_html');
        }

        // 5. Table to add the columns to.
        $table = $this->ask('Which database table gets these columns?', $this->guessTable($model));

        // 6. Persist choices to .env (config reads these).
        $this->writeEnv('CMS_EDITOR_ARTICLE_MODEL', $model);
        $this->writeEnv('CMS_EDITOR_JSON_COLUMN', $jsonColumn);
        if ($htmlColumn) {
            $this->writeEnv('CMS_EDITOR_HTML_COLUMN', $htmlColumn);
        }

        // 7. Scaffold the migration.
        $path = $this->createMigration($files, $table, $jsonColumn, $htmlColumn);
        $this->components->info("Created migration: {$this->relative($path)}");

        // 8. Next steps (we never patch the model automatically).
        $this->printNextSteps($model, $jsonColumn, $htmlColumn);

        return self::SUCCESS;
    }

    protected function guessTable(string $model): string
    {
        if (class_exists($model)) {
            try {
                return (new $model)->getTable();
            } catch (\Throwable) {
                // fall through to the heuristic
            }
        }

        return Str::snake(Str::pluralStudly(class_basename($model)));
    }

    protected function createMigration(Filesystem $files, string $table, string $jsonColumn, ?string $htmlColumn): string
    {
        $up = ["            if (! Schema::hasColumn('{$table}', '{$jsonColumn}')) {",
               "                \$table->json('{$jsonColumn}')->nullable();",
               '            }'];

        $drop = [$jsonColumn];

        if ($htmlColumn) {
            $up[] = "            if (! Schema::hasColumn('{$table}', '{$htmlColumn}')) {";
            $up[] = "                \$table->longText('{$htmlColumn}')->nullable();";
            $up[] = '            }';
            $drop[] = $htmlColumn;
        }

        $down = "            \$table->dropColumn(['" . implode("', '", $drop) . "']);";

        $stub = str_replace(
            ['{{ table }}', '{{ up }}', '{{ down }}'],
            [$table, implode("\n", $up), $down],
            $files->get(__DIR__ . '/../../resources/stubs/migration.stub'),
        );

        $name = date('Y_m_d_His') . "_add_cms_editor_columns_to_{$table}_table.php";
        $path = database_path("migrations/{$name}");

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $stub);

        return $path;
    }

    protected function writeEnv(string $key, string $value): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            $this->components->warn(".env not found — set {$key}={$value} manually.");

            return;
        }

        $contents = file_get_contents($path);
        $line = "{$key}={$value}"; // unquoted: FQCN backslashes must stay literal

        $contents = preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $contents)
            ? preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $contents)
            : rtrim($contents, "\n") . "\n" . $line . "\n";

        file_put_contents($path, $contents);
        $this->components->info("Set {$key} in .env");
    }

    protected function printNextSteps(string $model, string $jsonColumn, ?string $htmlColumn): void
    {
        $short = class_basename($model);

        $traits = ['use Spatie\\MediaLibrary\\InteractsWithMedia;',
                   'use Degrinthorst\\CmsEditor\\Concerns\\InteractsWithEditorMedia;'];
        $uses = ['    use InteractsWithMedia;', '    use InteractsWithEditorMedia;'];

        if ($htmlColumn) {
            $traits[] = 'use Degrinthorst\\CmsEditor\\Concerns\\SyncsEditorHtml;';
            $uses[] = '    use SyncsEditorHtml;';
        }

        $casts = "        '{$jsonColumn}' => 'array',";

        $this->newLine();
        $this->components->info('Almost done — wire up your model manually:');
        $this->line('');
        $this->line("  // {$model}");
        foreach ($traits as $t) {
            $this->line("  {$t}");
        }
        $this->line('  use Degrinthorst\\CmsEditor\\Contracts\\HasEditorMedia;');
        $this->line('');
        $this->line("  class {$short} extends Model implements HasEditorMedia");
        $this->line('  {');
        foreach ($uses as $u) {
            $this->line("  {$u}");
        }
        $this->line('');
        $this->line('      protected $casts = [');
        $this->line("  {$casts}");
        $this->line('      ];');
        $this->line('');
        $this->line('      public function registerMediaCollections(): void');
        $this->line('      {');
        $this->line('          $this->registerEditorMediaCollection();');
        $this->line('      }');
        $this->line('  }');
        $this->line('');
        $this->components->bulletList(array_filter([
            'Run: php artisan migrate',
            'Use it in a Livewire form: <x-cms-editor wire:model="' . $jsonColumn . '" :model="$' . Str::camel($short) . '" />',
        ]));
    }

    protected function relative(string $path): string
    {
        return Str::after($path, base_path() . DIRECTORY_SEPARATOR);
    }
}
