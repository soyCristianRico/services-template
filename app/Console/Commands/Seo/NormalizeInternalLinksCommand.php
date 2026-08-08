<?php

declare(strict_types=1);

namespace App\Console\Commands\Seo;

use App\Models\Lead;
use App\Models\MenuItem;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\User;
use App\Services\Seo\InternalLink;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Points every stored link back at the address this site actually serves.
 *
 * Meant for the day after a clone. The content comes from an install whose
 * addresses ended in a slash, and those addresses travel inside the copy and the
 * menu. Nothing looks broken — Laravel trims the slash when it routes, so `/faqs/`
 * answers 200 exactly like `/faqs` — which is why it survives the whole clone and
 * the verification: every one of those links points at an address that is not the
 * one the canonical, the sitemap and the navigation agree on.
 *
 * The models are discovered rather than listed. A clone adds entities the template
 * never had, and a list written today is a list that is wrong by the third project
 * — silently, which is the failure mode this command exists to end.
 */
final class NormalizeInternalLinksCommand extends Command
{
    protected $signature = 'seo:normalize-links
                            {--dry-run : List what would change without writing anything}
                            {--host=* : Extra host to treat as this site, for a database cleaned from elsewhere}';

    protected $description = 'Rewrite stored internal links to relative paths with no trailing slash';

    /**
     * Models whose text is a record of what somebody did, not content this site
     * publishes. A visitor's message is quoted evidence: it says what they wrote,
     * and rewriting it would make it say something else.
     *
     * @var list<class-string<Model>>
     */
    protected const NOT_CONTENT = [
        Lead::class,
        NotFoundLog::class,
        Redirect::class,
        User::class,
    ];

    /**
     * Columns holding one bare address each, rather than markup.
     *
     * Declared and not discovered, and it is the one place worth the maintenance: a
     * whole-column rewrite has no `<a>` around it to prove the value is a page
     * link, so pointing it at the wrong column would rewrite a file path or an
     * external enrolment URL. A clone that adds its own CTA column adds it here.
     *
     * @var array<class-string<Model>, list<string>>
     */
    protected const URL_COLUMNS = [
        MenuItem::class => ['url'],
    ];

    public function handle(): int
    {
        $links = InternalLink::forSite($this->option('host'));
        $dryRun = (bool) $this->option('dry-run');

        $rows = 0;
        $columns = 0;

        foreach ($this->models() as $model) {
            $urlColumns = self::URL_COLUMNS[$model] ?? [];

            foreach ($model::query()->cursor() as $record) {
                $touched = [];

                foreach (array_keys($record->getAttributes()) as $name) {
                    $before = $record->getAttribute($name);
                    $after = $this->rewrite($links, $before, in_array($name, $urlColumns, true));

                    if ($after === $before) {
                        continue;
                    }

                    $record->setAttribute($name, $after);
                    $touched[] = $name;
                }

                if ($touched === []) {
                    continue;
                }

                $rows++;
                $columns += count($touched);

                $this->line(sprintf(
                    '  %s #%s → %s',
                    class_basename($model),
                    (string) $record->getKey(),
                    implode(', ', $touched)
                ));

                if (! $dryRun) {
                    // Timestamps left alone on purpose: a link fix is not the kind
                    // of edit that should push every record's `lastmod` in the
                    // sitemap to today, all at once.
                    $record::withoutTimestamps(fn () => $record->save());
                }
            }
        }

        if ($rows === 0) {
            $this->info('Every stored link already points at a canonical address.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d column(s) across %d record(s).',
            $dryRun ? 'Would rewrite' : 'Rewrote',
            $columns,
            $rows
        ));

        if ($dryRun) {
            $this->comment('Nothing was written. Run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Every model of the application that stores content, whatever a clone added.
     *
     * @return list<class-string<Model>>
     */
    protected function models(): array
    {
        $models = [];

        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            $class = $this->classFor($file);

            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            if (in_array($class, self::NOT_CONTENT, true) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            // A model backed by no table is a model whose migration this install
            // does not carry. It is not an error here, just nothing to sweep.
            if (Schema::hasTable((new $class)->getTable())) {
                $models[] = $class;
            }
        }

        sort($models);

        return $models;
    }

    /** @return class-string */
    protected function classFor(SplFileInfo $file): string
    {
        $relative = Str::after($file->getRealPath(), app_path('Models').DIRECTORY_SEPARATOR);

        /** @var class-string $class */
        $class = 'App\\Models\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

        return $class;
    }

    /**
     * Rewrite one attribute, whatever shape it arrives in.
     *
     * An array comes back as an array so the model's cast writes it out as the JSON
     * it was, keys and nesting intact — a link buried in a landing's blocks is
     * reached without anybody having to name the block.
     *
     * A markup column is safe to sweep blind because `inHtml` only touches the
     * `href` of an `<a>`: on a name, a slug or a price it is a no-op.
     */
    protected function rewrite(InternalLink $links, mixed $value, bool $isUrlColumn): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->rewrite($links, $item, $isUrlColumn), $value);
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        return $isUrlColumn ? $links->normalize($value) : $links->inHtml($value);
    }
}
