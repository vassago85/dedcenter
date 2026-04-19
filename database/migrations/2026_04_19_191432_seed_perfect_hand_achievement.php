<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the "Perfect Hand" Royal Flush signature badge.
 *
 * Running it as a data migration (rather than re-seeding) so production
 * picks it up on the next deploy without any manual `db:seed` call and
 * without touching any other achievement rows. Safe to run repeatedly —
 * the slug uniqueness on `achievements.slug` makes it a no-op after the
 * first run.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('achievements')->updateOrInsert(
            ['slug' => 'perfect-hand'],
            [
                'label' => 'Perfect Hand',
                'description' => 'Hit every target at every distance — a flawless Royal Flush run. Almost unheard of.',
                'category' => 'match_special',
                'scope' => 'match',
                'is_repeatable' => true,
                'sort_order' => 290,
                'competition_type' => 'royal_flush',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // We keep the row on rollback so historical user_achievements stay
        // linked. Flagging it inactive hides it from the gallery and from
        // any future awarding logic without orphaning awarded instances.
        DB::table('achievements')
            ->where('slug', 'perfect-hand')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }
};
