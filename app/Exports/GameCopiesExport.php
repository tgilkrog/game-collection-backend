<?php

namespace App\Exports;

use App\Models\GameCopy;
use App\Models\GameCopyReview;
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
        'play_status'    => 'Play Status',
        'rating'         => 'Rating',
        'hours_played'   => 'Hours Played',
        'playthrough_count' => 'Playthrough Count',
        'would_replay'   => 'Would Replay',
        'would_recommend' => 'Would Recommend',
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
            'notes'          => $copy->review?->notes ?? '',
            'play_status'    => GameCopyReview::PLAY_STATUS_LABELS[$copy->review?->play_status] ?? '',
            'rating'         => $copy->review?->rating !== null ? (string) $copy->review->rating : '',
            'hours_played'   => $copy->review?->hours_played !== null ? (string) $copy->review->hours_played : '',
            'playthrough_count' => $copy->review?->playthrough_count !== null ? (string) $copy->review->playthrough_count : '',
            'would_replay'   => $this->boolLabel($copy->review?->would_replay),
            'would_recommend' => $this->boolLabel($copy->review?->would_recommend),
            'parts'          => $copy->parts
                ->map(fn ($part) => "{$part->type}: {$part->condition->name}")
                ->implode('; '),
        };
    }

    private function boolLabel(?bool $value): string
    {
        return match ($value) {
            true => 'Yes',
            false => 'No',
            default => '',
        };
    }
}
