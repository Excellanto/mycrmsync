<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DataConfigurationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Integration::class);

        return Inertia::render('Admin/DataConfiguration/Index', [
            'integrations' => Integration::query()
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(fn (Integration $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'slug' => $i->slug,
                    'type' => $i->type,
                    'documentation' => $i->documentation,
                    'fields' => Integration::normalizeFieldEntries($i->fields ?? []),
                    'enabled' => $i->enabled,
                    'is_system' => $i->is_system,
                ]),
            'integrationTypes' => ['CRM', 'ATS', 'ERP'],
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('update', new Integration());

        $request->merge([
            'integrations' => collect($request->input('integrations', []))->map(function ($row) {
                $doc = $row['documentation'] ?? null;
                $documentation = is_string($doc) && trim($doc) !== '' ? trim($doc) : null;

                $fieldsRaw = $row['fields'] ?? [];
                $fields = Integration::normalizeFieldEntries(is_array($fieldsRaw) ? $fieldsRaw : []);

                return [
                    'id' => $row['id'] ?? null,
                    'name' => $row['name'] ?? '',
                    'type' => $row['type'] ?? 'CRM',
                    'documentation' => $documentation,
                    'fields' => $fields,
                    'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            })->all(),
        ]);

        $data = $request->validate([
            'integrations' => ['required', 'array'],
            'integrations.*.id' => ['nullable', 'integer', 'exists:integrations,id'],
            'integrations.*.name' => ['required', 'string', 'max:80'],
            'integrations.*.type' => ['required', 'string', 'in:CRM,ATS,ERP'],
            'integrations.*.documentation' => ['nullable', 'url', 'max:2048'],
            'integrations.*.fields' => ['nullable', 'array'],
            'integrations.*.fields.*.label' => ['required', 'string', 'max:120'],
            'integrations.*.fields.*.optional' => ['required', 'boolean'],
            'integrations.*.enabled' => ['required', 'boolean'],
        ]);

        $normalizedNames = [];

        foreach ($data['integrations'] as $index => $item) {
            $name = str($item['name'])->squish()->title()->toString();
            $key = mb_strtolower($name);

            if (isset($normalizedNames[$key])) {
                throw ValidationException::withMessages([
                    "integrations.{$index}.name" => ['Duplicate integration name in this list.'],
                ]);
            }

            $normalizedNames[$key] = true;
        }

        DB::transaction(function () use ($data) {
            $payload = $data['integrations'];
            $submittedIds = collect($payload)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            Integration::query()
                ->where('is_system', false)
                ->whereNotIn('id', $submittedIds)
                ->delete();

            foreach ($payload as $item) {
                $id = isset($item['id']) ? (int) $item['id'] : null;
                $name = str($item['name'])->squish()->title()->toString();

                $attrs = [
                    'type' => $item['type'],
                    'documentation' => $item['documentation'],
                    'fields' => Integration::normalizeFieldEntries($item['fields'] ?? []),
                    'enabled' => $item['enabled'],
                ];

                if ($id) {
                    $model = Integration::query()->find($id);

                    if (! $model) {
                        continue;
                    }

                    if (! $model->is_system) {
                        $conflict = Integration::query()
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                            ->where('id', '!=', $model->id)
                            ->exists();

                        if ($conflict) {
                            throw ValidationException::withMessages([
                                'integrations' => ['An integration with this name already exists.'],
                            ]);
                        }
                    }

                    if ($model->is_system) {
                        $model->fill($attrs);
                    } else {
                        $model->fill(array_merge($attrs, ['name' => $name]));
                    }

                    $model->save();
                } else {
                    $conflict = Integration::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->exists();

                    if ($conflict) {
                        throw ValidationException::withMessages([
                            'integrations' => ['An integration with this name already exists.'],
                        ]);
                    }

                    Integration::query()->create(array_merge($attrs, [
                        'name' => $name,
                        'is_system' => false,
                    ]));
                }
            }
        });

        cache()->forget('app_settings');

        return back()->with('success', 'Integrations saved.');
    }

    public function updateIntegrationFields(Request $request, Integration $integration)
    {
        $this->authorize('update', $integration);

        $rawFields = $request->input('fields', []);
        $request->merge([
            'fields' => Integration::normalizeFieldEntries(is_array($rawFields) ? $rawFields : []),
        ]);

        $data = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.optional' => ['required', 'boolean'],
        ]);

        $integration->fields = $data['fields'] ?? [];
        $integration->save();

        cache()->forget('app_settings');

        return back()->with('success', 'Required fields saved.');
    }
}
