<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Audit\Services\AuditWriter;
use App\Modules\Platform\Security\EvidenceRecorder;
use App\Modules\Platform\Security\UnrecordedEvidence;
use Tests\TestCase;

/**
 * THE DIRECTION OF THE BOUNDARY, and the wiring that makes the whole unit real.
 *
 * P1-08 consumes P1-00 to P1-07. Nothing earlier may name Modules\Audit -
 * a later unit reaching backwards is the reversal P1-07's Gate C caught once
 * already, and it arrives from correct-looking re-use rather than from
 * anything new.
 */
final class AuditBoundaryTest extends TestCase
{
    /**
     * THE GUARD THAT MAKES UnrecordedEvidence SAFE TO EXIST.
     *
     * A no-op recorder is a convenience that quietly becomes the running
     * configuration. This asserts the container resolves the REAL one, so the
     * convenient answer can never be the deployed one.
     *
     * Mutation: bind UnrecordedEvidence in AuditServiceProvider. Every evidence
     * test still passes except this one.
     */
    public function test_the_container_binds_the_real_recorder(): void
    {
        $recorder = app(EvidenceRecorder::class);

        $this->assertInstanceOf(AuditWriter::class, $recorder);
        $this->assertNotInstanceOf(UnrecordedEvidence::class, $recorder);
    }

    /**
     * THE WRITER IS A SINGLETON, and that is not tidiness.
     *
     * It remembers refusals written while a transaction was open so it can
     * write them again if that transaction unwinds. A per-resolution instance
     * would remember rows nobody ever recovers - evidence lost to a wiring
     * detail rather than to anything visible.
     *
     * Mutation: change singleton() to bind().
     */
    public function test_the_writer_is_a_singleton(): void
    {
        $this->assertSame(app(AuditWriter::class), app(AuditWriter::class));
        $this->assertSame(app(AuditWriter::class), app(EvidenceRecorder::class));
    }

    /**
     * NOTHING EARLIER NAMES Modules\Audit.
     *
     * Platform declares the INTERFACE and P1-08 implements it, so the
     * dependency points forwards. A single `use App\Modules\Audit\...` in an
     * earlier module reverses it.
     *
     * Mutation: import AuditWriter into SecurityEventLogger.
     */
    public function test_no_earlier_module_names_the_audit_module(): void
    {
        $offenders = [];

        foreach ([
            'app/Modules/Platform', 'app/Modules/Organisation', 'app/Modules/Identity',
            'app/Modules/People', 'app/Modules/Domains', 'app/Modules/Access',
            'app/Modules/Security', 'app/Modules/Reviews',
        ] as $module) {
            foreach ($this->phpFilesIn(base_path($module)) as $file) {
                if (str_contains((string) file_get_contents($file), 'App\\Modules\\Audit')) {
                    $offenders[] = str_replace(base_path().'/', '', $file);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'An earlier unit names the Audit module. Later units consume earlier ones, never the reverse.'
        );
    }

    /**
     * AND THE AUDIT MODULE ADDS NO EVENT OF ITS OWN.
     *
     * P1-08 stores the existing vocabulary; it does not extend it. A new key
     * or a new permitted context field is a change to the D-12 boundary and a
     * Product Owner decision, not a reporting unit's convenience.
     *
     * Mutation: call $this->events->record() from anywhere in Modules\Audit.
     */
    public function test_the_audit_module_records_no_events_of_its_own(): void
    {
        foreach ($this->phpFilesIn(base_path('app/Modules/Audit')) as $file) {
            $this->assertStringNotContainsString(
                'SecurityEventLogger::',
                (string) file_get_contents($file),
                'Audit emitted a security event. It stores the vocabulary; it does not add to it.'
            );
        }
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
