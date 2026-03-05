<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Web Inventory') }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }

        .card {
            width: 100%;
            max-width: 38rem;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(17, 24, 39, 0.08);
            text-align: center;
        }

        h1 {
            margin: 0 0 0.75rem;
            font-size: 1.75rem;
            line-height: 1.2;
        }

        p {
            margin: 0;
            color: #4b5563;
            line-height: 1.5;
        }

        .cta {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.7rem 1.1rem;
            border-radius: 0.5rem;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }

        .cta:hover,
        .cta:focus-visible {
            background: #1d4ed8;
        }

        .note {
            margin-top: 1rem;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Welcome to {{ config('app.name', 'Web Inventory') }}</h1>
        <p>This is a temporary landing page for local development.</p>
        <a class="cta" href="{{ route('endpoints.index') }}">View Endpoints</a>
        <p class="note">This screen will be replaced with register/login in a future update.</p>
    </main>
</body>
</html>
