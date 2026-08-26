<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * The lifecycle of a privacy request. Feature PDPA-01.
 *
 * The order of the cases is the order of the workflow, and `allowedNext()` is
 * the single definition of which moves are legal. The service consults it; the
 * screen only renders what the service permits. A transition table that lives
 * in a controller is a transition table that will eventually disagree with
 * itself.
 *
 * THERE ARE NO BACKWARD TRANSITIONS out of a terminal state. A closed request
 * is reopened by raising a new one, so the record of what was disclosed on a
 * given date cannot be quietly edited afterwards.
 *
 * `InReview` may return to `Assembling` for a re-run. That is forward in
 * intent: it appends new record rows and never edits the previous ones.
 */
enum PrivacyRequestStatus: string
{
    case Received = 'received';
    case IdentityVerification = 'identity_verification';
    case Assembling = 'assembling';
    case InReview = 'in_review';
    case AwaitingDecision = 'awaiting_decision';
    case Responded = 'responded';
    case Refused = 'refused';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::IdentityVerification => 'Verifying identity',
            self::Assembling => 'Assembling',
            self::InReview => 'In review',
            self::AwaitingDecision => 'Awaiting decision',
            self::Responded => 'Responded',
            self::Refused => 'Refused',
            self::Closed => 'Closed',
        };
    }

    /**
     * What a reader should understand this state to mean, in plain terms.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::Received => 'Recorded, and waiting for the requester to be identified.',
            self::IdentityVerification => 'Nothing is collected until somebody confirms who this person is.',
            self::Assembling => 'Collecting what is held about this person.',
            self::InReview => 'Assembled and waiting for a reviewer to decide what may be disclosed.',
            self::AwaitingDecision => 'A correction has been proposed and needs a decision.',
            self::Responded => 'A response was released. Record how it was delivered, then close.',
            self::Refused => 'Refused, with the reason recorded. Refusal is a lawful outcome.',
            self::Closed => 'Finished. Reopen by raising a new request.',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Received, self::IdentityVerification => 'badge-warning',
            self::Assembling, self::InReview, self::AwaitingDecision => 'badge-info',
            self::Responded, self::Closed => 'badge-success',
            self::Refused => 'badge-danger',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Whether collection may run. The single source of the identity gate at the
     * state level; the service also checks that identity was actually verified,
     * because a state alone is not evidence that a person did anything.
     */
    public function permitsCollection(): bool
    {
        return $this === self::Assembling || $this === self::InReview;
    }

    /**
     * The legal moves out of this state.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Received => [self::IdentityVerification],
            self::IdentityVerification => [self::Assembling, self::Refused],
            self::Assembling => [self::InReview],
            self::InReview => [self::AwaitingDecision, self::Responded, self::Refused, self::Assembling],
            self::AwaitingDecision => [self::Responded, self::Refused],
            self::Responded, self::Refused => [self::Closed],
            self::Closed => [],
        };
    }

    public function permits(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
