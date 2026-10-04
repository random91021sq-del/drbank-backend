<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('exams')
            ->whereNull('title')
            ->update(['title' => 'Evaluación de progreso']);

        Schema::table('exams', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
