<?php

namespace Mk990\MkApi\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelSWG extends BaseCommand
{
    /**
     * Add or overwrite Swagger annotations in the model file.
     *
     * @param string $modelPath
     * @param string $swaggerModel
     * @return void
     */

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mkapi:modelSWG {name}
                    {--force : Overwrite any existing file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Generate Swagger annotations for a given table and its columns.
     *
     * @param string $tableName
     * @param array $columns
     * @return string
     */
    private function generateSwaggerModel(string $tableName, array $columns): string
    {
        $className = ucfirst(Str::camel($tableName));
        $modelName = Str::singular($className);
        $properties = [];

        foreach ($columns as $columnName => $columnType) {
            $blackList = ['deleted_at'];
            if (in_array($columnName, $blackList)) {
                continue;
            }
            // Determine Swagger type
            $type = $this->getSwaggerType($columnType);

            // Generate an example value
            $example = $this->getExampleValue($columnName);

            $properties[] = " *     @OA\Property(
 *       property=\"$columnName\",
 *       description=\"$columnName\",
 *       type=\"$type[0]\",
 *       format=\"$type[1]\",
 *       example=\"$example\"
 *     ),";
        }

        // Join all the property annotations
        $propertiesStr = implode("\n", $properties);

        return <<<EOT
/**
 * @OA\Schema(
 *     schema="{$modelName}Model",
 *     type="object",
 *     required={"id"},
$propertiesStr
 * )
 */
EOT;
    }

    private function addSwaggerAnnotationsToModel(string $modelPath, string $swaggerModel)
    {
        // Check if the model file exists
        if (file_exists($modelPath)) {
            if ($this->hasSwaggerAnnotations($modelPath) && !$this->option('force')) {
                return;
            }

            $fileContent = File::get($modelPath);
            // Remove any existing Swagger annotations (anything from /** to */ with @OA\Schema)
            $fileContent = preg_replace('/\/\*\*.*?@OA\\\Schema.*?\*\/\s*/s', '', $fileContent);
            // Add the new Swagger annotations above the class declaration
            $fileContent = preg_replace('/class\s+/', $swaggerModel . "\nclass ", $fileContent, 1);

            // Save the updated file content
            File::put($modelPath, $fileContent);
            $this->info("Swagger annotations added/updated for $modelPath");
        } else {
            $this->warn("Model file for `$modelPath` not found.");
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $name = strtolower($name);
        if ($name != 'all') {
            $name = Str::plural($name);
        }
        echo "Creating $name models...\n";

        try {
            $allTables = $this->parseCreateTableStatements($this->getSqlData()['pretend_sql']);
            foreach ($allTables as $tableName => $columns) {
                if ($name != 'all' && $name != $tableName) {
                    continue;
                }
                $swaggerModel = $this->generateSwaggerModel($tableName, $columns);

                // Look for the corresponding model file
                $modelName = ucfirst(Str::singular(Str::camel($tableName)));
                $modelPath = app_path('Models/' . $modelName . '.php');

                if (file_exists($modelPath)) {
                    // Add or update Swagger annotations in the model
                    $this->addSwaggerAnnotationsToModel($modelPath, $swaggerModel);
                } else {
                    $this->warn("Model file for `$tableName` not found at $modelPath");
                }
            }
        } finally {
            // Drop the temporary database to clean up
            DB::statement("DROP DATABASE {$this->tempDbName}");
        }
    }
}
