<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to enforce database-level unique constraints and race condition defense.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            if (Schema::hasTable('attendance')) {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS unique_student_attendance_date ON attendance(student_id, attendance_date)');
            }
            if (Schema::hasTable('exam_results')) {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS unique_exam_student_attempt ON exam_results(exam_id, student_id)');
            }
            if (Schema::hasTable('parent_student')) {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS unique_parent_student_link ON parent_student(parent_id, student_id)');
            }
            if (Schema::hasTable('servant_classes')) {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS unique_servant_class_assignment ON servant_classes(servant_id, class_id)');
            }

            return;
        }

        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                try {
                    $table->unique(['student_id', 'attendance_date'], 'unique_student_attendance_date');
                } catch (Throwable $e) {
                    // Index may already exist
                }
            });
        }

        if (Schema::hasTable('exam_results')) {
            Schema::table('exam_results', function (Blueprint $table) {
                try {
                    $table->unique(['exam_id', 'student_id'], 'unique_exam_student_attempt');
                } catch (Throwable $e) {
                    // Index may already exist
                }
            });
        }

        if (Schema::hasTable('parent_student')) {
            Schema::table('parent_student', function (Blueprint $table) {
                try {
                    $table->unique(['parent_id', 'student_id'], 'unique_parent_student_link');
                } catch (Throwable $e) {
                    // Index may already exist
                }
            });
        }

        if (Schema::hasTable('servant_classes')) {
            Schema::table('servant_classes', function (Blueprint $table) {
                try {
                    $table->unique(['servant_id', 'class_id'], 'unique_servant_class_assignment');
                } catch (Throwable $e) {
                    // Index may already exist
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->dropUnique('unique_student_attendance_date');
            });
        }

        if (Schema::hasTable('exam_results')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->dropUnique('unique_exam_student_attempt');
            });
        }

        if (Schema::hasTable('parent_student')) {
            Schema::table('parent_student', function (Blueprint $table) {
                $table->dropUnique('unique_parent_student_link');
            });
        }

        if (Schema::hasTable('servant_classes')) {
            Schema::table('servant_classes', function (Blueprint $table) {
                $table->dropUnique('unique_servant_class_assignment');
            });
        }
    }
};
