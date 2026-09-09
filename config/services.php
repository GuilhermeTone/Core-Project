<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mercadolivre' => [
        'app_id' => env('ML_APP_ID'),
        'secret' => env('ML_SECRET'),
    ],

    'serper' => [
        'api_key' => env('SERPER_API_KEY'),
    ],

    'openai' => [
        'enabled' => env('OPENAI_ENABLED', false),
        'normalize_search_enabled' => env('OPENAI_NORMALIZE_SEARCH_ENABLED', true),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.4-nano'),
        'fallback_model' => env('OPENAI_FALLBACK_MODEL', 'gpt-4.1-nano'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 12),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 220),
        'cache_ttl' => (int) env('OPENAI_CACHE_TTL', 86400),
    ],

    'meilisearch' => [
        'enabled' => env('MEILISEARCH_ENABLED', false),
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'temp_index_prefix' => env('MEILISEARCH_TEMP_INDEX_PREFIX', 'crawler_candidates'),
        'timeout' => (int) env('MEILISEARCH_TIMEOUT', 5),
        'task_wait_attempts' => (int) env('MEILISEARCH_TASK_WAIT_ATTEMPTS', 20),
        'task_wait_usleep' => (int) env('MEILISEARCH_TASK_WAIT_USLEEP', 100000),
        'matching_strategy' => env('MEILISEARCH_MATCHING_STRATEGY', 'last'),
        'max_results' => (int) env('MEILISEARCH_MAX_RESULTS', 30),
        'min_ranking_score' => (float) env('MEILISEARCH_MIN_RANKING_SCORE', 0.0),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
