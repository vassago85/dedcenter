<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('advertising_mode')->default('md_reserved_window')->after('registration_closes_at');
            $table->string('md_package_status')->default('pending')->after('advertising_mode');
            $table->foreignId('full_package_brand_id')->nullable()->after('md_package_status')
                ->constrained('sponsors')->nullOnDelete();
            $table->decimal('md_package_price', 10, 2)->default(1500.00)->after('full_package_brand_id');
            $table->decimal('individual_placement_price', 10, 2)->default(500.00)->after('md_package_price');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['full_package_brand_id']);
            $table->dropColumn([
                'advertising_mode',
                'md_package_status',
                'full_package_brand_id',
                'md_package_price',
                'individual_placement_price',
            ]);
        });
    }
};
