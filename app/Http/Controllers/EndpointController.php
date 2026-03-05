<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEndpointRequest;
use App\Http\Requests\UpdateEndpointRequest;
use App\Models\Endpoint;
use App\Services\EndpointResolver;
use App\Support\EndpointLocationNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;

class EndpointController extends Controller
{
    public function index()
    {
        $perPageOptions = [15, 25, 50, 100];
        $perPage = (int) request('per_page', 15);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 15;
        }

        $allowedSorts = [
            'location',
            'name',
            'resolved_url',
            'last_status_code',
            'last_checked_at',
        ];

        $sort = request('sort', 'location');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'location';
        }

        $direction = request('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $search = trim((string) request('q', ''));

        $endpoints = Endpoint::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('location', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->appends([
                'q' => $search !== '' ? $search : null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ]);

        return view('endpoints.index', compact('endpoints', 'search', 'sort', 'direction', 'perPage', 'perPageOptions'));
    }

    public function show(Endpoint $endpoint)
    {
        return view('endpoints.show', compact('endpoint'));
    }

    public function resolve(Endpoint $endpoint, EndpointResolver $resolver)
    {
        if ($endpoint->last_checked_at === null) {
            $resolver->resolve($endpoint);
            $endpoint->refresh();
        }

        return view('endpoints.resolve', compact('endpoint'));
    }

    public function resolveStore(Endpoint $endpoint, EndpointResolver $resolver)
    {
        $result = $resolver->resolve($endpoint);

        if ($result['resolved']) {
            return redirect()
                ->route('endpoints.resolve', $endpoint)
                ->with('status', "Resolved {$endpoint->location} to {$result['resolved_url']} with status {$result['status_code']}.");
        }

        return redirect()
            ->route('endpoints.resolve', $endpoint)
            ->with('status', "Resolve failed for {$endpoint->location}: {$result['failure_reason']}");
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

    public function importForm()
    {
        return view('endpoints.import');
    }

    public function importStore()
    {
        $lines = (string) request('lines', '');
        $rawLines = preg_split('/\r\n|\r|\n/', $lines);
        $errors = new MessageBag();
        $rows = [];
        $now = now();

        foreach ($rawLines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            $location = $parts[0] ?? '';
            $location = EndpointLocationNormalizer::normalize($location);
            $name = isset($parts[1]) ? trim($parts[1]) : null;
            $lineNumber = $index + 1;

            if ($location === '') {
                $errors->add('lines', "Line {$lineNumber}: location is required.");
                continue;
            }

            if (preg_match('/\s/', $location)) {
                $errors->add('lines', "Line {$lineNumber}: location cannot contain spaces.");
                continue;
            }

            if (mb_strlen($location) > 2048) {
                $errors->add('lines', "Line {$lineNumber}: location must be 2048 characters or fewer.");
                continue;
            }

            if ($name !== null && mb_strlen($name) > 255) {
                $errors->add('lines', "Line {$lineNumber}: name must be 255 characters or fewer.");
                continue;
            }

            $rows[] = [
                'location' => $location,
                'name' => $name !== '' ? $name : null,
                'resolved_url' => null,
                'last_status_code' => null,
                'last_checked_at' => null,
                'failure_reason' => null,
                'redirect_followed' => false,
                'redirect_count' => 0,
                'redirect_chain' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($errors->any()) {
            return back()->withErrors($errors)->withInput();
        }

        if (count($rows) === 0) {
            return back()->withErrors(['lines' => 'Please provide at least one valid line.'])->withInput();
        }

        DB::transaction(function () use ($rows) {
            Endpoint::query()->insert($rows);
        });

        return redirect()->route('endpoints.index')->with('status', 'Imported '.count($rows).' endpoints.');
    }
}
