<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Cached Copy - {{ $endpoint->page_title ?: $endpoint->location }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        <div class="min-h-screen">
            <header class="sticky top-0 z-10 border-b border-rose-200 bg-rose-50 px-4 py-2 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-sm font-semibold text-slate-900">
                            Cached Page View ({{ \App\Support\DateTimeDisplay::format($endpoint->last_checked_at) }})
                            <span class="font-semibold text-rose-700">NOTE: External assets may load from the live site.</span>
                        </h1>
                        <p class="font-mono text-xs text-slate-600 break-all">{{ $endpoint->resolved_url ?: $endpoint->location }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a class="inline-flex items-center justify-center rounded-md border border-rose-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-rose-100" href="{{ route('endpoints.cached.source', $endpoint) }}">View Cached Source</a>
                    </div>
                </div>
            </header>

            <iframe
                class="block w-full border-0 bg-white"
                style="height: calc(100dvh - 4.75rem); min-height: 44rem;"
                src="{{ route('endpoints.cached.content', $endpoint) }}"
                title="Cached copy of {{ $endpoint->location }}"
                sandbox="allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"
            ></iframe>
        </div>
    </body>
</html>
