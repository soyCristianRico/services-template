<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Catalog;

use App\Models\Service;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListServicesTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        List services in the catalog. Optional filters by category_id, is_active or name/slug substring.
        Returns id, slug, name, category, is_active and a snippet of short_description.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $data = $request->all();

        $services = Service::query()
            ->with('category:id,name,slug')
            ->when(! empty($data['category_id'] ?? null), fn ($q) => $q->where('category_id', $data['category_id']))
            ->when(array_key_exists('is_active', $data), fn ($q) => $q->where('is_active', (bool) $data['is_active']))
            ->when(! empty($data['search'] ?? null), fn ($q) => $q->where(function ($q) use ($data): void {
                $q->where('name', 'like', '%'.$data['search'].'%')
                    ->orWhere('slug', 'like', '%'.$data['search'].'%');
            }))
            ->ordered()
            ->limit($data['limit'] ?? 100)
            ->get()
            ->map(fn (Service $p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'category_id' => $p->category_id,
                'category' => $p->category?->name,
                'short_description' => $p->short_description,
                'is_active' => $p->is_active,
                'position' => $p->position,
            ])
            ->all();

        return Response::json(['services' => $services, 'count' => count($services)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()->description('Filter by category id'),
            'is_active' => $schema->boolean()->description('Filter by active state'),
            'search' => $schema->string()->description('Substring on name or slug'),
            'limit' => $schema->integer()->description('Max rows (default 100)'),
        ];
    }
}
