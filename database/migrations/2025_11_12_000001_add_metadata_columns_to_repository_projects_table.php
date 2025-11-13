<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('repository_projects', 'publish_date')) {
                $table->date('publish_date')->nullable()->after('url');
            }

            if (! Schema::hasColumn('repository_projects', 'keywords_es')) {
                $table->text('keywords_es')->nullable()->after('publish_date');
            }

            if (! Schema::hasColumn('repository_projects', 'keywords_en')) {
                $table->text('keywords_en')->nullable()->after('keywords_es');
            }

            if (! Schema::hasColumn('repository_projects', 'abstract_es')) {
                $table->text('abstract_es')->nullable()->after('keywords_en');
            }

            if (! Schema::hasColumn('repository_projects', 'abstract_en')) {
                $table->text('abstract_en')->nullable()->after('abstract_es');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repository_projects', function (Blueprint $table) {
            $table->dropColumn([
                'publish_date',
                'keywords_es',
                'keywords_en',
                'abstract_es',
                'abstract_en',
            ]);
        });
    }
};
