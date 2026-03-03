@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-slate-900">Bulk Import</h1>
        <p class="text-sm text-slate-600">Paste one endpoint per line. The first token is the location. Anything after a whitespace becomes the name.</p>
        <p class="text-sm text-slate-600">Example: <code class="rounded bg-slate-100 px-1">https://example.org/reports Example Reports</code></p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="space-y-5" method="POST" action="{{ route('endpoints.import.store') }}">
        @csrf

        <div>
            <label class="block text-sm font-medium text-slate-700" for="lines">Lines</label>
            <textarea class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900" id="lines" name="lines" rows="10" placeholder="example.com&#10;https://example.org/reports Example Reports">{{ old('lines') }}</textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">Import</button>
            <a class="text-sm font-medium text-slate-600 hover:text-slate-800" href="{{ route('endpoints.index') }}">Cancel</a>
        </div>
    </form>
@endsection