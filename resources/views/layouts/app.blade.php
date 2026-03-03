<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <nav class="mx-auto flex max-w-5xl items-center gap-4 px-6 py-4 text-sm font-medium">
                <a class="text-slate-900 hover:text-slate-700" href="{{ route('endpoints.index') }}">Endpoints</a>
                <a class="text-slate-900 hover:text-slate-700" href="{{ route('endpoints.create') }}">Create</a>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </main>
    </body>
</html>
