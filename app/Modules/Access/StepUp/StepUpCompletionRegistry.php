<?php

declare(strict_types=1);

namespace App\Modules\Access\StepUp;

/**
 * The step-up actions P1-05 does not perform itself.
 *
 * A unit registers its completion in its own service provider. Nothing here
 * imports it, names it, or knows it exists until it registers - so adding a
 * unit adds no line to any accepted P1-05 file.
 *
 * AN UNCLAIMED ACTION RESOLVES TO NULL, and the controller refuses. There is no
 * fallback: an action nobody claims is a malformed confirmation, and performing
 * "the closest thing" is how a step-up authorises something nobody confirmed.
 */
final class StepUpCompletionRegistry
{
    /** @var list<StepUpCompletion> */
    private array $completions = [];

    public function register(StepUpCompletion $completion): void
    {
        $this->completions[] = $completion;
    }

    public function for(StepUpAction $action): ?StepUpCompletion
    {
        foreach ($this->completions as $completion) {
            if ($completion->handles($action)) {
                return $completion;
            }
        }

        return null;
    }
}
