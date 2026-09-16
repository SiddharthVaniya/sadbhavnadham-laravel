<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->string('aisensy_thank_you_campaign')->nullable()->after('aisensy_payment_link_campaign');
            $table->string('aisensy_thank_you_image')->nullable()->after('aisensy_thank_you_campaign');
        });

        DB::table('causes')->update([
            'aisensy_thank_you_campaign' => DB::raw('aisensy_thank_you_general_campaign'),
            'aisensy_thank_you_image' => DB::raw('aisensy_thank_you_general_image'),
        ]);

        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn([
                'aisensy_thank_you_general_campaign',
                'aisensy_thank_you_tree_campaign',
                'aisensy_thank_you_general_image',
                'aisensy_thank_you_tree_image',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->string('aisensy_thank_you_general_campaign')->nullable()->after('aisensy_payment_link_campaign');
            $table->string('aisensy_thank_you_tree_campaign')->nullable()->after('aisensy_thank_you_general_campaign');
            $table->string('aisensy_thank_you_general_image')->nullable()->after('aisensy_thank_you_tree_campaign');
            $table->string('aisensy_thank_you_tree_image')->nullable()->after('aisensy_thank_you_general_image');
        });

        DB::table('causes')->update([
            'aisensy_thank_you_general_campaign' => DB::raw('aisensy_thank_you_campaign'),
            'aisensy_thank_you_general_image' => DB::raw('aisensy_thank_you_image'),
        ]);

        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn([
                'aisensy_thank_you_campaign',
                'aisensy_thank_you_image',
            ]);
        });
    }
};
