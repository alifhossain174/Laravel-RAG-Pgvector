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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pdftotext' => [
        'binary' => env('PDFTOTEXT_PATH'),
    ],

    'embedding' => [
        'provider' => env('EMBEDDING_PROVIDER', 'gemini'),
    ],

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'gemini'),
        'temperature' => env('LLM_TEMPERATURE', 0.2),
        'max_output_tokens' => env('LLM_MAX_OUTPUT_TOKENS', 1200),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-2'),
        'embedding_dimensions' => env('EMBEDDING_DIMENSIONS', 1536),
        'chat_model' => env('GEMINI_CHAT_MODEL', 'gemini-2.5-flash'),
    ],

    'rag' => [
        'top_k' => env('RAG_TOP_K', 6),
        'max_context_chars' => env('RAG_MAX_CONTEXT_CHARS', 12000),
        'retrieval_max_distance' => env('RAG_RETRIEVAL_MAX_DISTANCE'),
        'message_rate_limit_per_minute' => env('RAG_MESSAGE_RATE_LIMIT_PER_MINUTE', 20),
    ],

];
