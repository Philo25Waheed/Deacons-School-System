<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                if (! Schema::hasColumn('attendance', 'attended_lesson')) {
                    $table->boolean('attended_lesson')->default(true)->after('status');
                }
                if (! Schema::hasColumn('attendance', 'attended_pamphlet')) {
                    $table->boolean('attended_pamphlet')->default(false)->after('attended_lesson');
                }
                if (! Schema::hasColumn('attendance', 'attended_liturgy')) {
                    $table->boolean('attended_liturgy')->default(false)->after('attended_pamphlet');
                }
                if (! Schema::hasColumn('attendance', 'points_awarded')) {
                    $table->integer('points_awarded')->default(0)->after('attended_liturgy');
                }
            });
        }

        if (Schema::hasTable('exams')) {
            Schema::table('exams', function (Blueprint $table) {
                if (! Schema::hasColumn('exams', 'reward_points')) {
                    $table->integer('reward_points')->default(5)->after('duration_minutes');
                }
                if (! Schema::hasColumn('exams', 'pass_percentage')) {
                    $table->integer('pass_percentage')->default(50)->after('reward_points');
                }
            });
        }

        if (Schema::hasTable('exam_results')) {
            Schema::table('exam_results', function (Blueprint $table) {
                if (! Schema::hasColumn('exam_results', 'points_awarded')) {
                    $table->integer('points_awarded')->default(0)->after('status');
                }
                if (! Schema::hasColumn('exam_results', 'cheating_violations')) {
                    $table->integer('cheating_violations')->default(0)->after('points_awarded');
                }
                if (! Schema::hasColumn('exam_results', 'cheating_details')) {
                    $table->text('cheating_details')->nullable()->after('cheating_violations');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->dropColumn(['attended_lesson', 'attended_pamphlet', 'attended_liturgy', 'points_awarded']);
            });
        }

        if (Schema::hasTable('exams')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn(['reward_points', 'pass_percentage']);
            });
        }

        if (Schema::hasTable('exam_results')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->dropColumn(['points_awarded', 'cheating_violations', 'cheating_details']);
            });
        }
    }
};
