<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'marketingdashboard'),
            'username' => env('DB_USERNAME', 'istanatiara'),
            'password' => env('DB_PASSWORD', 'istanatiara'),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        'pgsql_jkt' => [
            'driver' => 'pgsql',
            'host' => '192.168.41.25',
            'port' => '5432',
            'database' => 'pwmjkt',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_bdg' => [
            'driver' => 'pgsql',
            'host' => '192.168.42.25',
            'port' => '5432',
            'database' => 'pwmbdg',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_smg' => [
            'driver' => 'pgsql',
            'host' => '192.168.43.25',
            'port' => '5432',
            'database' => 'pwmsmg',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_mdn' => [
            'driver' => 'pgsql',
            'host' => '192.168.44.25',
            'port' => '5432',
            'database' => 'pwmmdn',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_plb' => [
            'driver' => 'pgsql',
            'host' => '192.168.45.25',
            'port' => '5432',
            'database' => 'pwmplb',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_bjm' => [
            'driver' => 'pgsql',
            'host' => '192.168.46.25',
            'port' => '5432',
            'database' => 'pwmbjm',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_dps' => [
            'driver' => 'pgsql',
            'host' => '192.168.47.25',
            'port' => '5432',
            'database' => 'pwmdps',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_mks' => [
            'driver' => 'pgsql',
            'host' => '192.168.48.25',
            'port' => '5432',
            'database' => 'pm',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_pku' => [
            'driver' => 'pgsql',
            'host' => '192.168.49.25',
            'port' => '5432',
            'database' => 'pwmpku',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_sby' => [
            'driver' => 'pgsql',
            'host' => '192.168.40.25',
            'port' => '5432',
            'database' => 'pwmsby',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_ptk' => [
            'driver' => 'pgsql',
            'host' => '192.168.50.25',
            'port' => '5432',
            'database' => 'pwmptk',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_crb' => [
            'driver' => 'pgsql',
            'host' => '192.168.51.25',
            'port' => '5432',
            'database' => 'pwmcrb',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_pdg' => [
            'driver' => 'pgsql',
            'host' => '192.168.52.25',
            'port' => '5432',
            'database' => 'pwmpdg',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_pwt' => [
            'driver' => 'pgsql',
            'host' => '192.168.53.25',
            'port' => '5432',
            'database' => 'pwmpwt',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_bks' => [
            'driver' => 'pgsql',
            'host' => '192.168.54.25',
            'port' => '5432',
            'database' => 'abg',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_lmp' => [
            'driver' => 'pgsql',
            'host' => '192.168.56.25',
            'port' => '5432',
            'database' => 'pwmlmp',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_trg' => [
            'driver' => 'pgsql',
            'host' => '192.168.55.25',
            'port' => '5432',
            'database' => 'mpm',
            'username' => 'adempiere',
            'password' => 'adempiere',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Connections
    |--------------------------------------------------------------------------
    |
    | Define groups of connections to be used by synchronization commands.
    |
    */

    'sync_connections' => [
        'adempiere' => [
            'pgsql_jkt',
            'pgsql_bdg',
            'pgsql_smg',
            'pgsql_mdn',
            'pgsql_plb',
            'pgsql_bjm',
            'pgsql_dps',
            'pgsql_mks',
            'pgsql_pku',
            'pgsql_sby',
            'pgsql_ptk',
            'pgsql_crb',
            'pgsql_pdg',
            'pgsql_pwt',
            'pgsql_bks',
            'pgsql_lmp',
            'pgsql_trg',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_database_'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
