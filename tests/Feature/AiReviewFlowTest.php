<?php

namespace Tests\Feature;

use App\AI\Contracts\LLMClientInterface;
use App\AI\DTO\CompiledReviewPayload;
use App\AI\DTO\CriterionResult;
use App\AI\DTO\ReviewResult;
use App\Models\AiReviewRun;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskReviewProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $this->app->instance(LLMClientInterface::class, new class implements LLMClientInterface {
            public function analyze(CompiledReviewPayload $payload): ReviewResult
            {
                return new ReviewResult(
                    summary: 'Модель оценила только LLM-критерии.',
                    recommendedScore: 0,
                    confidence: 0.88,
                    criteriaResults: [
                        new CriterionResult(
                            criterionId: 'criterion_1',
                            label: 'Корректность',
                            passed: true,
                            score: 80,
                            evidence: ['В коде есть функция и ожидаемый вывод'],
                            feedback: 'Решение соответствует базовым требованиям.',
                        ),
                    ],
                    unsupportedChecks: [],
                    raw: [
                        'provider' => $payload->provider,
                        'model' => $payload->model,
                    ],
                );
            }
        });
    }

    public function test_teacher_can_configure_profile_queue_review_and_apply_grade(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'task' => $task] = $this->createCourseContext();

        $profileResponse = $this->actingAs($teacher, 'api')->putJson("/api/task/{$task->id}/review-profile", [
            'enabled' => true,
            'rubric' => [
                [
                    'id' => 'criterion_compile',
                    'label' => 'Компиляция файла',
                    'description' => 'Проверь синтаксис PHP файла',
                    'checks' => [
                        'Скомпилировать файл',
                    ],
                    'weight' => 40,
                ],
                [
                    'id' => 'criterion_1',
                    'label' => 'Корректность',
                    'description' => 'Проверь основную логику решения',
                    'checks' => [
                        'Проанализируй код',
                    ],
                    'weight' => 60,
                ],
            ],
            'custom_prompt' => 'Ответ должен быть на русском языке.',
            'supported_formats' => ['php', 'zip', 'docx', 'xlsx', 'csv', 'tsv'],
            'ai_model_key' => 'deepseek_v4',
        ]);

        $profileResponse->assertOk()
            ->assertJsonPath('profile.enabled', true)
            ->assertJsonPath('profile.ai_model_key', 'deepseek_v4')
            ->assertJsonPath('profile.version', 1);

        $submissionResponse = $this->actingAs($student, 'api')->post('/api/task/submit', [
            'task_id' => $task->id,
            'file' => UploadedFile::fake()->createWithContent('solution.php', "<?php\n\nfunction solve(): void\n{\n    echo 'привет';\n}\n"),
        ]);

        $submissionResponse->assertCreated()
            ->assertJsonPath('submission.original_name', 'solution.php')
            ->assertJsonPath('submission.extension', 'php');

        $submissionId = (int) $submissionResponse->json('submission.id');

        $queueResponse = $this->actingAs($teacher, 'api')->postJson("/api/task/{$task->id}/submission/{$submissionId}/ai-review", [
            'force_recheck' => true,
        ]);

        $queueResponse->assertStatus(202)
            ->assertJsonPath('review.status', 'queued');

        $reviewId = (int) $queueResponse->json('review.id');

        $review = AiReviewRun::findOrFail($reviewId)->fresh();
        $this->assertSame('completed', $review->status->value);
        $this->assertSame('nekocode', $review->provider);
        $this->assertSame('gpt-5.5', $review->model);
        $this->assertSame(88, $review->recommended_score);
        $this->assertStringContainsString('Итоговая оценка: 88/100', (string) $review->summary);
        $this->assertSame('passed', $review->result_json['criteria_results'][0]['status']);
        $this->assertSame('server', $review->result_json['criteria_results'][0]['source']);
        $this->assertSame('criterion_compile', $review->result_json['criteria_results'][0]['criterion_id']);

        $listResponse = $this->actingAs($teacher, 'api')->getJson("/api/task/{$task->id}/ai-reviews");
        $listResponse->assertOk()
            ->assertJsonCount(1, 'reviews.data');

        $showResponse = $this->actingAs($teacher, 'api')->getJson("/api/ai-review/{$reviewId}");
        $showResponse->assertOk()
            ->assertJsonPath('review.id', $reviewId)
            ->assertJsonPath('review.recommended_score', 88);

        $applyResponse = $this->actingAs($teacher, 'api')->postJson("/api/ai-review/{$reviewId}/apply-grade");
        $applyResponse->assertOk()
            ->assertJsonPath('grade.type', 'AI')
            ->assertJsonPath('grade.grade', 88);

        $this->assertDatabaseHas('grades', [
            'user_id' => $student->id,
            'task_id' => $task->id,
            'type' => 'AI',
            'grade' => 88,
        ]);
    }

    public function test_student_cannot_queue_or_view_ai_review(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'task' => $task] = $this->createCourseContext();

        $this->actingAs($teacher, 'api')->putJson("/api/task/{$task->id}/review-profile", [
            'enabled' => true,
            'rubric' => [
                [
                    'id' => 'criterion_1',
                    'label' => 'Корректность',
                    'description' => 'Проверь основную логику решения',
                ],
            ],
            'supported_formats' => ['py'],
        ])->assertOk();

        $submissionResponse = $this->actingAs($student, 'api')->post('/api/task/submit', [
            'task_id' => $task->id,
            'file' => UploadedFile::fake()->createWithContent('solution.py', "print('student')\n"),
        ]);

        $submissionId = (int) $submissionResponse->json('submission.id');

        $this->actingAs($student, 'api')
            ->postJson("/api/task/{$task->id}/submission/{$submissionId}/ai-review")
            ->assertForbidden();

        $reviewId = (int) $this->actingAs($teacher, 'api')
            ->postJson("/api/task/{$task->id}/submission/{$submissionId}/ai-review", ['force_recheck' => true])
            ->assertStatus(202)
            ->json('review.id');

        $this->actingAs($student, 'api')
            ->getJson("/api/ai-review/{$reviewId}")
            ->assertForbidden();
    }

    public function test_teacher_can_queue_ai_review_with_default_profile(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'task' => $task] = $this->createCourseContext();

        $submissionResponse = $this->actingAs($student, 'api')->post('/api/task/submit', [
            'task_id' => $task->id,
            'file' => UploadedFile::fake()->createWithContent('solution.txt', 'Ответ студента.'),
        ]);

        $submissionId = (int) $submissionResponse->assertCreated()->json('submission.id');

        $queueResponse = $this->actingAs($teacher, 'api')->postJson("/api/task/{$task->id}/submission/{$submissionId}/ai-review", [
            'force_recheck' => true,
        ]);

        $queueResponse->assertStatus(202)
            ->assertJsonPath('review.status', 'queued');

        $profile = TaskReviewProfile::where('task_id', $task->id)->firstOrFail();

        $this->assertTrue($profile->enabled);
        $this->assertSame('minimax', $profile->ai_model_key);
        $this->assertCount(3, $profile->rubric_json);
        $this->assertSame(100, array_sum(array_column($profile->rubric_json, 'weight')));

        $review = AiReviewRun::findOrFail((int) $queueResponse->json('review.id'))->fresh();
        $this->assertSame('completed', $review->status->value);
        $this->assertSame('openrouter', $review->provider);
        $this->assertSame('minimax/minimax-m2.5:free', $review->model);
    }

    /**
     * @return array{teacher: User, student: User, course: Course, discipline: Discipline, task: Task}
     */
    private function createCourseContext(): array
    {
        $userRole = Role::where('name', 'user')->firstOrFail();

        $teacher = User::factory()->create([
            'role_id' => $userRole->id,
        ]);

        $student = User::factory()->create([
            'role_id' => $userRole->id,
        ]);

        $course = Course::create([
            'creator_id' => $teacher->id,
            'name' => 'Алгоритмы',
            'invite_code' => 'IC-'.Str::upper(Str::random(8)),
            'invite_code_teacher' => 'TC-'.Str::upper(Str::random(8)),
            'description' => 'Тестовый курс',
            'status' => 'active',
            'is_closed' => false,
            'slug' => 'algoritmy-'.Str::lower(Str::random(6)),
        ]);

        $teacher->courses()->attach($course->id, ['role' => 'teacher']);
        $student->courses()->attach($course->id, ['role' => 'student']);

        $discipline = Discipline::create([
            'course_id' => $course->id,
            'name' => 'Python',
            'hours' => 36,
            'slug' => 'python-'.Str::lower(Str::random(6)),
            'description' => 'Язык Python',
            'created_by' => $teacher->id,
        ]);

        $task = Task::create([
            'user_id' => $teacher->id,
            'course_id' => $course->id,
            'discipline_id' => $discipline->id,
            'name' => 'Решить задачу',
            'description' => 'Напишите программу.',
            'scores' => 100,
            'deadline' => now()->addWeek(),
        ]);

        return compact('teacher', 'student', 'course', 'discipline', 'task');
    }
}
