<?php

declare(strict_types=1);

use App\Services\Seo\RedirectImporter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.admin')]
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
};
?>

<div class="mx-auto max-w-5xl space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Importar redirecciones</flux:heading>
        <flux:button :href="route('admin.redirects.index')" variant="ghost" icon="arrow-left">Volver</flux:button>
    </div>

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

    <div class="flex flex-wrap items-center gap-4">
        <flux:button wire:click="review" variant="primary" icon="eye">Revisar</flux:button>

        <flux:field variant="inline">
            <flux:label>…o sube un fichero</flux:label>
            <flux:input type="file" wire:model="file" accept=".csv,.txt,.tsv" class="max-w-xs" />
        </flux:field>
        <flux:error name="file" />
    </div>

    @if ($this->rows)
        <flux:separator />

        <div class="flex flex-wrap gap-3">
            <flux:badge color="green">{{ $this->counts[\App\Services\Seo\RedirectImporter::OUTCOME_NEW] }} nuevas</flux:badge>
            <flux:badge color="amber">{{ $this->counts[\App\Services\Seo\RedirectImporter::OUTCOME_REPLACES] }} ya existen</flux:badge>
            <flux:badge color="zinc">{{ $this->counts[\App\Services\Seo\RedirectImporter::OUTCOME_DUPLICATE] }} repetidas en la lista</flux:badge>
            <flux:badge color="red">{{ $this->counts[\App\Services\Seo\RedirectImporter::OUTCOME_ERROR] }} con problemas</flux:badge>
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
                        <flux:table.cell class="font-mono text-xs">{{ $row['source'] }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $row['destination'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['status_code'] }}</flux:table.cell>
                        <flux:table.cell>
                            @switch ($row['outcome'])
                                @case (\App\Services\Seo\RedirectImporter::OUTCOME_NEW)
                                    <flux:badge size="sm" color="green">Se creará</flux:badge>
                                    @break
                                @case (\App\Services\Seo\RedirectImporter::OUTCOME_REPLACES)
                                    <flux:badge size="sm" :color="$overwrite ? 'amber' : 'zinc'">
                                        {{ $overwrite ? 'Se reemplazará' : 'Se dejará como está' }}
                                    </flux:badge>
                                    @break
                                @default
                                    <flux:badge size="sm" color="red">Se descartará</flux:badge>
                            @endswitch

                            @if ($row['message'])
                                <flux:text size="sm" class="text-zinc-500">{{ $row['message'] }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <flux:field variant="inline">
            <flux:switch wire:model.live="overwrite" />
            <flux:label>Reemplazar las que ya existan</flux:label>
            <flux:description>Sin esto, las direcciones que ya tengan redirección se dejan intactas.</flux:description>
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.redirects.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="import" variant="primary" icon="check">
                Importar
            </flux:button>
        </div>
    @endif
</div>
