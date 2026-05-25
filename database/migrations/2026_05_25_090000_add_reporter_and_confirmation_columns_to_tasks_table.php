<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('tasks', 'whatsapp_contact_id')) {
                $table->foreignId('whatsapp_contact_id')
                    ->nullable()
                    ->after('reported_by_user_id')
                    ->constrained('whatsapp_contacts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tasks', 'resolution_submitted_by_user_id')) {
                $table->foreignId('resolution_submitted_by_user_id')
                    ->nullable()
                    ->after('updated_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tasks', 'resolution_submitted_at')) {
                $table->timestamp('resolution_submitted_at')
                    ->nullable()
                    ->after('resolution_submitted_by_user_id');
            }

            if (! Schema::hasColumn('tasks', 'reporter_confirmation_status')) {
                $table->string('reporter_confirmation_status', 32)
                    ->nullable()
                    ->after('resolution_submitted_at');
            }

            if (! Schema::hasColumn('tasks', 'reporter_confirmed_by_user_id')) {
                $table->foreignId('reporter_confirmed_by_user_id')
                    ->nullable()
                    ->after('reporter_confirmation_status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tasks', 'reporter_confirmed_at')) {
                $table->timestamp('reporter_confirmed_at')
                    ->nullable()
                    ->after('reporter_confirmed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('tasks', 'reporter_confirmed_at')) {
                $table->dropColumn('reporter_confirmed_at');
            }

            if (Schema::hasColumn('tasks', 'reporter_confirmed_by_user_id')) {
                $table->dropConstrainedForeignId('reporter_confirmed_by_user_id');
            }

            if (Schema::hasColumn('tasks', 'reporter_confirmation_status')) {
                $table->dropColumn('reporter_confirmation_status');
            }

            if (Schema::hasColumn('tasks', 'resolution_submitted_at')) {
                $table->dropColumn('resolution_submitted_at');
            }

            if (Schema::hasColumn('tasks', 'resolution_submitted_by_user_id')) {
                $table->dropConstrainedForeignId('resolution_submitted_by_user_id');
            }

            if (Schema::hasColumn('tasks', 'whatsapp_contact_id')) {
                $table->dropConstrainedForeignId('whatsapp_contact_id');
            }
        });
    }
};
