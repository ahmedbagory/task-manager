<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

trait RunsAfterCommit
{
    protected function runAfterCommit(callable $callback): void
    {
        if ($this->shouldRunAfterCommitImmediately()) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }

    protected function shouldRunAfterCommitImmediately(): bool
    {
        return app()->runningUnitTests() || app()->environment('testing');
    }
}
