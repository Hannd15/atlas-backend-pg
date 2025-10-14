<?php

use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$resources = require __DIR__.'/../config/api-resources.php';

$header = <<<'PHP'
<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

class ResourcePaths
{

PHP;

$footer = "}\n";

$body = '';

foreach ($resources as $slug => $config) {
    $tag = $config['tag'] ?? Str::headline($slug);
    $pluralStudly = Str::studly(str_replace('-', ' ', $slug));
    $singularStudly = Str::studly(Str::singular(str_replace('-', ' ', $slug)));
    $methodBase = Str::camel($pluralStudly);
    $identifierDescription = 'Resource identifier';

    if (! empty($config['composite_key'])) {
        $identifierDescription = 'Composite identifier composed of '.implode(', ', $config['composite_key']).' separated by pipe (|).';
    }

    $body .= <<<PHP
    /**
     * @OA\Get(
     *     path="/api/{$slug}",
     *     operationId="list{$pluralStudly}",
     *     tags={"{$tag}"},
     *     summary="List {$tag}",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated {$tag}",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/{$singularStudly}"))
     *         )
     *     )
     * )
     */
    public function {$methodBase}Index(): void {}

    /**
     * @OA\Post(
     *     path="/api/{$slug}",
     *     operationId="create{$singularStudly}",
     *     tags={"{$tag}"},
     *     summary="Create {$singularStudly}",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/{$singularStudly}")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/{$singularStudly}"))
     * )
     */
    public function {$methodBase}Store(): void {}

    /**
     * @OA\Get(
     *     path="/api/{$slug}/{identifier}",
     *     operationId="show{$singularStudly}",
     *     tags={"{$tag}"},
     *     summary="Show {$singularStudly}",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="{$identifierDescription}", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/{$singularStudly}")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function {$methodBase}Show(): void {}

    /**
     * @OA\Put(
     *     path="/api/{$slug}/{identifier}",
     *     operationId="update{$singularStudly}",
     *     tags={"{$tag}"},
     *     summary="Update {$singularStudly}",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="{$identifierDescription}", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/{$singularStudly}")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/{$singularStudly}")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function {$methodBase}Update(): void {}

    /**
     * @OA\Delete(
     *     path="/api/{$slug}/{identifier}",
     *     operationId="delete{$singularStudly}",
     *     tags={"{$tag}"},
     *     summary="Delete {$singularStudly}",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="{$identifierDescription}", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function {$methodBase}Destroy(): void {}

PHP;
}

file_put_contents(__DIR__.'/../app/OpenApi/ResourcePaths.php', $header.$body.$footer);
