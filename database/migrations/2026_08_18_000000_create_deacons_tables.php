<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('patron_saint')->nullable();
            $table->string('time_from')->nullable();
            $table->string('time_to')->nullable();
            $table->string('location')->nullable();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->string('name_ar');
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->unique();
                $table->string('role')->default('student');
                $table->string('status')->default('active');
                $table->string('church_name')->default('كنيسة العذراء مريم');
                $table->string('deacon_rank')->nullable();
                $table->string('gender')->nullable();
                $table->string('address')->nullable();
                $table->string('father_name')->nullable();
                $table->string('father_phone')->nullable();
                $table->string('mother_name')->nullable();
                $table->string('mother_phone')->nullable();
                $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
                $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->string('profile_pic')->nullable();
                $table->string('qr_code_token')->nullable()->unique();
                $table->integer('points_balance')->default(0);
            }
        });

        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship')->default('والد / والدة');
            $table->timestamps();
        });

        Schema::create('servant_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('hymns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('coptic_text')->nullable();
            $table->text('coptic_arabic_text')->nullable();
            $table->text('arabic_translation')->nullable();
            $table->text('description')->nullable();
            $table->string('audio_file')->nullable();
            $table->string('pdf_file')->nullable();
            $table->string('video_link')->nullable();
            $table->string('season')->default('سنوي');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->string('pdf_file')->nullable();
            $table->string('audio_file')->nullable();
            $table->string('video_file')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('attendance_date');
            $table->string('status')->default('present');
            $table->timestamp('scanned_at')->useCurrent();
            $table->string('notes')->nullable();
        });

        Schema::create('points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('points');
            $table->string('type')->default('positive');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('behavior_score')->default(5);
            $table->integer('hymn_memorization')->default(5);
            $table->integer('church_attending')->default(5);
            $table->text('notes')->nullable();
            $table->date('evaluation_date');
            $table->timestamps();
        });

        Schema::create('liturgy_rosters', function (Blueprint $table) {
            $table->id();
            $table->date('liturgy_date');
            $table->string('liturgy_name');
            $table->string('altar_name')->default('المذبح الرئيسي');
            $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->json('student_ids')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->integer('duration_minutes')->default(30);
            $table->float('total_score')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_type')->default('mcq');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->float('score')->default(10);
            $table->timestamps();
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->float('score')->default(0);
            $table->string('status')->default('completed');
            $table->text('servant_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('points_required');
            $table->integer('quantity')->default(10);
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('visitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servant_id')->constrained('users')->cascadeOnDelete();
            $table->string('visitation_type')->default('home');
            $table->date('visit_date');
            $table->text('notes')->nullable();
            $table->date('next_visit_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitations');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('liturgy_rosters');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('points');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('hymns');
        Schema::dropIfExists('servant_classes');
        Schema::dropIfExists('parent_student');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('stages');
    }
};
