<?php

namespace App\Support;

use App\Models\DonationTelecallerNote;
use Illuminate\Database\Eloquent\Builder;

class TelecallerOutcomeFilter
{
    public const NONE = 'none';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForUi(): array
    {
        $options = [
            ['value' => '', 'label' => 'All telecaller responses'],
            ['value' => self::NONE, 'label' => 'Not called / no note'],
        ];

        foreach (DonationTelecallerNote::OUTCOMES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public static function apply(Builder $query, ?string $outcome): void
    {
        $outcome = trim((string) $outcome);

        if ($outcome === '') {
            return;
        }

        if ($outcome === self::NONE) {
            $query->whereDoesntHave('telecallerNotes');

            return;
        }

        if (! array_key_exists($outcome, DonationTelecallerNote::OUTCOMES)) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereHas('telecallerNotes', function (Builder $notes) use ($outcome): void {
            $table = $notes->getModel()->getTable();

            $notes
                ->where('outcome', $outcome)
                ->whereRaw(
                    '`'.$table.'`.`id` = (
                        SELECT MAX(`latest_note`.`id`)
                        FROM `'.$table.'` AS `latest_note`
                        WHERE `latest_note`.`donation_order_id` = `'.$table.'`.`donation_order_id`
                    )'
                );
        });
    }
}
