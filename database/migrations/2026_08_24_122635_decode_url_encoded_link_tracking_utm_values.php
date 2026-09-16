<?php

use App\Models\AnalyticsEvent;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\LinkTrackingSummary;
use App\Models\LinkTrackingVisit;
use App\Support\Attribution\AttributionParameters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;

return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    public function up(): void
    {
        $this->decodeModel(LinkTrackingVisit::query(), decodeExtra: true);
        $this->decodeModel(LinkTrackingSummary::query());
        $this->decodeModel(DonationOrder::query());
        $this->decodeModel(DonationSubscription::query());
        $this->decodeModel(AnalyticsEvent::query());
    }

    public function down(): void
    {
        // Encoded values cannot be restored losslessly.
    }

    private function decodeModel(\Illuminate\Database\Eloquent\Builder $query, bool $decodeExtra = false): void
    {
        $query->orderBy('id')->chunkById(100, function (Collection $rows) use ($decodeExtra): void {
            /** @var Model $row */
            foreach ($rows as $row) {
                $dirty = false;

                foreach (self::COLUMNS as $column) {
                    $current = $row->getAttribute($column);
                    if (! is_string($current) || $current === '') {
                        continue;
                    }

                    $decoded = AttributionParameters::decodeQueryValue($current);
                    if (is_string($decoded) && $decoded !== $current) {
                        $row->setAttribute($column, $decoded);
                        $dirty = true;
                    }
                }

                if ($decodeExtra && is_array($row->getAttribute('extra_params'))) {
                    $extra = $row->getAttribute('extra_params');
                    $changed = false;

                    foreach ($extra as $key => $value) {
                        if (! is_string($value) || $value === '') {
                            continue;
                        }

                        $decoded = AttributionParameters::decodeQueryValue($value);
                        if (is_string($decoded) && $decoded !== $value) {
                            $extra[$key] = $decoded;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        $row->setAttribute('extra_params', $extra);
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $row->saveQuietly();
                }
            }
        });
    }
};
