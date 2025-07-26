<?php

return [
    /*
     * Set a custom dashboard configuration
     */
    'dashboard' => [
        'port' => env('LARAVEL_WEBSOCKETS_PORT', 6001),
    ],

    /*
     * Set a custom dashboard configuration
     */
    'apps' => [
        [
            'id' => env('PUSHER_APP_ID'),
            'name' => env('APP_NAME'),
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'path' => env('PUSHER_APP_PATH'),
            'capacity' => null,
            'enable_client_messages' => false,
            'enable_statistics' => true,
        ],
    ],

    /*
     * Only change this if you're using a custom domain for your WebSocket server
     */
    'domain' => env('LARAVEL_WEBSOCKETS_DOMAIN'),

    /*
     * Only change this if you're using a custom path for your WebSocket server
     */
    'path' => env('LARAVEL_WEBSOCKETS_PATH', 'laravel-websockets'),

    /*
     * Set this to true if you want to use the same SSL certificate for
     * both HTTP and WebSocket connections.
     */
    'use_same_ssl_certificate' => env('LARAVEL_WEBSOCKETS_USE_SAME_SSL_CERTIFICATE', false),

    /*
     * Set this to true if you want to verify the peer when connecting to
     * the WebSocket server.
     */
    'verify_peer' => env('LARAVEL_WEBSOCKETS_VERIFY_PEER', false),

    /*
     * You can get your certificate's path by doing an openssl connection.
     * You can add the chain certificates by concatenating them into one file
     * and uploading it into your server, or you can add them here.
     * You can also add your key here, but we recommend not storing it in
     * your database directly. You can use the application's config
     * encryption feature instead. For extra security, you can declare
     * your key content in an environment variable.
     *
     * Please remove the sample chain certificate below before getting started.
     */
    'local_cert' => env('LARAVEL_WEBSOCKETS_LOCAL_CERT', null),

    /*
     * Passphrase (optional) used for your local certificate. You can remove
     * this parameter if you are not using a passphrase.
     */
    'local_pk' => env('LARAVEL_WEBSOCKETS_LOCAL_PK', null),

    /*
     * Passphrase (optional) used for your local certificate. You can remove
     * this parameter if you are not using a passphrase.
     */
    'passphrase' => env('LARAVEL_WEBSOCKETS_PASSPHRASE', null),

    /*
     * Statistic
     */
    'statistics' => [
        /*
         * This model will be used to retrieve the statistics. If you want to store the
         * statistics in a different table, you can create your own model and extend
         * the `WebSocketsStatisticsEntry` model.
         */
        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatisticsEntry::class,

        /*
         * The Statistics will be made every second, and then the data will be stored in the
         * database, each record will be kept for 24 hours.
         *
         * The interval in seconds to save all statistics to the database.
         */
        'interval_in_seconds' => 60,

        /*
         * When the clean-command is executed, all recorded statistics older than
         * the number of days specified here will be deleted.
         *
         * The number of days to keep the statistics in the database.
         */
        'delete_statistics_older_than_days' => 60,

        /*
         * Use an DNS resolver to make the requests to the statistics dashboard
         * default is 8.8.8.8, but you can change it to any of the Google DNS servers.
         *
         * 8.8.8.8 / 8.8.4.4
         */
        'dns_resolver' => '8.8.8.8',

        /*
         * Enable the statistics collector to collect and store the number of
         * concurrent connections. This can be disabled if you want to reduce
         * the database load, but it is needed to display the concurrent
         * connections in the dashboard.
         */
        'enabled' => env('LARAVEL_WEBSOCKETS_STATISTICS_ENABLED', true),
    ],

    /*
     * Maximum execution time of a request in seconds. The default value is 5 minutes.
     */
    'max_execution_time' => env('LARAVEL_WEBSOCKETS_MAX_EXECUTION_TIME', 300),

    /*
     * Enable the presence channel manager.
     */
    'enable_presence_channels' => true,

    /*
     * Enable the statistics collector.
     */
    'enable_statistics' => true,

    /*
     * Define the optional SSL context for your WebSocket connections.
     * You can see all available options at: http://php.net/manual/en/context.ssl.php
     */
    'ssl' => [
        /*
         * Path to local certificate file on filesystem. It must be a PEM encoded file which
         * contains your certificate and private key. It can optionally contain the
         * certificate chain of issuers. The private key also may be contained
         * in a separate file specified by local_pk.
         */
        'local_cert' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT', null),

        /*
         * Path to local private key file on filesystem in case of separate files for
         * certificate (local_cert) and private key.
         */
        'local_pk' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_PK', null),

        /*
         * Passphrase for your local_cert file.
         */
        'passphrase' => env('LARAVEL_WEBSOCKETS_SSL_PASSPHRASE', null),

        /*
         * This option requires stream to support the ssl. Peer name field will be set into
         * $_SERVER['SSL_PEER_NAME'] variable. Could be used to set up SNI for the context.
         */
        'peer_name' => env('LARAVEL_WEBSOCKETS_SSL_PEER_NAME', null),

        /*
         * If set to true then the stream will be created with the SNI_enabled flag
         * (if supported by the underlying SSL stream implementation).
         * You can set this to false to disable the automatic SNI handling.
         */
        'verify_peer_name' => env('LARAVEL_WEBSOCKETS_SSL_VERIFY_PEER_NAME', false),

        /*
         * Set to true if the certificate should be self-signed.
         */
        'allow_self_signed' => env('LARAVEL_WEBSOCKETS_SSL_ALLOW_SELF_SIGNED', false),
    ],

    /*
     * Channel Manager
     * This class handles how channel authentication is handled.
     * By default, persistence channels are stored in an array by the running webservers.
     * The only requirement is that the class should implement
     * `ChannelManager` interface provided by this package.
     */
    'channel_manager' => \BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager::class,
]; 