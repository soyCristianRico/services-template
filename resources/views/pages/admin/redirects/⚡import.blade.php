<?php

declare(strict_types=1);

use App\Services\Seo\RedirectImporter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.admin')]
#[Title('Importar redirecciones')]
class extends Component
{
    use WithFileUploads;

    public string $raw = '';

    public bool $overwrite = false;

    public bool $reviewed = false;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    /**
     * Reading the file into the same textarea keeps one path through the parser
     * and lets the person fix a bad line before importing instead of after.
     */
    public function updatedFile(): void
    {
        $this->validate([
            'file' => ['file', 'max:2048', 'mimetypes:text/plain,text/csv,application/csv,text/tab-separated-values'],
        ], [], ['file' => 'fichero']);

        $this->raw = (string) file_get_contents($this->file->getRealPath());
        $this->file = null;
        $this->reviewed = true;
    }

    public function review(): void
    {
        $this->reviewed = true;
        unset($this->rows);

        if ($this->rows === []) {
            Flux::toast(text: 'No hay ninguna línea que revisar.', variant: 'warning');
        }
    }

    public function import(RedirectImporter $importer): void
    {
        $result = $importer->import($this->rows, $this->overwrite);

        $summary = "Importadas: {$result['created']} nuevas, {$result['updated']} actualizadas, {$result['skipped']} descartadas.";

        session()->flash('status', $summary);

        $this->redirectRoute('admin.redirects.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        if (! $this->reviewed || trim($this->raw) === '') {
            return [];
        }

        return app(RedirectImporter::class)->parse($this->raw);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $counts = [
            RedirectImporter::OUTCOME_NEW => 0,
            RedirectImporter::OUTCOME_REPLACES => 0,
            RedirectImporter::OUTCOME_DUPLICATE => 0,
            RedirectImporter::OUTCOME_ERROR => 0,
        ];

        foreach ($this->rows as $row) {
            $counts[$row['outcome']]++;
        }

        return $counts;
    }

    /**
     * What the preview badge says a line will do, which depends on the overwrite switch.
     *
     * @param  array<string, mixed>  $row
     * @return array{label: string, color: string}
     */
    public function outcomeBadge(array $row): array
    {
        return match ($row['outcome']) {
            RedirectImporter::OUTCOME_NEW => ['label' => 'Se creará', 'color' => 'green'],
            RedirectImporter::OUTCOME_REPLACES => $this->overwrite
                ? ['label' => 'Se reemplazará', 'color' => 'amber']
                : ['label' => 'Se dejará como está', 'color' => 'zinc'],
            default => ['label' => 'Se descartará', 'color' => 'red'],
        };
    }
};
?>

<div class="mx-auto max-w-3xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => 'Redirecciones', 'href' => route('admin.redirects.index')],
        ['label' => 'Importar lista'],
    ]">
        Importar redirecciones
    </x-admin.page-header>

    <section class="space-y-6">
        <flux:heading size="lg">Lista</flux:heading>

        <flux:callout icon="information-circle" color="zinc">
            <flux:callout.text>
                Una redirección por línea: <strong>dirección antigua</strong>, <strong>dirección nueva</strong> y,
                opcionalmente, el <strong>código</strong> (301 por defecto) y el <strong>tipo</strong>
                (exacta por defecto). Vale separar con tabulador, punto y coma o coma.
                Las líneas que empiezan por <code>#</code> se ignoran.
            </flux:callout.text>
            <flux:callout.text class="font-mono text-xs">
                /curso-antiguo;/cursos/curso-nuevo<br>
                /promo-verano;/cursos;302<br>
                /seccion-retirada;;410<br>
                ^/blog/\d{4}/(.+)$;/blog/$1;301;regex
            </flux:callout.text>
        </flux:callout>

        <flux:field>
            <flux:label>Pega la lista</flux:label>
            <flux:textarea wire:model="raw" rows="10" class="font-mono text-xs" placeholder="/direccion-antigua;/direccion-nueva" />
            <flux:error name="raw" />
        </flux:field>

        <div>
            <flux:button wire:click="review" icon="eye">Revisar</flux:button>
        </div>

        <flux:field>
            <flux:label badge="Opcional">…o sube un fichero</flux:label>
            <flux:description>CSV, TSV o texto. Su contenido se vuelca en la lista de arriba para revisarlo.</flux:description>
            <flux:input type="file" wire:model="file" accept=".csv,.txt,.tsv" class="max-w-xs" />
            <flux:error name="file" />
        </flux:field>
    </section>

    @if ($this->rows)
        <section class="space-y-6">
            <flux:heading size="lg">Revisión</flux:heading>

            <div class="flex flex-wrap gap-3">
                <flux:badge color="green">{{ $this->counts[RedirectImporter::OUTCOME_NEW] }} nuevas</flux:badge>
                <flux:badge color="amber">{{ $this->counts[RedirectImporter::OUTCOME_REPLACES] }} ya existen</flux:badge>
                <flux:badge color="zinc">{{ $this->counts[RedirectImporter::OUTCOME_DUPLICATE] }} repetidas en la lista</flux:badge>
                <flux:badge color="red">{{ $this->counts[RedirectImporter::OUTCOME_ERROR] }} con problemas</flux:badge>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Línea</flux:table.column>
                    <flux:table.column>Dirección antigua</flux:table.column>
                    <flux:table.column>Va a</flux:table.column>
                    <flux:table.column>Código</flux:table.column>
                    <flux:table.column>Qué pasará</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row wire:key="row-{{ $row['line'] }}">
                            <flux:table.cell class="text-zinc-400">{{ $row['line'] }}</flux:table.cell>
                            <flux:table.cell class="max-w-48 truncate font-mono text-xs" title="{{ $row['source'] }}">{{ $row['source'] }}</flux:table.cell>
                            <flux:table.cell class="max-w-48 truncate font-mono text-xs" title="{{ $row['destination'] }}">{{ $row['destination'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row['status_code'] }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$this->outcomeBadge($row)['color']">{{ $this->outcomeBadge($row)['label'] }}</flux:badge>

                                @if ($row['message'])
                                    <flux:text size="sm" class="text-zinc-500">{{ $row['message'] }}</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <flux:field>
                <flux:label>Reemplazar las que ya existan</flux:label>
                <flux:description>Sin esto, las direcciones que ya tengan redirección se dejan intactas.</flux:description>
                <flux:switch wire:model.live="overwrite" />
            </flux:field>
        </section>
    @endif

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-zinc-50 lg:start-64">
        <div class="mx-auto flex max-w-3xl justify-end gap-3 px-8 py-4">
            <flux:button :href="route('admin.redirects.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="import" variant="primary" icon="check" :disabled="! $this->rows">Importar</flux:button>
        </div>
    </div>
</div>
