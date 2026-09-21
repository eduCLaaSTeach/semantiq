<?php

declare(strict_types=1);

namespace App\Modules\Administration\Support;

/**
 * THREE STATES THAT MUST NEVER COLLAPSE - D-139.
 *
 *   Valued       asked, answered. The numbers are real and 0 is one of them.
 *   Withheld     this viewer may not be told. NO NUMBER AT ALL.
 *   Unavailable  the source could not answer THIS render. NO NUMBER AT ALL.
 *
 * WHY THREE AND NOT TWO. "Withheld" and "Not available" both render without a
 * number, so it is tempting to make them one state. They are different claims:
 * the first is a decision about the viewer and is normal; the second is a
 * failure and is not. A screen that reported a broken posture evaluator as
 * "withheld" would tell a System Administrator that security information is
 * not theirs to see.
 *
 * WHY Unavailable IS NOT A COUNT OF ZERO. §8's rule, and the direction of it
 * matters more than the rule: a dashboard rendering "0 open exceptions"
 * because the evaluator threw is worse than one rendering nothing. It is
 * confidently wrong about security, and wrong in the reassuring direction.
 *
 * THIS IS WHY THE SEAM DTOs CARRY ONLY `valued`. A source that throws never
 * returns a DTO at all - PeopleSummary and DomainSummary describe what P1-03
 * and P1-04 know, and "we could not ask them" is not something they know. The
 * distinction is made here, at the composition boundary, by the code that
 * caught the Throwable.
 */
enum TileState: string
{
    case Valued = 'valued';
    case Withheld = 'withheld';
    case Unavailable = 'unavailable';
}
