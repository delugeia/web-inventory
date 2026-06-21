<?php

$input = json_decode(stream_get_contents(STDIN), true);

echo json_encode([
    'ok' => true,
    'title' => 'Rendered Test Page',
    'text' => 'This Page Is Under Construction Coming Soon Network Solutions',
    'html' => '<html><body><h1>This Page Is Under Construction</h1></body></html>',
    'durationMs' => 321,
    'finalUrl' => $input['url'] ?? null,
], JSON_THROW_ON_ERROR);
