<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fountain_photos')->truncate();

        Schema::table('fountain_photos', function (Blueprint $table) {
            $table->dropIndex('fountain_photos_object_id_index');
            $table->dropColumn('object_id');
        });

        Schema::table('fountain_photos', function (Blueprint $table) {
            $table->string('shape_hash', 40)->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('fountain_photos', function (Blueprint $table) {
            $table->dropColumn('shape_hash');
        });

        Schema::table('fountain_photos', function (Blueprint $table) {
            $table->unsignedBigInteger('object_id')->index()->after('id');
        });
    }
};
