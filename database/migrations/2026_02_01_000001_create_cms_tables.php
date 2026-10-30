<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS: biblioteca media, pagini compuse din blocuri (cu versiuni = ciorne și revizii), blocuri reutilizabile,
 * întrebări frecvente aprobate, meniuri, redirecturi și mesajele primite prin formulare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique()->comment('Relativ la directorul public media/');
            $table->string('folder', 60)->default('general')->comment('catalog | lentile | pagini | productie | general');
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 80);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('sha256', 64)->index();
            $table->string('alt', 500)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('caption', 500)->nullable()->comment('Ex. „Fotografie ilustrativă”');
            $table->json('variants')->nullable()->comment('{"webp":{"480":"cale",...}}');
            $table->string('source', 20)->default('upload')->comment('upload | import');
            $table->string('source_url', 500)->nullable()->comment('URL-ul original (ex. wp-content/uploads)');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('source_url');
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique()->comment('Calea publică, ex. /productia-noastra/');
            $table->string('kind', 20)->default('page')->comment('page | home | guide | policy');
            $table->string('title', 255);
            $table->string('status', 20)->default('draft')->comment('draft | published | archived');
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedInteger('lock_version')->default(0)->comment('Versiune optimistă pentru editare simultană');
            $table->foreignId('editing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('editing_until')->nullable();
            $table->boolean('needs_legal_review')->default(false)->comment('Texte juridice: nu se publică fără revizie');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'status']);
        });

        Schema::create('cms_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 255);
            $table->json('seo')->nullable()->comment('{"title","description","noindex","canonical","og_image"}');
            $table->json('blocks')->comment('Listă de blocuri tipizate: [{"type":"hero","data":{...}}]');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unique(['page_id', 'number']);
        });

        Schema::create('cms_reusable_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('type', 40);
            $table->json('data');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cms_faq_items', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 60)->index()->comment('comanda | reteta | lentile | livrare | plata | b2b | retur | general');
            $table->string('question', 255);
            $table->text('answer')->comment('HTML sanitizat');
            $table->boolean('is_published')->default(false);
            $table->boolean('use_in_assistant')->default(false)->comment('Sursă aprobată pentru asistent');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('menu', 30)->default('header')->comment('header | footer | footer_legal');
            $table->foreignId('parent_id')->nullable()->constrained('cms_menu_items')->cascadeOnDelete();
            $table->string('label', 160);
            $table->string('url', 500)->nullable();
            $table->string('description', 255)->nullable()->comment('Text scurt în meniul extins');
            $table->boolean('new_tab')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['menu', 'parent_id', 'sort']);
        });

        Schema::create('cms_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 255)->unique();
            $table->string('target', 500)->comment('Gol pentru 410 Gone');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('origin', 20)->default('manual')->comment('manual | import');
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cms_not_found_log', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->string('last_referer', 255)->nullable();
            $table->timestamp('last_seen_at');
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('form', 40)->comment('contact | ajutor_lentile | parteneriat');
            $table->text('payload')->comment('Criptat (Laravel encrypter)');
            $table->string('status', 20)->default('noua');
            $table->string('reference', 40)->nullable()->unique();
            $table->char('ip_hash', 64)->nullable();
            $table->string('source_url', 255)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['form', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('cms_not_found_log');
        Schema::dropIfExists('cms_redirects');
        Schema::dropIfExists('cms_menu_items');
        Schema::dropIfExists('cms_faq_items');
        Schema::dropIfExists('cms_reusable_blocks');
        Schema::dropIfExists('cms_page_versions');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('media');
    }
};
