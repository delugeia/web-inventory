<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>View Source - {{ $endpoint->page_title ?: $endpoint->location }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/cached-source.js'])
        <style>
            pre.source-code {
                margin: 0;
                border-radius: 0;
                min-height: calc(100vh - 4.75rem);
                font-size: 13px;
                line-height: 1.6;
            }

            pre.source-code code {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            }

            pre.source-code.source-wrap,
            pre.source-code.source-wrap code {
                white-space: pre-wrap;
                word-break: break-word;
            }
        </style>
    </head>
    <body x-data="{ wrap: false }" class="min-h-screen bg-white text-slate-900">
        <header class="sticky top-0 z-10 border-b border-rose-200 bg-rose-50 px-4 py-2 shadow-sm">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-sm font-semibold text-slate-900">
                        Cached Source View ({{ \App\Support\DateTimeDisplay::format($endpoint->last_checked_at) }})
                        <span class="font-semibold text-rose-700">NOTE: URLs have been updated to absolute.</span>
                    </h1>
                    <p class="font-mono text-xs text-slate-600 break-all">{{ $endpoint->resolved_url ?: $endpoint->location }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input class="rounded border-slate-300 text-slate-900 shadow-sm focus:border-slate-500 focus:ring-slate-500" type="checkbox" x-model="wrap">
                        <span>Line wrap</span>
                    </label>
                    <a class="inline-flex items-center justify-center rounded-md border border-rose-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-rose-100" href="{{ route('endpoints.cached', $endpoint) }}">View Cached Page</a>
                </div>
            </div>
        </header>

        <main>
            <pre class="source-code line-numbers language-html" x-bind:class="{ 'source-wrap': wrap }"><code class="language-html">{{ $endpoint->page_content }}</code></pre>
        </main>
    </body>
</html>
