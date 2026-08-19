<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use App\Services\Seo\RedirectPath;
use App\Services\Seo\RedirectWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateRedirectTool extends Tool
{
    protected string $description = 'Update an existing redirect. Provide id (or source_lookup) plus any subset of fields to change. Retire one by setting is_active to false — nothing is deleted through here.';

    public function __construct(protected RedirectWriter $writer) {}

    public function handle(Request $request): Response
    {
        $redirect = $this->find($request);

        if (! $redirect instanceof Redirect) {
            return Response::error('Redirect not found. Pass a valid `id`, or a `source_lookup` that matches a stored source.');
        }

        // The whole row is revalidated, not just what arrived: the rules cross-read
        // each other — a status_code of 410 changes what `destination` is allowed
        // to be, and a match_type of regex changes what `source` even means — so
        // checking a lone field against nothing would wave through combinations
        // the form would have stopped.
        $attributes = $this->writer->normalize([
            'source' => (string) ($request->get('source') ?? $redirect->source),
            'destination' => $request->get('destination', $redirect->destination),
            'match_type' => $request->get('match_type') ?? $redirect->match_type->value,
            'status_code' => $request->get('status_code') ?? $redirect->status_code->value,
            'is_active' => $request->get('is_active') ?? $redirect->is_active,
            'preserve_query' => $request->get('preserve_query') ?? $redirect->preserve_query,
            'notes' => $request->get('notes', $redirect->notes),
        ]);

        $validator = Validator::make(
            $attributes,
            $this->writer->rules($attributes, $redirect->id),
            $this->writer->messages(),
        );

        if ($validator->fails()) {
            return Response::error('Validation failed: '.implode(' ', $validator->errors()->all()));
        }

        $redirect = $this->writer->persist($attributes, $redirect->id);

        return Response::json([
            'id' => $redirect->id,
            'source' => $redirect->source,
            'destination' => $redirect->destination,
            'match_type' => $redirect->match_type->value,
            'status_code' => $redirect->status_code->value,
            'is_active' => $redirect->is_active,
            'preserve_query' => $redirect->preserve_query,
            'notes' => $redirect->notes,
            'hits' => $redirect->hits,
        ]);
    }

    protected function find(Request $request): ?Redirect
    {
        $id = $request->get('id');

        if (! empty($id)) {
            return Redirect::query()->find($id);
        }

        $lookup = $request->get('source_lookup');

        if (empty($lookup)) {
            return null;
        }

        // Matched twice: a regex row is stored raw, an exact one normalised, and
        // the caller does not know which it is looking at.
        return Redirect::query()
            ->where('source', RedirectPath::normalize((string) $lookup))
            ->orWhere('source', trim((string) $lookup))
            ->first();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Redirect id (or use source_lookup)'),
            'source_lookup' => $schema->string()->description('Stored source to find the redirect by (alternative to id)'),
            'source' => $schema->string()->description('Change the old address it answers to'),
            'destination' => $schema->string()->description('Change where it sends visitors'),
            'match_type' => $schema->string()
                ->enum(array_map(fn (RedirectMatchType $t): string => $t->value, RedirectMatchType::cases()))
                ->description('exact, prefix or regex'),
            'status_code' => $schema->integer()
                ->enum(array_map(fn (RedirectStatusCode $c): int => $c->value, RedirectStatusCode::cases()))
                ->description('301, 302, 307, 308 or 410. Moving to 410 drops the destination.'),
            'is_active' => $schema->boolean()->description('Set false to retire the redirect without losing the row'),
            'preserve_query' => $schema->boolean()->description('Carry the incoming query string to the destination'),
            'notes' => $schema->string()->description('Why this redirect exists'),
        ];
    }
}
