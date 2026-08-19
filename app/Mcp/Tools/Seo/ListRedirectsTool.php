<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use App\Services\Seo\RedirectPath;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRedirectsTool extends Tool
{
    protected string $description = 'List redirects. Filter by substring on source/destination, active state, status code or match type. Check here before creating one: `hits` says whether an address is still being visited.';

    public function handle(Request $request): Response
    {
        $data = $request->all();

        // A search for `https://example.com/Curso/` has to find the row stored as
        // `/curso`, or the agent concludes it does not exist and writes a
        // duplicate the unique index then rejects.
        $search = ! empty($data['search'] ?? null)
            ? RedirectPath::normalize((string) $data['search'])
            : null;

        $redirects = Redirect::query()
            ->when($search !== null, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('source', 'like', '%'.$search.'%')
                    ->orWhere('destination', 'like', '%'.$search.'%');
            }))
            ->when(array_key_exists('is_active', $data), fn ($q) => $q->where('is_active', (bool) $data['is_active']))
            ->when(! empty($data['status_code'] ?? null), fn ($q) => $q->where('status_code', (int) $data['status_code']))
            ->when(! empty($data['match_type'] ?? null), fn ($q) => $q->where('match_type', $data['match_type']))
            ->orderByDesc('hits')
            ->orderByDesc('id')
            ->limit((int) ($data['limit'] ?? 100))
            ->get()
            ->map(fn (Redirect $r): array => [
                'id' => $r->id,
                'source' => $r->source,
                'destination' => $r->destination,
                'match_type' => $r->match_type->value,
                'status_code' => $r->status_code->value,
                'is_active' => $r->is_active,
                'preserve_query' => $r->preserve_query,
                'hits' => $r->hits,
                'last_hit_at' => $r->last_hit_at?->toIso8601String(),
                'notes' => $r->notes,
            ])
            ->all();

        return Response::json(['redirects' => $redirects, 'count' => count($redirects)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Substring on source or destination. A full URL is reduced to its path first.'),
            'is_active' => $schema->boolean()->description('Filter by active state (omit for all)'),
            'status_code' => $schema->integer()
                ->enum(array_map(fn (RedirectStatusCode $c): int => $c->value, RedirectStatusCode::cases()))
                ->description('Filter by response code'),
            'match_type' => $schema->string()
                ->enum(array_map(fn (RedirectMatchType $t): string => $t->value, RedirectMatchType::cases()))
                ->description('Filter by how the source is matched'),
            'limit' => $schema->integer()->description('Max rows (default 100)'),
        ];
    }
}
