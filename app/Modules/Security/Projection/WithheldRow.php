<?php

declare(strict_types=1);

namespace App\Modules\Security\Projection;

/**
 * A NAMED row whose VALUE this viewer may not see.
 *
 * THERE IS NO $state, NO $finding AND NO $ownerHref. Not null - ABSENT.
 *
 * That is the whole design of this class. A nullable ?PostureState would have
 * been a FILTER, and filters are what later edits remove: somebody adds a
 * `$row->state?->value` to a template, or sorts by severity, or derives a CSS
 * class, and the value is out. With no field at all there is nothing to print,
 * nothing to order by, nothing to derive a colour from, and nothing for a
 * future edit to forget to strip. It is the ALLOWED_KEYS pattern applied to a
 * screen: the leak is unrepresentable rather than discouraged.
 *
 * withheld() is ONE CONSTANT SENTENCE, identical for every withheld row and
 * every underlying value. Never "no action needed", which would be a value.
 *
 * D-76: named but not valued. A hidden row would make the count wrong; a valued
 * row would widen the role.
 */
final class WithheldRow
{
    public function __construct(
        public readonly string $control,
        public readonly string $label,
    ) {}

    public const SENTENCE = 'Managed by the platform administrator.';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /*
         * The SAME KEYS as a valued row, so the payload SHAPE is invariant -
         * a response whose field set changed with the viewer's authority would
         * itself be the disclosure. The value keys are constants.
         */
        return [
            'control' => $this->control,
            'label' => $this->label,
            'state' => null,
            'stateLabel' => null,
            'kind' => null,
            'finding' => self::SENTENCE,
            'ownerHref' => null,
            'ownerLabel' => null,
            'qualifier' => null,
            'withheld' => true,
        ];
    }
}
