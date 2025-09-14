<?php

namespace Qnox\Crud\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Qnox\Crud\Support\QueryFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class CrudController extends Controller
{
    /** @return class-string<Model> */
    abstract public static function model(): string;

    /** Optional: override to add tenant/user scoping */
    protected function baseQuery()
    {
        $model = static::model();
        return $model::query();
    }

    /** Override to configure searchable columns */
    protected function searchable(): array { return []; }

    /** Override to map request sort to db columns whitelist */
    protected function sortable(): array { return []; }

    /** FormRequest class FQCN */
    abstract protected function requestClass(): string;

    /** Resource FQCN */
    abstract protected function resource(): string;

    protected function resourceCollection(): string { return $this->resource(); }

    public function index(Request $request)
    {
        $q = $this->baseQuery();

        // Search
        if ($search = $request->query('q')) {
            $q->where(function ($sub) use ($search) {
                foreach ($this->searchable() as $col) {
                    $sub->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        // Filter DSL
        $q = (new QueryFilters($q, $this->filterable()))->apply($request->query('filter', []));

        // Sort
        if ($sort = $request->query('sort')) {
            // allow -column for desc
            $direction = 'asc';
            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $sort = ltrim($sort, '-');
            }
            $allowed = $this->sortable();
            if (in_array($sort, $allowed)) {
                $q->orderBy($sort, $direction);
            }
        }

        return $this->resourceCollection()::collection(
            $q->paginate($request->integer('per_page', 15))->appends($request->query())
        );
    }

    /** Override to declare filterable columns and custom ops */
    protected function filterable(): array { return []; }

    public function store(Request $request)
    {
        $data = $this->validated($request, 'store');
        $m = static::model()::create($data);
        $this->afterSaved($m, 'store', $data);
        return new ($this->resource())($m);
    }

    public function show($id)
    {
        $m = $this->baseQuery()->findOrFail($id);
        return new ($this->resource())($m);
    }

    public function update(Request $request, $id)
    {
        $m = $this->baseQuery()->findOrFail($id);
        $data = $this->validated($request, 'update', $m);
        $m->update($data);
        $this->afterSaved($m, 'update', $data);
        return new ($this->resource())($m);
    }

    public function destroy($id)
    {
        $m = $this->baseQuery()->findOrFail($id);
        $m->delete();
        return response()->noContent();
    }

    /** Bulk actions: delete, restore, forceDelete, update:{key:value} */
    public function bulk(Request $request)
    {
        $action = $request->string('action')->toString();
        $ids = $request->input('ids', []);
        $q = $this->baseQuery()->whereIn('id', $ids);

        $count = 0;
        switch ($action) {
            case 'delete':
                $count = $q->delete();
                break;
            case 'forceDelete':
                $count = $q->forceDelete();
                break;
            case 'restore':
                $count = $q->restore();
                break;
            default:
                if (str_starts_with($action, 'update:')) {
                    $json = substr($action, 7);
                    $payload = json_decode($json, true) ?: [];
                    $count = $q->update($payload);
                }
        }
        return response()->json(['ok' => true, 'affected' => $count]);
    }

    /** CSV export */
    public function export(Request $request): StreamedResponse
    {
        $q = $this->baseQuery();
        if ($search = $request->query('q')) {
            $q->where(function ($sub) use ($search) {
                foreach ($this->searchable() as $col) {
                    $sub->orWhere($col, 'like', "%{$search}%");
                }
            });
        }
        $q = (new QueryFilters($q, $this->filterable()))->apply($request->query('filter', []));

        $columns = $this->exportableColumns();
        $config = config('crud.csv');

        $callback = function () use ($q, $columns, $config) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns, $config['delimiter'], $config['enclosure'], $config['escape']);

            $q->orderBy('id')->chunk($config['chunk'], function ($rows) use ($out, $columns, $config) {
                foreach ($rows as $row) {
                    $data = [];
                    foreach ($columns as $col) {
                        $data[] = data_get($row, $col);
                    }
                    fputcsv($out, $data, $config['delimiter'], $config['enclosure'], $config['escape']);
                }
            });
            fclose($out);
        };

        return response()->streamDownload($callback, class_basename(static::model()).'-export.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /** Override to choose which columns export */
    protected function exportableColumns(): array { return ['id','created_at','updated_at']; }

    /** Hook after create/update */
    protected function afterSaved(Model $model, string $action, array $data): void {}

    protected function validated(Request $request, string $action, ?Model $model = null): array
    {
        $class = $this->requestClass();
        /** @var \Illuminate\Foundation\Http\FormRequest $req */
        $req = app($class);
        $req->setContainer(app())->setRedirector(app('redirect'));
        $req->merge(['__action' => $action, '__id' => $model?->getKey()]);
        return $req->validated();
    }
}
