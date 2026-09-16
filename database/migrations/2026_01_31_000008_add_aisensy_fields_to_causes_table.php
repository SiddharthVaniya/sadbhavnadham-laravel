<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->text('aisensy_api_key')->nullable()->after('cta_text');
            $table->string('aisensy_country_code', 10)->nullable()->after('aisensy_api_key');
            $table->string('aisensy_payment_link_campaign')->nullable()->after('aisensy_country_code');
            $table->string('aisensy_thank_you_general_campaign')->nullable()->after('aisensy_payment_link_campaign');
            $table->string('aisensy_thank_you_tree_campaign')->nullable()->after('aisensy_thank_you_general_campaign');
            $table->string('aisensy_thank_you_general_image')->nullable()->after('aisensy_thank_you_tree_campaign');
            $table->string('aisensy_thank_you_tree_image')->nullable()->after('aisensy_thank_you_general_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('causes', function (Blueprint $table) {
            $table->dropColumn([
                'aisensy_api_key',
                'aisensy_country_code',
                'aisensy_payment_link_campaign',
                'aisensy_thank_you_general_campaign',
                'aisensy_thank_you_tree_campaign',
                'aisensy_thank_you_general_image',
                'aisensy_thank_you_tree_image',
            ]);
        });
    }
};
