<?php

return [
    'enabled' => env('RENDERED_CAPTURE_ENABLED', false),
    'node_binary' => env('RENDERED_CAPTURE_NODE_BINARY', 'node'),
    'script_path' => env('RENDERED_CAPTURE_SCRIPT_PATH', base_path('scripts/render-page.mjs')),
    'timeout_seconds' => env('RENDERED_CAPTURE_TIMEOUT_SECONDS', 12),
    'settle_milliseconds' => env('RENDERED_CAPTURE_SETTLE_MILLISECONDS', 1500),
    'max_text_chars' => env('RENDERED_CAPTURE_MAX_TEXT_CHARS', 50000),
    'max_html_chars' => env('RENDERED_CAPTURE_MAX_HTML_CHARS', 100000),
    'viewport_width' => env('RENDERED_CAPTURE_VIEWPORT_WIDTH', 1366),
    'viewport_height' => env('RENDERED_CAPTURE_VIEWPORT_HEIGHT', 900),
    'user_agent' => env('RENDERED_CAPTURE_USER_AGENT', env('RESOLVER_USER_AGENT', 'web-inventory-renderer/1.0')),
];
