<?php

use App\Models\Location;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Codigo gravado na tag NFC da caixa: a tag leva o link /n/{codigo}.
            $table->string('nfc_code', 16)->nullable()->unique()->after('depth');
        });

        foreach (DB::table('locations')->whereNull('nfc_code')->pluck('id') as $id) {
            DB::table('locations')->where('id', $id)->update(['nfc_code' => Location::newNfcCode()]);
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['nfc_code']);
            $table->dropColumn('nfc_code');
        });
    }
};
