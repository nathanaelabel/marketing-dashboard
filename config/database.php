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
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
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
            'host' => env('DB_PGSQL_JKT_HOST'),
            'port' => env('DB_PGSQL_JKT_PORT'),
            'database' => env('DB_PGSQL_JKT_DATABASE'),
            'username' => env('DB_PGSQL_JKT_USERNAME'),
            'password' => env('DB_PGSQL_JKT_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_bdg' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_BDG_HOST'),
            'port' => env('DB_PGSQL_BDG_PORT'),
            'database' => env('DB_PGSQL_BDG_DATABASE'),
            'username' => env('DB_PGSQL_BDG_USERNAME'),
            'password' => env('DB_PGSQL_BDG_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_smg' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_SMG_HOST'),
            'port' => env('DB_PGSQL_SMG_PORT'),
            'database' => env('DB_PGSQL_SMG_DATABASE'),
            'username' => env('DB_PGSQL_SMG_USERNAME'),
            'password' => env('DB_PGSQL_SMG_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_mdn' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_MDN_HOST'),
            'port' => env('DB_PGSQL_MDN_PORT'),
            'database' => env('DB_PGSQL_MDN_DATABASE'),
            'username' => env('DB_PGSQL_MDN_USERNAME'),
            'password' => env('DB_PGSQL_MDN_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_plb' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_PLB_HOST'),
            'port' => env('DB_PGSQL_PLB_PORT'),
            'database' => env('DB_PGSQL_PLB_DATABASE'),
            'username' => env('DB_PGSQL_PLB_USERNAME'),
            'password' => env('DB_PGSQL_PLB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_bjm' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_BJM_HOST'),
            'port' => env('DB_PGSQL_BJM_PORT'),
            'database' => env('DB_PGSQL_BJM_DATABASE'),
            'username' => env('DB_PGSQL_BJM_USERNAME'),
            'password' => env('DB_PGSQL_BJM_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_dps' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_DPS_HOST'),
            'port' => env('DB_PGSQL_DPS_PORT'),
            'database' => env('DB_PGSQL_DPS_DATABASE'),
            'username' => env('DB_PGSQL_DPS_USERNAME'),
            'password' => env('DB_PGSQL_DPS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_mks' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_MKS_HOST'),
            'port' => env('DB_PGSQL_MKS_PORT'),
            'database' => env('DB_PGSQL_MKS_DATABASE'),
            'username' => env('DB_PGSQL_MKS_USERNAME'),
            'password' => env('DB_PGSQL_MKS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_pku' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_PKU_HOST'),
            'port' => env('DB_PGSQL_PKU_PORT'),
            'database' => env('DB_PGSQL_PKU_DATABASE'),
            'username' => env('DB_PGSQL_PKU_USERNAME'),
            'password' => env('DB_PGSQL_PKU_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_sby' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_SBY_HOST'),
            'port' => env('DB_PGSQL_SBY_PORT'),
            'database' => env('DB_PGSQL_SBY_DATABASE'),
            'username' => env('DB_PGSQL_SBY_USERNAME'),
            'password' => env('DB_PGSQL_SBY_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_ptk' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_PTK_HOST'),
            'port' => env('DB_PGSQL_PTK_PORT'),
            'database' => env('DB_PGSQL_PTK_DATABASE'),
            'username' => env('DB_PGSQL_PTK_USERNAME'),
            'password' => env('DB_PGSQL_PTK_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_crb' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_CRB_HOST'),
            'port' => env('DB_PGSQL_CRB_PORT'),
            'database' => env('DB_PGSQL_CRB_DATABASE'),
            'username' => env('DB_PGSQL_CRB_USERNAME'),
            'password' => env('DB_PGSQL_CRB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_pdg' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_PDG_HOST'),
            'port' => env('DB_PGSQL_PDG_PORT'),
            'database' => env('DB_PGSQL_PDG_DATABASE'),
            'username' => env('DB_PGSQL_PDG_USERNAME'),
            'password' => env('DB_PGSQL_PDG_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_pwt' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_PWT_HOST'),
            'port' => env('DB_PGSQL_PWT_PORT'),
            'database' => env('DB_PGSQL_PWT_DATABASE'),
            'username' => env('DB_PGSQL_PWT_USERNAME'),
            'password' => env('DB_PGSQL_PWT_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_bks' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_BKS_HOST'),
            'port' => env('DB_PGSQL_BKS_PORT'),
            'database' => env('DB_PGSQL_BKS_DATABASE'),
            'username' => env('DB_PGSQL_BKS_USERNAME'),
            'password' => env('DB_PGSQL_BKS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_lmp' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_LMP_HOST'),
            'port' => env('DB_PGSQL_LMP_PORT'),
            'database' => env('DB_PGSQL_LMP_DATABASE'),
            'username' => env('DB_PGSQL_LMP_USERNAME'),
            'password' => env('DB_PGSQL_LMP_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'adempiere',
        ],
        'pgsql_trg' => [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_TRG_HOST'),
            'port' => env('DB_PGSQL_TRG_PORT'),
            'database' => env('DB_PGSQL_TRG_DATABASE'),
            'username' => env('DB_PGSQL_TRG_USERNAME'),
            'password' => env('DB_PGSQL_TRG_PASSWORD'),
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
        // Cabang utama - sync jam 18:00 WIB (server aktif 24 jam)
        'adempiere' => [
            'pgsql_trg',
            'pgsql_bks',
            'pgsql_jkt',
            'pgsql_lmp',
            'pgsql_bdg',
            'pgsql_mks',
            'pgsql_sby',
            'pgsql_smg',
            'pgsql_pwt',
            'pgsql_dps',
            'pgsql_pdg',
            'pgsql_mdn',
        ],
        // Cabang dengan server tidak aktif malam hari - sync jam 09:00 WIB
        // Juga menerima retry otomatis dari cabang yang gagal di sync sore
        'adempiere_morning' => [
            'pgsql_bjm',
            'pgsql_pku',
            'pgsql_plb',
            'pgsql_ptk',
            'pgsql_crb',
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
