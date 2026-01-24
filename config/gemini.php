<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    |
    | Your Google Gemini API key for AI features.
    |
    */
    'api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gemini API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Gemini API requests.
    |
    */
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    |
    | The Gemini model to use for AI operations.
    |
    */
    'model' => env('GEMINI_MODEL', 'gemini-pro'),
];
