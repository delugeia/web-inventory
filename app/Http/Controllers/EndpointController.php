<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEndpointRequest;
use App\Http\Requests\UpdateEndpointRequest;
use App\Models\Endpoint;

class EndpointController extends Controller
{
    public function index()
    {
        $endpoints = Endpoint::query()
            ->orderByDesc('id')
            ->paginate(15);

        return view('endpoints.index', compact('endpoints'));
    }

    public function create()
    {
        return view('endpoints.create');
    }

    public function store(StoreEndpointRequest $request)
    {
        Endpoint::create($request->validated());

        return redirect()->route('endpoints.index');
    }

    public function edit(Endpoint $endpoint)
    {
        return view('endpoints.edit', compact('endpoint'));
    }

    public function update(UpdateEndpointRequest $request, Endpoint $endpoint)
    {
        $endpoint->update($request->validated());

        return redirect()->route('endpoints.index');
    }

    public function destroy(Endpoint $endpoint)
    {
        $endpoint->delete();

        return redirect()->route('endpoints.index');
    }
}