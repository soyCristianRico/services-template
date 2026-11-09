<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Blog;

use App\Models\BlogPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListBlogPostsTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        List blog posts. Filter by status (all/published/draft/scheduled) or title/slug substring.
        Default status=all returns everything; pass status=published for the publicly visible list.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $data = $request->all();
        $status = $data['status'] ?? 'all';

        $posts = BlogPost::query()
            ->when(! empty($data['search'] ?? null), fn ($q) => $q->where(function ($q) use ($data): void {
                $q->where('title', 'like', '%'.$data['search'].'%')
                    ->orWhere('slug', 'like', '%'.$data['search'].'%');
            }))
            ->when($status === 'published', fn ($q) => $q->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()))
            ->when($status === 'draft', fn ($q) => $q->whereNull('published_at'))
            ->when($status === 'scheduled', fn ($q) => $q->whereNotNull('published_at')
                ->where('published_at', '>', now()))
            ->orderByDesc('updated_at')
            ->limit($data['limit'] ?? 100)
            ->get()
            ->map(fn (BlogPost $p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'author_name' => $p->author_name,
                'published_at' => $p->published_at?->toIso8601String(),
                'is_active' => $p->is_active,
                'tags' => $p->tags,
            ])
            ->all();

        return Response::json(['posts' => $posts, 'count' => count($posts)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['all', 'published', 'draft', 'scheduled'])
                ->description('Filter by publication state. Defaults to "all"'),
            'search' => $schema->string()->description('Substring on title or slug'),
            'limit' => $schema->integer()->description('Max rows (default 100)'),
        ];
    }
}
