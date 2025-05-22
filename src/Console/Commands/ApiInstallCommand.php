<?php

namespace Mk990\MkApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;
use function PHPUnit\Framework\directoryExists;

#[AsCommand(name: 'install:mkApi')]
class ApiInstallCommand extends Command
{
    use InteractsWithComposerPackages;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:mkApi
                    {--composer=global : Absolute path to the Composer binary which should be used to install packages}
                    {--force : Overwrite any existing file}
                    {--package : The name of the package to install}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an API routes file and install Laravel Sanctum or Laravel Passport';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->initializeMiddlewares();
        $this->changeAuthConfigFile();
        $this->handleDotEnvFile();

        $this->installPrompt();
        $this->installSwagger();
        $this->installJWT();
        $this->installLaraStan();

        if (file_exists($swaggerBlade = $this->laravel->basePath('resources/views/vendor/l5-swagger/index.blade.php'))) {
            $this->components->info('putted swagger blade file');
            File::put($swaggerBlade, File::get(__DIR__ . '/stubs/swagger-blade.stub'));
        } else {
            $this->components->error('swagger blade file not found');
        }

        if (file_exists($apiRoutesPath = $this->laravel->basePath('routes/api.php')) &&
            !$this->option('force')) {
            $this->components->error('API routes file already exists.');
        } else {
            $this->components->info('Published API routes file.');

            copy(__DIR__ . '/stubs/api-routes.stub', $apiRoutesPath);
            $this->uncommentApiRoutesFile();
        }

        if (!file_exists($authControllerPath = $this->laravel->basePath('app/Http/Controllers/AuthController.php'))) {
            $this->components->info('putted AuthController file');
            File::put($authControllerPath, File::get(__DIR__ . '/stubs/auth-controller.stub'));
        }

        if (!file_exists($testControllerPath = $this->laravel->basePath('app/Http/Controllers/ExampleController.php'))) {
            $this->components->info('putted ExampleController file');
            File::put($testControllerPath, File::get(__DIR__ . '/stubs/example-controller.stub'));
        }

        if (file_exists($UserModel = $this->laravel->basePath('app/Models/User.php'))) {
            copy(__DIR__ . '/stubs/user-model.stub', $UserModel);
            $this->components->info('Updated User Model file');
        } else {
            $this->components->error('User Model file not found');
        }

        if (!file_exists($fixerPath = $this->laravel->basePath('.php-cs-fixer.php'))) {
            copy(__DIR__ . '/stubs/phpfixer.stub', $fixerPath);
        }

        if ($this->option('package')) {
            $choises = multiselect(
                label: 'Which package do you want to install?',
                options: [
                    'pulse',
                    'turnstile',
                    'backup',
                    'iran'
                ],
                required: true,
            );

            foreach ($choises as $choise) {
                match ($choise) {
                    'pulse'     => $this->installPulse(),
                    'turnstile' => $this->installTurnstile(),
                    'backup'    => $this->installBackup(),
                    'iran'      => $this->installPersianPackages(),
                };

                if ($choise == 'backup') {
                    if (file_exists($apiRoutesPath = $this->laravel->basePath('config/backup.php')) &&
                        !$this->option('force')) {
                        $this->components->error('backup file already exists.');
                    } else {
                        $this->components->info('Published backup config file.');
                        copy(__DIR__ . '/stubs/backup.stub', $apiRoutesPath);
                    }

                    if (file_exists($consolePath = $this->laravel->basePath('routes/console.php'))) {
                        $content = file_get_contents($consolePath);
                        if (!str_contains($content, 'backup:run')) {
                            copy(__DIR__ . '/stubs/console.stub', $consolePath);
                        }
                    }
                }

                if ($choise == 'iran') {
                    if (!file_exists($vertaTrait = $this->laravel->basePath('app/Models/Traits/VertaTrait.php'))) {
                        $this->components->info('putted VertaTrait file');
                        File::put($vertaTrait, File::get(__DIR__ . '/stubs/verta-trait.stub'));
                    }
                }
            }
        }
    }

    protected function validationComposer($package, $dev = false)
    {
        // want check composer.json file if package exist return error
        if (file_exists($this->laravel->basePath('composer.json'))) {
            $composer = json_decode(file_get_contents($this->laravel->basePath('composer.json')), true);
            if (isset($composer['require'][$package])) {
                $this->components->error($package . ' already installed');
            } else {
                $this->requireComposerPackages($this->option('composer'), [
                    $package,
                ], $dev);
            }
        }
    }

    protected function installPrompt()
    {
        $this->validationComposer('laravel/prompts');
    }

    protected function initializeMiddlewares()
    {
        if (!is_dir(app_path('Http/Middleware'))) {
            mkdir(app_path('Http/Middleware'), 0755, true);
        }

        if (!file_exists($pulseMiddleware = $this->laravel->basePath('app/Http/Middleware/PulseJwtAuth.php'))) {
            $this->components->info('putted PulseJwtAuth file');
            File::put($pulseMiddleware, File::get(__DIR__ . '/stubs/pulse-middleware.stub'));
        }

        if (!file_exists($SecretHeadersMiddleware = $this->laravel->basePath('app/Http/Middleware/SecureHeadersMiddleware.php'))) {
            $this->components->info('putted SecureHeadersMiddleware file');
            File::put($SecretHeadersMiddleware, File::get(__DIR__ . '/stubs/secret-headers-middleware.stub'));
        }

        if (!file_exists($userIdMiddleware = $this->laravel->basePath('app/Http/Middleware/UserIdMiddleware.php'))) {
            $this->components->info('putted UserIdMiddleware file');
            File::put($userIdMiddleware, File::get(__DIR__ . '/stubs/user-id-middleware.stub'));
        }

        $appBootstrapPath = $this->laravel->bootstrapPath('app.php');
        $content = file_get_contents($appBootstrapPath);

        $middlewareToAdd = [
            '\App\Http\Middleware\PulseJwtAuth::class',
            '\App\Http\Middleware\SecureHeadersMiddleware::class',
            '\App\Http\Middleware\UserIdMiddleware::class',
        ];

        foreach ($middlewareToAdd as $middleware) {
            if (!str_contains($content, $middleware)) {
                $this->components->info("Appending {$middleware} to middleware stack.");

                $pattern = '/->withMiddleware\(function\s*\(Middleware\s+\$middleware\)\s*{(.*?)^\s*}\)/sm';

                $content = preg_replace_callback($pattern, function ($matches) use ($middleware) {
                    $existing = rtrim($matches[1]);
                    $injection = "\n        \$middleware->append({$middleware});";
                    return "->withMiddleware(function (Middleware \$middleware) {{$existing}{$injection}\n    })";
                }, $content, 1);
            } else {
                $this->components->info("{$middleware} is already registered.");
            }
        }

        file_put_contents($appBootstrapPath, $content);
    }

    /**
     * Uncomment the API routes file in the application bootstrap file.
     *
     * @return void
     */
    protected function uncommentApiRoutesFile()
    {
        $appBootstrapPath = $this->laravel->bootstrapPath('app.php');

        $content = file_get_contents($appBootstrapPath);

        if (str_contains($content, '// api: ')) {
            (new Filesystem())->replaceInFile(
                '// api: ',
                'api: ',
                $appBootstrapPath,
            );
        } elseif (str_contains($content, 'web: __DIR__.\'/../routes/web.php\',')) {
            (new Filesystem())->replaceInFile(
                'web: __DIR__.\'/../routes/web.php\',',
                'web: __DIR__.\'/../routes/web.php\',' . PHP_EOL . '        api: __DIR__.\'/../routes/api.php\',',
                $appBootstrapPath,
            );
        } else {
            return 'ok';
        }
    }

    protected function changeAuthConfigFile()
    {
        $authConfigPath = $this->laravel->basePath('config/auth.php');

        $content = file_get_contents($authConfigPath);

        if (str_contains($content, "'AUTH_GUARD', 'web'")) {
            (new Filesystem())->replaceInFile(
                "'AUTH_GUARD', 'web'",
                "'AUTH_GUARD', 'api'",
                $authConfigPath,
            );
        } else {
            return 'ok';
        }

        if (str_contains(
            $content,
            "'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],"
        )) {
            (new Filesystem())->replaceInFile(
                "'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],",
                "'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],",
                $authConfigPath,
            );
            $this->components->info('updated auth config file');
        } else {
            return 'ok';
        }
    }

    protected function installPulse()
    {
        $this->validationComposer('laravel/pulse');

        if (file_exists($this->laravel->basePath('config/pulse.php')) &&
        !$this->option('force')) {
            $this->components->error('pulse file already exists.');
        } else {
            $this->call('vendor:publish', ['--provider' => 'Laravel\Pulse\PulseServiceProvider']);
        }

        $this->call('migrate');
    }

    protected function installTurnstile()
    {
        $this->validationComposer('ryangjchandler/laravel-cloudflare-turnstile');

        $servicesFilePath = config_path('services.php');

        if (file_exists($servicesFilePath)) {
            $content = file_get_contents($servicesFilePath);

            if (!Str::contains($content, "'turnstile' =>")) {
                $turnstileConfig = <<<CONFIG

    'turnstile' => [
        'key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],
CONFIG;

                $content = preg_replace(
                    '/(\s*\]\s*;)\s*$/',
                    "\n" . $turnstileConfig . "\n$1",
                    $content
                );

                File::put($servicesFilePath, $content);
            }
        }
    }

    protected function installSwagger()
    {
        $this->validationComposer('darkaonline/l5-swagger');

        $this->call('vendor:publish', ['--provider' =>  "L5Swagger\L5SwaggerServiceProvider"]);
    }

    protected function installLaraStan()
    {
        // $this->requireComposerPackages($this->option('composer'), [
        //     'larastan/larastan:^3.0',
        // ], true);
        $this->validationComposer('larastan/larastan:^3.0', true);
    }

    protected function installJWT()
    {
        $this->requireComposerPackages($this->option('composer'), [
            'php-open-source-saver/jwt-auth:^2.7',
        ]);
        $this->validationComposer('php-open-source-saver/jwt-auth:^2.7');
    }

    protected function installBackup()
    {
        $this->requireComposerPackages($this->option('composer'), [
            'ext-zip:*',
            'spatie/laravel-backup:^9.1',
        ]);
    }

    protected function handleDotEnvFile()
    {
        $envExample = $this->laravel->basePath('.env.example');
        $env = $this->laravel->basePath('.env');
        $jwt_secret = bin2hex(random_bytes(32));
        $dataEnv = [
            'L5_SWAGGER_CONST_HOST=${APP_URL}/api',
            'L5_SWAGGER_GENERATE_ALWAYS=true',
            '# L5_SWAGGER_USE_ABSOLUTE_PATH=false',
            'JWT_TTL=10080',
            'TURNSTILE_SITE_KEY=0000000000000',
            'TURNSTILE_SECRET_KEY=0000000000000',
            'JWT_SECRET=' . $jwt_secret,
        ];
        $content = file_exists($env) ? file_get_contents($env) : '';
        $newContent = [];

        foreach ($dataEnv as $line) {
            $key = explode('=', $line, 2)[0];
            if (!preg_match("/^$key=/m", $content)) {
                $newContent[] = $line;
            }
        }
        if (!empty($newContent)) {
            $content .= "\n" . implode("\n", $newContent);
            file_put_contents($env, trim($content));
            // wan add data to .env.example
            $content = file_get_contents($envExample);
            $content .= "\n" . implode("\n", $newContent);
            file_put_contents($envExample, trim($content));
            $this->components->info('env file updated');
        }
    }

    protected function installPersianPackages()
    {
        $this->requireComposerPackages($this->option('composer'), [
            'sadegh19b/laravel-persian-validation',
            'hekmatinasser/verta',
        ]);
    }
}
