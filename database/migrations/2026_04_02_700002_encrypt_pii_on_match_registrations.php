<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;

/**
 * Widen PII columns to text (encrypted values are longer) and encrypt
 * any existing plaintext data. Encryption uses APP_KEY -- if the key
 * is rotated, existing values become unreadable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->text('sa_id_number')->nullable()->change();
            $table->text('contact_number')->nullable()->change();
        });

        // Encrypt existing plaintext values
        DB::table('match_registrations')
            ->whereNotNull('sa_id_number')
            ->where('sa_id_number', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                // Skip if already encrypted (starts with eyJ which is base64 JSON)
                if (str_starts_with($row->sa_id_number, 'eyJ')) {
                    return;
                }
                DB::table('match_registrations')->where('id', $row->id)->update([
                    'sa_id_number' => Crypt::encryptString($row->sa_id_number),
                ]);
            });

        DB::table('match_registrations')
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                if (str_starts_with($row->contact_number, 'eyJ')) {
                    return;
                }
                DB::table('match_registrations')->where('id', $row->id)->update([
                    'contact_number' => Crypt::encryptString($row->contact_number),
                ]);
            });
    }

    public function down(): void
    {
        // Decrypt values back to plaintext
        DB::table('match_registrations')
            ->whereNotNull('sa_id_number')
            ->where('sa_id_number', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                try {
                    $decrypted = Crypt::decryptString($row->sa_id_number);
                    DB::table('match_registrations')->where('id', $row->id)->update([
                        'sa_id_number' => $decrypted,
                    ]);
                } catch (\Exception) {
                    // Already plaintext
                }
            });

        DB::table('match_registrations')
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                try {
                    $decrypted = Crypt::decryptString($row->contact_number);
                    DB::table('match_registrations')->where('id', $row->id)->update([
                        'contact_number' => $decrypted,
                    ]);
                } catch (\Exception) {
                    // Already plaintext
                }
            });

        Schema::table('match_registrations', function (Blueprint $table) {
            $table->string('sa_id_number')->nullable()->change();
            $table->string('contact_number')->nullable()->change();
        });
    }
};
