<?php

namespace App\Exports;

use App\Models\GameCopy;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GameCopiesExport implements FromCollection, WithHeadings, WithMapping
{
    const COLUMNS = [
        'game_title'     => 'Game Title',
        'title'          => 'Edition / Variant',
        'platform'       => 'Platform',
        'region'         => 'Region',
        'purchase_price' => 'Purchase Price (DKK)',
        'purchase_date'  => 'Purchase Date',
        'notes'          => 'Notes',
        'parts'          => 'Parts & Condition',
    ];

    public function __construct(
        private Collection $copies,
        private array $selectedColumns,
    ) {}

    public function collection()
    {
        return $this->copies;
    }

    public function headings(): array
    {
        return array_map(
            fn ($column) => self::COLUMNS[$column],
            $this->selectedColumns,
        );
    }

    public function map($copy): array
    {
        return array_map(
            fn ($column) => $this->resolveColumn($copy, $column),
            $this->selectedColumns,
        );
    }

    private function resolveColumn(GameCopy $copy, string $column): string
    {
        return match ($column) {
            'game_title'     => $copy->game->title,
            'title'          => $copy->title ?? '',
            'platform'       => $copy->platform->name,
            'region'         => $copy->region ?? '',
            'purchase_price' => $copy->purchase_price !== null ? (string) $copy->purchase_price : '',
            'purchase_date'  => $copy->purchase_date?->format('Y-m-d') ?? '',
            'notes'          => $copy->notes ?? '',
            'parts'          => $copy->parts
                ->map(fn ($part) => "{$part->type}: {$part->condition->name}")
                ->implode('; '),
        };
    }
}
