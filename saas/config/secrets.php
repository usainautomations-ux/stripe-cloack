<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Key provider
    |--------------------------------------------------------------------------
    |
    | "local" reads the master key from the environment and is intended for
    | development. "kms" delegates wrapping to a hosted key service so the
    | master key is never present in this process's memory — that is the
    | production setting, and the one that makes a stolen database dump
    | insufficient to recover a Stripe key.
    |
    */

    'provider' => env('SECRETS_PROVIDER', 'local'),

    'local' => [
        'master_key' => env('SECRETS_MASTER_KEY'),
    ],

    'kms' => [
        'key_id' => env('SECRETS_KMS_KEY_ID'),
        'region' => env('SECRETS_KMS_REGION', 'us-east-1'),
    ],

];
