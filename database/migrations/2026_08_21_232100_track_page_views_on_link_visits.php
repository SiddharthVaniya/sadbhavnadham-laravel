<?php

use App\Models\LinkTrackingVisit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_tracking_visits', function (Blueprint $table) {
            $table->dropUnique('uq_visitor_sid');
            $table->string('page_path', 255)->nullable()->after('landing_url');
            $table->json('extra_params')->nullable()->after('ptype');
        });

        LinkTrackingVisit::query()->whereNull('page_path')->each(function (LinkTrackingVisit $visit): void {
            $path = '/';
            if (is_string($visit->landing_url) && $visit->landing_url !== '') {
                $parsed = parse_url($visit->landing_url, PHP_URL_PATH);
                if (is_string($parsed) && $parsed !== '') {
                    $path = $parsed;
                }
            }

            $visit->update(['page_path' => mb_substr($path, 0, 255)]);
        });

        Schema::table('link_tracking_visits', function (Blueprint $table) {
            $table->unique(['visitor_id', 'sid', 'page_path'], 'uq_visitor_sid_page');
            $table->index('page_path', 'idx_page_path');
        });
    }

    public function down(): void
    {
        Schema::table('link_tracking_visits', function (Blueprint $table) {
            $table->dropUnique('uq_visitor_sid_page');
            $table->dropIndex('idx_page_path');
            $table->dropColumn(['page_path', 'extra_params']);
            $table->unique(['visitor_id', 'sid'], 'uq_visitor_sid');
        });
    }
};
