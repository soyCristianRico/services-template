<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Services\Seo\RedirectWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateRedirectTool extends Tool
{
    protected string $description = 'Create a redirect from an old address to a new one. `source` is a path on this site (a pasted full URL is reduced to its path); `destination` is a path or an absolute URL elsewhere. Defaults: exact match, 301, active, query string preserved.';

    public function __construct(protected RedirectWriter $writer) {}

    public function handle(Request $request): Response
    {
        $attributes = $this->writer->normalize([
            'source' => (string) ($request->get('source') ?? ''),
            'destination' => $request->get('destination'),
            'match_type' => $request->get('match_type') ?? RedirectMatchType::Exact->value,
            'status_code' => $request->get('status_code') ?? RedirectStatusCode::MovedPermanently->value,
            'is_active' => $request->get('is_active') ?? true,
            'preserve_query' => $request->get('preserve_query') ?? true,
            'notes' => $request->get('notes'),
        ]);

        $validator = Validator::make(
            $attributes,
            $this->writer->rules($attributes),
            $this->writer->messages(),
        );

        if ($validator->fails()) {
            return Response::error('Validation failed: '.implode(' ', $validator->errors()->all()));
        }

        $redirect = $this->writer->persist($attributes);

        return Response::json([
            'id' => $redirect->id,
            'source' => $redirect->source,
            'destination' => $redirect->destination,
            'match_type' => $redirect->match_type->value,
            'status_code' => $redirect->status_code->value,
            'is_active' => $redirect->is_active,
            'preserve_query' => $redirect->preserve_query,
            'notes' => $redirect->notes,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()
                ->description('Old address on this site, e.g. /tutoria. Stored lower case, without host, query or trailing slash. For match_type=regex it is the raw pattern instead.')
                ->required(),
            'destination' => $schema->string()
                ->description('Where it goes: a path starting with / or an absolute https:// URL. Omit only with status_code=410.'),
            'match_type' => $schema->string()
                ->enum(array_map(fn (RedirectMatchType $t): string => $t->value, RedirectMatchType::cases()))
                ->description('exact = only that address (the usual one); prefix = it and everything under it, the remainder is appended to the destination; regex = pattern with $1 captures'),
            'status_code' => $schema->integer()
                ->enum(array_map(fn (RedirectStatusCode $c): int => $c->value, RedirectStatusCode::cases()))
                ->description('301 permanent (default, moves the ranking), 302/307 temporary, 308 permanent keeping the method, 410 retired with no replacement (no destination)'),
            'is_active' => $schema->boolean()->description('Defaults to true'),
            'preserve_query' => $schema->boolean()->description('Carry ?utm_source=… from the old address to the new one. Defaults to true.'),
            'notes' => $schema->string()->description('Why this redirect exists. A year later nobody remembers.'),
        ];
    }
}
