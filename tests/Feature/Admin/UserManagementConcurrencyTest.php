<?php

use App\Actions\Admin\DeleteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Throwable;

uses(DatabaseMigrations::class);

it('keeps one administrator during concurrent deletes on production databases', function (): void {
    if (! filter_var(env('RUN_CONCURRENCY_TESTS', false), FILTER_VALIDATE_BOOL)
        || ! extension_loaded('pcntl')
        || ! in_array(config('database.default'), ['mysql', 'pgsql'], true)) {
        $this->markTestSkipped('Enable RUN_CONCURRENCY_TESTS on MySQL or PostgreSQL to run the row-lock integration test.');
    }

    $admins = User::factory()->admin()->count(2)->create();
    $resultFile = tempnam(sys_get_temp_dir(), 'user-concurrency-');
    $children = [];

    foreach ($admins as $target) {
        $actor = $admins->first(fn (User $admin): bool => ! $admin->is($target));
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork concurrency test process.');
        }

        if ($pid === 0) {
            try {
                DB::disconnect();
                app(DeleteUser::class)->execute(
                    User::query()->findOrFail($target->getKey()),
                    User::query()->findOrFail($actor->getKey()),
                );
                file_put_contents($resultFile, "success\n", FILE_APPEND | LOCK_EX);
            } catch (Throwable $exception) {
                file_put_contents($resultFile, $exception::class."\n", FILE_APPEND | LOCK_EX);
            }

            exit(0);
        }

        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    expect(User::query()->administrators()->count())->toBeGreaterThanOrEqual(1);

    if ($resultFile !== false) {
        unlink($resultFile);
    }
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
