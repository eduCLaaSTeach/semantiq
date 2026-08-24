<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Modules\Governance\Enums\ProfileStatus;
use App\Modules\Governance\Models\Concerns\IsVersionedProfile;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One version of the data protection profile. Feature ADM-014.
 *
 * The organisation's stated privacy position: which regime applies, whether a
 * privacy officer has been designated, and how long it has to notify the
 * regulator about a breach.
 *
 * TWO OF THESE FIELDS ARE COMPLIANCE-OWNED AND START NULL. `regime_basis` and
 * `breach_notification_basis` hold legal reasoning, and engineering does not
 * write legal reasoning. A blank one reads as Not Configured on the screen, not
 * as a compliance claim nobody made.
 *
 * `breach_notification_due_days` is different: 3 calendar days was accepted for
 * implementation (D7), so the catalogue supplies it. The value on an APPROVED
 * version is what a breach record freezes in R1.4c, which is why editing an
 * approved version is refused - moving it afterwards would move a date somebody
 * is being held to.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $version
 * @property ProfileStatus $status
 * @property string|null $applicable_regime
 * @property string|null $regime_basis
 * @property bool $privacy_officer_designated
 * @property int|null $breach_notification_due_days
 * @property string|null $breach_notification_basis
 * @property string|null $notes
 * @property Carbon|null $approved_at
 * @property int|null $approved_by_user_id
 * @property Carbon|null $superseded_at
 * @property int|null $superseded_by_id
 */
class DataProtectionProfile extends Model
{
    use BelongsToOrganisation;
    use IsVersionedProfile;

    /**
     * `status`, `version` and every approval column are absent on purpose.
     * Moving a profile through its lifecycle is the service's job, and a
     * lifecycle a form can post is a lifecycle a form can skip.
     */
    protected $fillable = [
        'applicable_regime',
        'regime_basis',
        'privacy_officer_designated',
        'breach_notification_due_days',
        'breach_notification_basis',
        'notes',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'version' => 'integer',
            'privacy_officer_designated' => 'boolean',
            'breach_notification_due_days' => 'integer',
            'approved_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * Whether every field a complete profile needs has been answered.
     *
     * Used by the screen to say what is still missing, and by the R1.4c
     * Governance Overview to decide whether this area is healthy. It returns
     * the GAPS rather than a boolean, so the screen can name them instead of
     * showing an unexplained warning.
     *
     * The two compliance-owned fields count as gaps. That is the point: an
     * approved profile with no stated legal basis is incomplete, and a screen
     * that called it complete would be claiming compliance nobody signed off.
     *
     * @return list<string>
     */
    public function gaps(): array
    {
        $gaps = [];

        if (($this->applicable_regime ?? '') === '' || $this->applicable_regime === 'Not determined') {
            $gaps[] = 'The applicable privacy regime has not been determined.';
        }

        if (($this->regime_basis ?? '') === '') {
            $gaps[] = 'No basis has been recorded for the applicable regime. This is compliance-owned '
                .'text and engineering cannot supply it.';
        }

        if (! $this->privacy_officer_designated) {
            $gaps[] = 'No privacy officer has been designated.';
        }

        if ($this->breach_notification_due_days === null) {
            $gaps[] = 'No breach notification deadline has been set.';
        }

        if (($this->breach_notification_basis ?? '') === '') {
            $gaps[] = 'No basis or reference has been recorded for the breach notification deadline. '
                .'This is compliance-owned text and engineering cannot supply it.';
        }

        return $gaps;
    }
}
