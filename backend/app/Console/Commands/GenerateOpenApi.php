<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Builds api-spec/openapi.json from the real routes so the contract cannot drift from the code. */
class GenerateOpenApi extends Command
{
    protected $signature = 'api:openapi {--out=../api-spec/openapi.json}';

    protected $description = 'Generate the OpenAPI document from registered API routes';

    public function handle(): int
    {
        $paths = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            $path = '/'.substr($uri, strlen('api/v1/'));
            $mw = $route->gatherMiddleware();
            $public = ! in_array('auth:sanctum', $mw, true);
            $perms = [];
            $module = null;
            foreach ($mw as $m) {
                if (is_string($m) && str_starts_with($m, 'perm:')) {
                    $perms = array_merge($perms, explode(',', substr($m, 5)));
                }
                if (is_string($m) && str_starts_with($m, 'module:')) {
                    $module = substr($m, 7);
                }
            }
            preg_match_all('/\{(\w+)\??\}/', $path, $mm);
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $op = [
                    'tags' => [explode('/', trim($path, '/'))[0]],
                    'summary' => ucfirst(strtolower($method)).' '.$path,
                    'responses' => ['200' => ['description' => 'OK'], '401' => ['$ref' => '#/components/responses/Error'], '403' => ['$ref' => '#/components/responses/Error'], '422' => ['$ref' => '#/components/responses/Error']],
                ];
                if ($public) {
                    $op['security'] = [];
                }
                if ($perms) {
                    $op['x-required-permission-any-of'] = array_values(array_unique($perms));
                }
                if ($module) {
                    $op['x-module'] = $module;
                }
                if ($mm[1]) {
                    $op['parameters'] = array_map(fn ($p) => ['name' => $p, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], $mm[1]);
                }
                $paths[$path][strtolower($method)] = $op;
            }
        }
        ksort($paths);
        $components = json_decode(file_get_contents(base_path('../api-spec/components.json')), true);
        $doc = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Edusmart Hospitality API', 'version' => '1.0.0', 'description' => 'Generated from routes. Money is whole RWF. Every business record has a client-generatable UUID `id`, a `version` counter and soft-delete tombstones for sync. Errors use {error:{code,message,details}}.'],
            'servers' => [['url' => 'https://api.hospitality.edusmartconsult.com/api/v1']],
            'security' => [['bearer' => []]],
            'paths' => $paths,
            'components' => $components,
        ];
        $out = $this->option('out');
        file_put_contents($out, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info(count($paths).' paths written to '.$out);

        return self::SUCCESS;
    }
}
