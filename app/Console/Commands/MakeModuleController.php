<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeModuleController extends Command
{
    protected $signature = 'make:module-controller {module} {name} {--model=}';
    protected $description = 'Create a full resource controller inside app/Modules/{Module}/Http/Controllers';

    public function handle()
    {
        $module = trim($this->argument('module'));
        $name   = trim($this->argument('name'));
        $model  = $this->option('model');

        $filesystem = new Filesystem();

        $path = app_path("Modules/{$module}/Http/Controllers/{$name}Controller.php");

        $filesystem->ensureDirectoryExists(dirname($path));

        if ($filesystem->exists($path)) {
            $this->error("❌ Controller already exists: {$path}");
            return self::FAILURE;
        }

        $namespace = "App\\Modules\\{$module}\\Http\\Controllers";

        $content = $this->buildController($namespace, $name, $module, $model);

        $filesystem->put($path, $content);

        $this->info("✅ Module controller created successfully!");
        $this->line($path);

        return self::SUCCESS;
    }

    protected function buildController(string $namespace, string $name, string $module, ?string $model): string
    {
        $useRequest = '';
        $useModel = '';
        $methods = [];

        // INDEX
        $methods[] = <<<PHP
    public function index()
    {
        return response()->json(['message' => 'index']);
    }
PHP;

        if ($model) {
            $modelClass = $model;
            $modelVar = lcfirst($modelClass);

            $useRequest = "use Illuminate\\Http\\Request;";
            $useModel = "use App\\Modules\\{$module}\\Models\\{$modelClass};";

            // STORE
            $methods[] = <<<PHP
    public function store(Request \$request)
    {
        \$data = {$modelClass}::create(\$request->all());

        return response()->json(\$data, 201);
    }
PHP;

            // SHOW
            $methods[] = <<<PHP
    public function show({$modelClass} \${$modelVar})
    {
        return response()->json(\${$modelVar});
    }
PHP;

            // UPDATE
            $methods[] = <<<PHP
    public function update(Request \$request, {$modelClass} \${$modelVar})
    {
        \${$modelVar}->update(\$request->all());

        return response()->json(\${$modelVar});
    }
PHP;

            // DESTROY
            $methods[] = <<<PHP
    public function destroy({$modelClass} \${$modelVar})
    {
        \${$modelVar}->delete();

        return response()->json(['message' => 'deleted successfully']);
    }
PHP;
        }

        $methodsBlock = implode(PHP_EOL . PHP_EOL, $methods);

        return "<?php

namespace {$namespace};

use App\\Http\\Controllers\\Controller;
{$useRequest}
{$useModel}

class {$name}Controller extends Controller
{
{$methodsBlock}
}
";
    }
}
