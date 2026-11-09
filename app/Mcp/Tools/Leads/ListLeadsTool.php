<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Leads;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListLeadsTool extends Tool
{
    protected string $description = 'List leads. Filter by status, landing_id, or substring search on email/name/phone.';

    public function handle(Request $request): Response
    {
        $data = $request->all();

        $leads = Lead::query()
            ->with('landing:id,slug')
            ->when(! empty($data['status'] ?? null), fn ($q) => $q->where('status', $data['status']))
            ->when(! empty($data['landing_id'] ?? null), fn ($q) => $q->where('landing_id', $data['landing_id']))
            ->when(! empty($data['search'] ?? null), fn ($q) => $q->where(function ($q) use ($data): void {
                $q->where('email', 'like', '%'.$data['search'].'%')
                    ->orWhere('name', 'like', '%'.$data['search'].'%')
                    ->orWhere('phone', 'like', '%'.$data['search'].'%');
            }))
            ->orderByDesc('created_at')
            ->limit($data['limit'] ?? 100)
            ->get()
            ->map(fn (Lead $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'email' => $l->email,
                'phone' => $l->phone,
                'status' => $l->status->value,
                'landing' => $l->landing?->slug,
                'created_at' => $l->created_at?->toIso8601String(),
            ])
            ->all();

        return Response::json(['leads' => $leads, 'count' => count($leads)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_map(fn (LeadStatus $s): string => $s->value, LeadStatus::cases()))
                ->description('Filter by lead status'),
            'landing_id' => $schema->integer()->description('Filter by source landing id'),
            'search' => $schema->string()->description('Substring on email, name or phone'),
            'limit' => $schema->integer()->description('Max rows (default 100)'),
        ];
    }
}
