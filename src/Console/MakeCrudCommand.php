<?php

namespace Qnox\Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
        {name : Studly model name e.g. Post}
        {--fields= : Comma fields e.g. title:string,slug:string:unique,body:text}
        {--schema= : Path to JSON schema file}
        {--tenant : Add tenant_id to migration & model}
        {--api : Use routes/api.php}
        {--views : Generate Blade views}
        {--policy : Generate policy}
        {--softdeletes : Add soft deletes}';

    protected $description = 'Generate CRUD files quickly (Model, Migration, Controller, FormRequest, Policy, Resource, Factory, Seeder, Views, Tests)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $snake = Str::snake($name);
        $table = Str::plural($snake);

        $schema = $this->option('schema') ? json_decode(file_get_contents($this->option('schema')), true) : null;
        $fields = $schema['fields'] ?? $this->parseFields($this->option('fields'));

        // Generate using stubs
        $this->generateMigration($table, $fields);
        $this->generateModel($name, $table);
        $this->generateController($name);
        $this->generateRequest($name, $fields);
        $this->generateResource($name);
        $this->generateFactory($name);
        $this->generateSeeder($name, $table);
        if ($this->option('policy')) $this->generatePolicy($name);
        if ($this->option('views')) $this->generateViews($name, $table);

        $this->appendRoutes($name, $table);

        $this->info('CRUD generated for '.$name);
        return self::SUCCESS;
    }

    protected function stubPath(string $file): string
    {
        $custom = base_path('stubs/crud/'.$file);
        if (file_exists($custom)) return $custom;
        return __DIR__.'/../../stubs/'.$file;
    }

    protected function render(string $stub, array $vars): string
    {
        $content = file_get_contents($this->stubPath($stub));
        foreach ($vars as $k=>$v) {
            $content = str_replace('{{'.$k.'}}', $v, $content);
        }
        return $content;
    }

    protected function parseFields(?string $str): array
    {
        $out = [];
        if (!$str) return $out;
        foreach (explode(',', $str) as $def) {
            $parts = explode(':', trim($def));
            $name = $parts[0] ?? null; $type = $parts[1] ?? 'string'; $extra = $parts[2] ?? '';
            $out[] = ['name'=>$name, 'type'=>$type, 'extra'=>$extra];
        }
        return $out;
    }

    protected function migrationColumns(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['name']; $type = $f['type']; $extra = $f['extra'] ?? '';
            $col = "\$table->{$type}('{$name}')";
            if (str_contains($extra, 'null')) $col .= "->nullable()";
            if (str_contains($extra, 'unique')) $col .= "->unique()";
            $lines[] = $col.';';
        }
        if ($this->option('softdeletes')) $lines[] = "\$table->softDeletes();";
        return implode("\n            ", $lines);
    }

    protected function validationRules(array $fields): string
    {
        $rules = [];
        foreach ($fields as $f) {
            $name = $f['name']; $type = $f['type'];
            $base = match($type) {
                'integer','bigInteger' => 'integer',
                'boolean' => 'boolean',
                'date','datetime' => 'date',
                default => 'string',
            };
            $rules[] = "'{$name}' => '{$base}'";
        }
        return implode(",\n            ", $rules);
    }

    protected function generateMigration(string $table, array $fields): void
    {
        $ts = date('Y_m_d_His');
        $path = base_path("database/migrations/{$ts}_create_{$table}_table.php");
        $content = $this->render('migration.stub', [
            'table' => $table,
            'columns' => $this->migrationColumns($fields),
        ]);
        file_put_contents($path, $content);
        $this->line("Created migration: database/migrations/{$ts}_create_{$table}_table.php");
    }

    protected function generateModel(string $name, string $table): void
    {
        $traits = [];
        if ($this->option('tenant')) $traits[] = "use \\Qnox\\Crud\\Traits\\HasTenantScope; protected string \$tenant_column = 'tenant_id';";
        if ($this->option('softdeletes')) $traits.append("use \\Illuminate\\Database\\Eloquent\\SoftDeletes;");
        $content = $this->render('model.stub', [
            'name' => $name,
            'table' => $table,
            'traits' => implode("\n    ", $traits),
        ]);
        file_put_contents(app_path("Models/{$name}.php"), $content);
        $this->line("Created model: app/Models/{$name}.php");
    }

    protected function generateController(string $name): void
    {
        $content = $this->render('controller.stub', [
            'name' => $name
        ]);
        file_put_contents(app_path("Http/Controllers/{$name}Controller.php"), $content);
        $this->line("Created controller: app/Http/Controllers/{$name}Controller.php");
    }

    protected function generateRequest(string $name, array $fields): void
    {
        $rules = $this->validationRules($fields);
        $content = $this->render('request.stub', [
            'name' => $name,
            'rules' => $rules,
        ]);
        @mkdir(app_path('Http/Requests'), 0777, true);
        file_put_contents(app_path("Http/Requests/{$name}Request.php"), $content);
        $this->line("Created request: app/Http/Requests/{$name}Request.php");
    }

    protected function generatePolicy(string $name): void
    {
        $content = $this->render('policy.stub', ['name' => $name]);
        @mkdir(app_path('Policies'), 0777, true);
        file_put_contents(app_path("Policies/{$name}Policy.php"), $content);
        $this->line("Created policy: app/Policies/{$name}Policy.php");
    }

    protected function generateResource(string $name): void
    {
        $content = $this->render('resource.stub', ['name' => $name]);
        @mkdir(app_path('Http/Resources'), 0777, true);
        file_put_contents(app_path("Http/Resources/{$name}Resource.php"), $content);
        $this->line("Created resource: app/Http/Resources/{$name}Resource.php");
    }

    protected function generateFactory(string $name): void
    {
        $content = $this->render('factory.stub', ['name' => $name]);
        @mkdir(database_path('factories'), 0777, true);
        file_put_contents(database_path("factories/{$name}Factory.php"), $content);
        $this->line("Created factory: database/factories/{$name}Factory.php");
    }

    protected function generateSeeder(string $name, string $table): void
    {
        $content = $this->render('seeder.stub', ['name'=> $name, 'table'=> $table]);
        @mkdir(database_path('seeders'), 0777, true);
        file_put_contents(database_path("seeders/{$name}Seeder.php"), $content);
        $this->line("Created seeder: database/seeders/{$name}Seeder.php");
    }

    protected function generateViews(string $name, string $table): void
    {
        $base = resource_path('views/'+Str::kebab(Str::pluralStudly($name)));
        @mkdir($base, 0777, true);
        foreach (['index','create','edit','show'] as $v) {
            $content = $this->render("views/{$v}.blade.php.stub", ['name'=> $name, 'table'=> $table]);
            file_put_contents($base.'/'.$v+'.blade.php', $content);
        }
        $this->line("Created views: resources/views/".Str::kebab(Str::pluralStudly($name)));
    }

    protected function appendRoutes(string $name, string $table): void
    {
        $file = base_path($this->option('api') ? 'routes/api.php' : 'routes/web.php');
        $route = "Route::crud('".Str::kebab(Str::pluralStudly($name))."', App\\Http\\Controllers\\{$name}Controller::class);";
        file_put_contents($file, PHP_EOL.$route.PHP_EOL, FILE_APPEND);
        $this->line("Appended Route::crud to ".($this->option('api') ? 'routes/api.php' : 'routes/web.php'));
    }
}
