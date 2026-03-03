@extends('layouts.app')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-slate-900">Edit Endpoint</h1>

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="space-y-5" method="POST" action="{{ route('endpoints.update', $endpoint) }}">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-slate-700" for="name">Name</label>
            <input class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900" id="name" name="name" type="text" value="{{ old('name', $endpoint->name) }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="location">Location</label>
            <input class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900" id="location" name="location" type="text" required value="{{ old('location', $endpoint->location) }}">
        </div>

        <button class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">Save</button>
    </form>
@endsection
