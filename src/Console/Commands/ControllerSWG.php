<?php

namespace Mk990\MkApi\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Util\Blacklist;

class ControllerSWG extends BaseCommand
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

    protected $signature = 'mkapi:controllerSWG {name}
                    {--force : Overwrite any existing file}
                    {--path : Controller path}
                    {--code : Generate base code for controller}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $metadata;
    protected $codes;

    public function dataToValidator(array $input): string
    {
        $validate = '';

        // Check if the column is NOT NULL
        if (isset($input['Null']) && $input['Null'] === 'NO') {
            $validate .= 'required|';
        }

        // Add validation rules based on the data type
        if (isset($input['Type'])) {
            if (preg_match('/varchar|text|char/i', $input['Type'])) {
                // String type with length constraint
                if (preg_match('/\((\d+)\)/', $input['Type'], $matches)) {
                    $maxLength = $matches[1];
                    $validate .= "string|max:$maxLength|";
                } else {
                    $validate .= 'string|';
                }
            } elseif (preg_match('/int|bigint|tinyint|smallint/i', $input['Type'])) {
                // Integer types
                $validate .= 'numeric|';
            } elseif (preg_match('/decimal|float|double/i', $input['Type'])) {
                // Floating-point types
                $validate .= 'numeric|';
            } elseif (preg_match('/date|timestamp/i', $input['Type'])) {
                // Date and timestamp types
                $validate .= 'date|';
            }
        }

        // Add validation rule for specific column names
        if (isset($input['Field'])) {
            if ($input['Field'] === 'email') {
                $validate .= 'email|unique:' . $input['table'] . '|';
            }
        }

        return rtrim($validate, '|');
    }

    /**
     * Generate Swagger annotations for a given table and its columns.
     *
     * @param string $tableName
     * @param array $columns
     * @return string
     */
    public function generateValidatorRules($tableName)
    {
        $validators = [];

        if (!isset($this->metadata[$tableName])) {
            throw new \Exception("Table '$tableName' does not exist in the metadata.");
        }

        $columns = $this->metadata[$tableName];

        foreach ($columns as $column) {
            $blackList = ['id', 'deleted_at', 'created_at', 'updated_at'];
            if (in_array($column['Field'], $blackList)) {
                continue;
            }
            // Add the table name to the column metadata
            $column['table'] = $tableName;

            // Generate validation rules for this column
            $validators[$column['Field']] = $this->dataToValidator($column);
        }
        // convert array to string array like [ "key" => "value" ] in php format for eval
        $validators = var_export($validators, true);
        // convert array to [] format
        $validators = str_replace('array (', '[', $validators);
        $validators = str_replace('  ', '            ', $validators);
        $validators = str_replace(')', '        ]', $validators);

        return $validators;
    }

    private function generateControllerCode(string $tableName)
    {
        $className = ucfirst(Str::camel($tableName));
        $modelName = Str::singular($className);
        $validator = $this->generateValidatorRules($tableName);
        $index = <<<EOT
public function index(): JsonResponse
    {
        return \$this->success((new {$modelName}())->latest()->paginate(20));
    }
EOT;
        $store = <<<EOT
public function store(Request \$request): JsonResponse
    {
        \$request->validate($validator);
        try {
            return \$this->success((new {$modelName}())->create(\$request->all()));
        } catch (Exception \$e) {
            //return error message
            Log::error(\$e->getMessage());
            return \$this->error('create error');
        }
    }
EOT;
        $show = <<<EOT
public function show(int \$id): JsonResponse
    {
         try {
            \$result = (new {$modelName}())->findOrFail(\$id);
            return \$this->success(\$result);
        } catch (Exception \$e) {
            Log::error(\$e->getMessage());
            return \$this->error('nothing to show');
        }
    }
EOT;
        $update = <<<EOT
public function update(Request \$request, int \$id): JsonResponse
    {
        \$request->validate($validator);
            try {
            \$result = (new {$modelName}())->findOrFail(\$id);
            \$result->update(\$request->all());
            return \$this->success(\$result);
        } catch (Exception \$e) {
            Log::error(\$e->getMessage());
            return \$this->error('update error');
        }
    }
EOT;
        $delete = <<<EOT
public function destroy(int \$id): JsonResponse
    {
        try {
            \$result = (new {$modelName}())->findOrFail(\$id);
            \$result->delete();
            \$id = \$result->id;
            return \$this->success("post \$id deleted");
        } catch (Exception \$e) {
            Log::error(\$e->getMessage());
            return \$this->error('delete error');
        }
    }
EOT;
        return [$index, $store, $show, $update, $delete];
    }

    private function generateSwaggerController(string $tableName, array $columns): array
    {
        $className = ucfirst(Str::camel($tableName));
        $modelName = Str::singular($className);
        $routeName = Str::camel($modelName);
        $properties = [];

        foreach ($columns as $columnName => $columnType) {
            $blackList = ['id', 'created_at', 'updated_at'];
            if (in_array($columnName, $blackList)) {
                continue;
            }
            // Determine Swagger type
            $type = $this->getSwaggerType($columnType);

            // Generate an example value
            $example = $this->getExampleValue($columnName);

            $properties[] = "
     *            @OA\Property(
     *              property=\"$columnName\",
     *              description=\"$columnName\",
     *              type=\"$type[0]\",
     *              format=\"$type[1]\",
     *              example=\"$example\",
     *            ),";
        }

        // Join all the property annotations
        $propertiesStr = implode('', $properties);

        $index = <<<EOT
    /**
     * @OA\Get(
     *   path="/$routeName",
     *   tags={"$modelName"},
     *   summary="get all $className",
     *   description="list of all $className",
     *   operationId="getAll$className",
     *   deprecated=false,
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     example=1,
     *     @OA\Schema(
     *     type="string"
     *      )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *     @OA\JsonContent(ref="#/components/schemas/{$modelName}Model"),
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="an unexpected error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *   ),security={{"api_key": {}}}
     * )
     *
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Your code here
    }
EOT;
        $store = <<<EOT
    /**
     * @OA\Post(
     *   path="/$routeName",
     *   tags={"$modelName"},
     *   summary="create $modelName",
     *   description="create $modelName",
     *   operationId="Create$modelName",
     *   deprecated=false,
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(ref="#/components/schemas/{$modelName}Model"),
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="an unexpected error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *   ),
     *   @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent($propertiesStr
     *      )
     *   ),security={{"api_key": {}}}
     * )
     *
     * Store a newly created resource in storage.
     *
     * @param Request \$request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request \$request): JsonResponse
    {
        // Your code here
    }
EOT;
        $show = <<<EOT
    /**
     * @OA\Get(
     *   path="/$routeName/{id}",
     *   tags={"$modelName"},
     *   summary="get one $modelName",
     *   description="one $modelName",
     *   operationId="getOne$modelName",
     *   deprecated=false,
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *     type="string"
     *      )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="success",
     *     @OA\JsonContent(ref="#/components/schemas/{$modelName}Model"),
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="an unexpected error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *   ),security={{"api_key": {}}}
     * )
     *
     * Display the specified resource.
     *
     * @param int \$id
     * @return JsonResponse
     */
    public function show(int \$id): JsonResponse
    {
        // Your code here
    }
EOT;
        $update = <<<EOT
    /**
     * @OA\Put(
     *   path="/$routeName/{id}",
     *   tags={"$modelName"},
     *   summary="update $modelName",
     *   description="update $modelName",
     *   operationId="Update$modelName",
     *   deprecated=false,
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(ref="#/components/schemas/{$modelName}Model"),
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="an unexpected error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *   ),
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *     type="string"
     *      )
     *   ),
     *   @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent($propertiesStr
     *      )
     *   ),security={{"api_key": {}}}
     * )
     *
     * Update the specified resource in storage.
     *
     * @param Request \$request
     * @param int     \$id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update(Request \$request, int \$id): JsonResponse
    {
        // Your code here
    }
EOT;
        $delete = <<<EOT
    /**
     * @OA\Delete(
     *   path="/$routeName/{id}",
     *   tags={"$modelName"},
     *   summary="delete $modelName",
     *   description="delete $modelName",
     *   operationId="delete$modelName",
     *   deprecated=false,
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *     type="string"
     *      )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success Message",
     *     @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="an unexpected error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *   ),security={{"api_key": {}}}
     * )
     *
     * Remove the specified resource from storage.
     *
     * @param int \$id
     * @return JsonResponse
     */
    public function destroy(int \$id): JsonResponse
    {
        // Your code here
    }
EOT;

        return [$index, $store, $show, $update, $delete];
    }

    private function addSwaggerAnnotationsToController(string $modelPath, $swaggers)
    {
        $index = $swaggers[0];
        $store = $swaggers[1];
        $show = $swaggers[2];
        $update = $swaggers[3];
        $delete = $swaggers[4];

        // Check if the model file exists
        if (file_exists($modelPath)) {
            $fileContent = File::get($modelPath);
            $fileContent = preg_replace('/\s{4}\/\*\*.{8}Display a.*?index\(\).*?\{\n\s*?\/\/\n\s*?\}/s', $index, $fileContent);
            File::put($modelPath, $fileContent);
            $fileContent = preg_replace('/\s{4}\/\*\*.{8}Store a.*?store\(.*?\).*?\{\n\s*?\/\/\n\s*?\}/s', $store, $fileContent);
            File::put($modelPath, $fileContent);
            $fileContent = preg_replace('/\s{4}\/\*\*.{8}Display the.*?show\(.*?\).*?\{\n\s*?\/\/\n\s*?\}/s', $show, $fileContent);
            File::put($modelPath, $fileContent);
            $fileContent = preg_replace('/\s{4}\/\*\*.{8}Update the.*?update\(.*?\).*?\{\n\s*?\/\/\n\s*?\}/s', $update, $fileContent);
            File::put($modelPath, $fileContent);
            $fileContent = preg_replace('/\s{4}\/\*\*.{8}Remove the.*?destroy\(.*?\).*?\{\n\s*?\/\/\n\s*?\}/s', $delete, $fileContent);
            File::put($modelPath, $fileContent);
            $this->info("Swagger annotations added/updated for $modelPath");
            if ($this->option('force')) {
                $fileContent = File::get($modelPath);
                $fileContent = preg_replace('/\/\*\*.*?@OA\\\.*?\*\//s', '', $fileContent);
                File::put($modelPath, $fileContent);

                $fileContent = preg_replace('/public function index/s', trim(preg_replace("/index\(.*?\)\: JsonResponse.*/s", '', $index)) . ' index', $fileContent);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function store/s', trim(preg_replace("/store\(.*?\)\: JsonResponse.*/s", '', $store)) . ' store', $fileContent);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function show/s', trim(preg_replace("/show\(.*?\)\: JsonResponse.*/s", '', $show)) . ' show', $fileContent);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function update/s', trim(preg_replace("/update\(.*?\)\: JsonResponse.*/s", '', $update)) . ' update', $fileContent);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function destroy/s', trim(preg_replace("/destroy\(.*?\)\: JsonResponse.*/s", '', $delete)) . ' destroy', $fileContent);
                File::put($modelPath, $fileContent);

                $fileContent = preg_replace('/\s{4}\n/s', '', $fileContent);
                File::put($modelPath, $fileContent);
            }
            if ($this->option('code')) {
                $index = $this->codes[0];
                $store = $this->codes[1];
                $show = $this->codes[2];
                $update = $this->codes[3];
                $delete = $this->codes[4];

                $this->haveMiddeleware($modelPath);

                $fileContent = File::get($modelPath);
                $fileContent = preg_replace('/public function index\(.*?\).{29}\/\/ Your code here.{5}\}/s', $index, $fileContent, 1);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function store\(.*?\).{29}\/\/ Your code here.{5}\}/s', $store, $fileContent, 1);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function show\(.*?\).{29}\/\/ Your code here.{5}\}/s', $show, $fileContent, 1);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function update\(.*?\).{29}\/\/ Your code here.{5}\}/s', $update, $fileContent, 1);
                File::put($modelPath, $fileContent);
                $fileContent = preg_replace('/public function destroy\(.*?\).{29}\/\/ Your code here.{5}\}/s', $delete, $fileContent, 1);
                File::put($modelPath, $fileContent);
            }
        } else {
            $this->warn("Model file for `$modelPath` not found.");
        }
    }

    public function haveMiddeleware($path)
    {
        $fileContent = File::get($path);
        if (!preg_match('/HasMiddleware/s', $fileContent)) {
            $fileContent = preg_replace("/extends Controller.*?\{/s", "extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth')
        ];
    }
", $fileContent, 1);
            $fileContent = preg_replace("/use App\\\Models/s", "use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use App\Models", $fileContent, 1);
            File::put($path, $fileContent);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->metadata = $this->getSqlData();
            $allTables = $this->parseCreateTableStatements($this->metadata['pretend_sql']);

            foreach ($allTables as $tableName => $columns) {
                $this->codes = $this->generateControllerCode($tableName);
                $swaggers = $this->generateSwaggerController($tableName, $columns);

                // Look for the corresponding model file
                $modelName = ucfirst(Str::singular(Str::camel($tableName)));
                $modelPath = app_path('Http/Controllers/' . $modelName . 'Controller.php');

                if (file_exists($modelPath)) {
                    // Add or update Swagger annotations in the model
                    $this->addSwaggerAnnotationsToController($modelPath, $swaggers);
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
