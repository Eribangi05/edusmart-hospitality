<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Services\CustomFields;
use App\Support\ResourceRegistry;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** One controller for every registry resource. */
class ResourceController extends Controller
{
    private function def(Request $r): array
    {
        $key = $r->route()->defaults['resource'];
        $def = ResourceRegistry::all()[$key] ?? throw new ApiError('not_found', 'Unknown resource.', 404);
        $def['key'] = $key;

        return $def;
    }

    private function authorize(Request $r, array $def, string $verb): void
    {
        $user = $r->user();
        if (isset($def['fixed'])) {
            // configuration is readable by every signed-in user; changing it needs the fixed permission
            $ok = $verb === 'view' || $user->can2($def['fixed']);
        } else {
            $ok = false;
            foreach ($def['perms_any'] ?? [$def['perm']] as $m) {
                $ok = $ok || $user->can2("$m.$verb");
            }
        }
        if (! $ok) {
            throw new ApiError('forbidden', 'You do not have permission to do this.', 403);
        }
        if (! empty($def['module'])) {
            if (! in_array($def['module'], app(Tenancy::class)->get()->enabledModules(), true)) {
                throw new ApiError('module_disabled', "The {$def['module']} module is not enabled.", 403);
            }
        }
    }

    public function index(Request $r)
    {
        $def = $this->def($r);
        $this->authorize($r, $def, 'view');
        $q = $def['model']::query();
        foreach ($def['filters'] ?? [] as $f) {
            if ($r->has("filter.$f")) {
                $v = $r->input("filter.$f");
                $q->where($f, $v === 'null' ? null : $v);
            }
        }
        if ($term = $r->query('q')) {
            $cols = $def['search'] ?? [];
            $q->where(function ($w) use ($cols, $term) {
                foreach ($cols as $c) {
                    $w->orWhere($c, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }
        if ($since = $r->query('updated_since')) {
            $q->where('updated_at', '>', $since);
        }
        if ($r->boolean('with_trashed') && method_exists($def['model'], 'bootSoftDeletes')) {
            $q->withTrashed();
        }
        $sort = $r->query('sort', '-created_at');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (in_array($col, array_merge($def['filters'] ?? [], ['created_at', 'updated_at', 'name', 'date', 'number', 'position']), true)) {
            $q->orderBy($col, $dir);
        }
        $page = $q->paginate(min((int) $r->query('per_page', 50), 200));

        return response()->json(['data' => $page->items(), 'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function show(Request $r, string $id)
    {
        $def = $this->def($r);
        $this->authorize($r, $def, 'view');

        return response()->json(['data' => $def['model']::findOrFail($id)]);
    }

    public function store(Request $r)
    {
        $def = $this->def($r);
        $this->authorize($r, $def, 'create');
        $rules = $def['rules']();
        $data = Validator::make($r->all(), $rules + ['id' => 'nullable|uuid'])->validate();
        if (array_key_exists('meta', $rules)) {
            $data['meta'] = CustomFields::validate($this->entity($def), $data['meta'] ?? null, true);
        }
        if (isset($data['id']) && $def['model']::withTrashed()->where('id', $data['id'])->exists()) {
            throw new ApiError('duplicate', 'A record with this id already exists.', 409);
        }
        $model = new $def['model'];
        $model->forceFill($data);
        $model->save();
        Audit::log($this->entity($def).'.created', $this->entity($def), $model->id, null, $model->toArray());

        return response()->json(['data' => $model->fresh()], 201);
    }

    public function update(Request $r, string $id)
    {
        $def = $this->def($r);
        $this->authorize($r, $def, 'edit');
        $model = $def['model']::findOrFail($id);
        $rules = collect($def['rules']())->map(fn ($v) => is_string($v) ? 'sometimes|'.$v : array_merge(['sometimes'], (array) $v))->all();
        $data = Validator::make($r->all(), $rules + ['version' => 'nullable|integer'])->validate();
        if (isset($data['version']) && isset($model->version) && (int) $data['version'] !== (int) $model->version) {
            throw new ApiError('version_conflict', 'The record was changed by someone else.', 409, ['server_version' => $model->version]);
        }
        unset($data['version']);
        if (array_key_exists('meta', $data)) {
            $data['meta'] = CustomFields::validate($this->entity($def), $data['meta'], false);
        }
        $before = $model->toArray();
        $model->forceFill($data)->save();
        Audit::log($this->entity($def).'.updated', $this->entity($def), $model->id, $before, $model->fresh()->toArray());

        return response()->json(['data' => $model->fresh()]);
    }

    public function destroy(Request $r, string $id)
    {
        $def = $this->def($r);
        $this->authorize($r, $def, 'delete');
        $model = $def['model']::findOrFail($id);
        Audit::log($this->entity($def).'.deleted', $this->entity($def), $model->id, $model->toArray());
        $model->delete();

        return response()->noContent();
    }

    private function entity(array $def): string
    {
        return str_replace('-', '_', rtrim($def['key'], 's'));
    }
}
