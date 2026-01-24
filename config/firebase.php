<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | Your Firebase project ID.
    |
    */
    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials Path
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase service account JSON file.
    |
    */
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('firebase-credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Firebase Database URL
    |--------------------------------------------------------------------------
    |
    | The URL for Firebase Realtime Database.
    |
    */
    'database_url' => env('FIREBASE_DATABASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Token Verification Cache
    |--------------------------------------------------------------------------
    |
    | Whether to cache public keys for token verification.
    |
    */
    'cache_token_verification' => env('FIREBASE_CACHE_TOKENS', true),
];
