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

    /**
     * Override in child for nested resources
     * Example return:
     *  return [
     *      'param' => 'post',                   // route parameter name
     *      'fk'    => 'post_id',                // foreign key on child table
     *      'model' => \App\Models\Post::class,  // (optional) parent model class
     *  ];
     */
    protected function parentConfig(): array { return []; }

    protected function baseQuery()
    {
        $model = static::model();
        $q = $model::query();

        // If nested, scope by parent FK from route param
        $cfg = $this->parentConfig();
        if (!empty($cfg['fk']) && !empty($cfg['param'])) {
            $parentId = request()->route($cfg['param']);
            if ($parentId instanceof \Illuminate\Database\Eloquent\Model) {
                $parentId = $parentId->getKey();
            }
            if ($parentId !== null) {
                $q->where($q->getModel()->getTable().'.'.$cfg['fk'], $parentId);
            }
        }
        return $q;
    }

    protected function searchable(): array { return []; }

    protected function sortable(): array { return []; }

    abstract protected function requestClass(): string;

    abstract protected function resource(): string;

    protected function resourceCollection(): string { return $this->resource(); }

    public function index(Request $request)
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

        if ($sort = $request->query('sort')) {
            $direction = 'asc';
            if (str_starts_with($sort, '-')) { $direction = 'desc'; $sort = ltrim($sort, '-'); }
            $allowed = $this->sortable();
            if (in_array($sort, $allowed)) $q->orderBy($sort, $direction);
        }

        return $this->resourceCollection()::collection(
            $q->paginate($request->integer('per_page', 15))->appends($request->query())
        );
    }

    protected function filterable(): array { return []; }

    public function store(Request $request)
    {
        $data = $this->validated($request, 'store');

        // If nested, auto-fill FK from route
        $cfg = $this->parentConfig();
        if (!empty($cfg['fk']) && !empty($cfg['param']) && !isset($data[$cfg['fk']])) {
            $parentId = $request->route($cfg['param']);
            if ($parentId instanceof \Illuminate\Database\Eloquent\Model) $parentId = $parentId->getKey();
            $data[$cfg['fk']] = $parentId;
        }

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

    public function bulk(Request $request)
    {
        $action = (string)$request->input('action');
        $ids = $request->input('ids', []);
        $q = $this->baseQuery()->whereIn('id', $ids);

        $count = 0;
        switch ($action) {
            case 'delete': $count = $q->delete(); break;
            case 'forceDelete': $count = $q->forceDelete(); break;
            case 'restore': $count = $q->restore(); break;
            default:
                if (str_starts_with($action, 'update:')) {
                    $payload = json_decode(substr($action, 7), true) ?: [];
                    $count = $q->update($payload);
                }
        }
        return response()->json(['ok' => true, 'affected' => $count]);
    }

    public function export(Request $request): StreamedResponse
    {
        $q = $this->baseQuery();
        if ($search = $request->query('q')) {
            $q->where(function ($sub) use ($search) {
                foreach ($this->searchable() as $col) $sub->orWhere($col, 'like', "%{$search}%");
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
                    foreach ($columns as $col) $data[] = data_get($row, $col);
                    fputcsv($out, $data, $config['delimiter'], $config['enclosure'], $config['escape']);
                }
            });
            fclose($out);
        };

        return response()->streamDownload($callback, class_basename(static::model()).'-export.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function exportableColumns(): array { return ['id','created_at','updated_at']; }

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
