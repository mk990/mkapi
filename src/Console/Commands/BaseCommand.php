<?php

namespace Mk990\MkApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BaseCommand extends Command
{
    protected $tempDbName;


    protected function getSqlData(): array
    {
        $this->tempDbName = 'temp_db_' . Str::random(8);

        // Create the temporary database
        DB::statement("CREATE DATABASE {$this->tempDbName}");
        // Configure a new connection to the temporary database
        Config::set("database.connections.{$this->tempDbName}", [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => $this->tempDbName,
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_persian_ci',
            'prefix'    => '',
        ]);

        // Run migrations on the temporary database
        Artisan::call('migrate', ['--database' => $this->tempDbName]);
        $data = [];

        $tables = DB::connection($this->tempDbName)->select('SHOW TABLES');
        $key = 'Tables_in_' . $this->tempDbName; // Key for table name in the result set

        foreach ($tables as $table) {
            $tableName = $table->$key;

            // Get all columns for the table
            $columns = DB::connection($this->tempDbName)->select("SHOW FULL COLUMNS FROM `$tableName`");

            $data[$tableName] = [];
            foreach ($columns as $column) {
                $data[$tableName][] = [
                    'Field' => $column->Field,
                    'Type' => $column->Type,
                    'Null' => $column->Null,
                    'Key' => $column->Key,
                    'Default' => $column->Default,
                    'Extra' => $column->Extra,
                    'Collation' => $column->Collation ?? null,
                    'Comment' => $column->Comment ?? null,
                ];
            }
        }

        // Reset and pretend migration SQL for quick outputs
        Artisan::call('migrate:reset', ['--database' => $this->tempDbName]);
        Artisan::call('migrate', ['--database' => $this->tempDbName, '--pretend' => true]);
        $data['pretend_sql'] = Artisan::output();

        // Get all tables in the temporary database
        return $data;
    }

    protected function parseCreateTableStatements(string $output): array
    {
        $output = str_replace('  ⇂ ', "\n", $output);
        $lines = explode("\n", $output);
        $sqlArr = array_filter($lines, function ($line) {
            return stripos($line, 'create table') !== false;
        });
        $sqlOutput = implode("\n", $sqlArr);
        $tables = [];

        // Match each line that contains a "create table" statement
        preg_match_all('/create table `([^`]+)` \((.*?)\) default character set/', $sqlOutput, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $blackList = [
                'migrations', 'password_resets', 'jobs', 'failed_jobs', 'sessions',
                'job_batches', 'cache', 'cache_locks', 'password_reset_tokens',
                'personal_access_tokens', 'pulse_aggregates', 'pulse_entries', 'pulse_values'
            ];
            if (in_array($match[1], $blackList)) {
                continue;
            }
            $tableName = $match[1];
            $columnsStr = $match[2];

            // Initialize array for the current table's columns
            $columns = [];

            // Match each column definition within the parentheses
            preg_match_all('/`([^`]+)`\s+([a-zA-Z0-9\(\)]+)[^,]*,?/', $columnsStr, $columnMatches, PREG_SET_ORDER);

            foreach ($columnMatches as $columnMatch) {
                $columnName = $columnMatch[1];
                $columnType = $columnMatch[2];
                $columns[$columnName] = $columnType;
            }

            // Add to main array
            $tables[$tableName] = $columns;
        }

        return $tables;
    }

    /**
     * Get the appropriate Swagger data type for a MySQL column.
     *
     * @param string $columnType
     * @return array
     */
    protected function getSwaggerType(string $columnType): array
    {
        switch ($columnType) {
            case 'int':
            case 'tinyint':
                return ['integer', 'int32'];
            case 'bigint':
                return ['integer', 'int64'];
            case 'timestamp':
                return ['string', 'date-time'];
            case 'varchar':
            case 'text':
                return ['string', ''];
            case 'decimal':
            case 'double':
                return ['number', 'double'];
            case 'float':
                return ['number', 'float'];
            default:
                return ['string', ''];
        }
    }

    /**
     * Generate example values based on column names.
     *
     * @param string $columnName
     * @return mixed
     */
    protected function getExampleValue(string $columnName)
    {
        switch ($columnName) {
            case 'state':
            case 'id':
                return 0;
            case 'username':
                return 'user';
            case 'password':
                return '1234567';
            case 'email':
                return 'example@example.com';
            case 'ip':
                return '127.0.0.1';
            case 'updated_at':
            case 'created_at':
                return date('Y-m-d H:i:s');
            default:
                return 'string';
        }
    }

    /**
    * Check if Swagger annotations are already added to the model.
    *
    * @param string $filePath
    * @return bool
    */
    protected function hasSwaggerAnnotations(string $filePath): bool
    {
        $fileContent = File::get($filePath);
        return strpos($fileContent, '@OA\Schema') !== false;
    }
}
