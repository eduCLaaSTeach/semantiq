<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P1-03 delivers people and groups AND NO ACCESS MODEL. This is the file that
 * says so against the code rather than in a comment.
 *
 * The risk is specific and it is not hypothetical: "add a user" and "put someone
 * in a group" are exactly the two operations a reader expects to grant
 * something. P1-05 owns the role model, and the way it arrives early is not a
 * deliberate decision - it is a column called owner_role added because it seemed
 * useful, or an authorisation path that reads group_memberships "just for now".
 */
final class PeopleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Negative case 3b, the PHYSICAL schema - Schema::getColumnListing, not the
     * migration source.
     *
     * Reading the migration would prove what the file says, not what the
     * database has: a later migration that adds a column would leave this
     * passing. The Product Owner asked for the physical check for that reason.
     *
     * Mutation: add `$table->boolean('is_admin')` in a second migration. CAUGHT
     * by the equality, and by the forbidden-word check below even if somebody
     * updated the expected list without reading why it was there.
     */
    public function test_the_group_tables_have_exactly_their_declared_columns(): void
    {
        $this->assertSame(
            ['id', 'organisation_id', 'name', 'code', 'description', 'status', 'created_at', 'updated_at'],
            Schema::getColumnListing('groups'),
            'The physical groups table is not the declared one. A group is a label and a membership '
            .'container; a column that could be read as a grant is P1-05 arriving early.'
        );

        $this->assertSame(
            ['id', 'group_id', 'user_id', 'joined_at', 'left_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('group_memberships'),
            'The physical group_memberships table is not the declared one.'
        );
    }

    /**
     * Negative case 3b, second half - and the half that survives somebody
     * updating the list above without thinking.
     *
     * Mutation: add `owner_role` to the table AND to the expected list. The
     * equality above then passes; this fails.
     */
    public function test_no_people_column_can_be_read_as_a_grant(): void
    {
        $forbidden = ['role', 'permission', 'scope', 'domain', 'sensitivity', 'entitlement', 'admin', 'grant'];

        foreach (['groups', 'group_memberships'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                foreach ($forbidden as $word) {
                    $this->assertStringNotContainsString(
                        $word,
                        strtolower($column),
                        "[{$table}.{$column}] names an access concept. P1-03 delivers no access model, "
                        .'and a column that anticipates one is P1-05 arriving through the back door.'
                    );
                }
            }
        }
    }

    /**
     * Negative case 3, source half. NOTHING OUTSIDE PEOPLE QUERIES GROUP
     * MEMBERSHIP.
     *
     * The failure this guards against is not in People - it is a middleware, a
     * policy or a navigation authorizer somewhere else deciding what to show
     * based on a group.
     *
     * ONE occurrence outside People is expected and is not a read:
     * PurgeDependencies carries a business phrase for every table that
     * references a record, keyed by table NAME. It answers "may this row be
     * deleted", never "what may this person see", and it is schema-driven - the
     * key is there because a foreign key exists, not because anybody decided
     * groups mean something. So it is permitted as an ARRAY KEY and nothing
     * else, which is asserted rather than waved through.
     *
     * The behavioural half is in PeopleAccessBoundaryTest: a group member gets
     * the same answer from every route as a non-member. That one cannot be
     * evaded by writing the query differently.
     *
     * Mutation: read GroupMembership from RequireSystemAdministrator.
     */
    public function test_nothing_outside_people_queries_group_membership(): void
    {
        $modelReferences = [];
        $tableReferences = [];

        foreach ($this->sourceFiles(base_path('app')) as $file) {
            if (str_contains($file, '/Modules/People/')) {
                continue;
            }

            $relative = str_replace(base_path().'/', '', $file);
            $contents = file_get_contents($file) ?: '';

            if (str_contains($contents, 'GroupMembership')) {
                $modelReferences[] = $relative;
            }

            foreach (explode("\n", $contents) as $number => $line) {
                if (! str_contains($line, 'group_memberships')) {
                    continue;
                }

                // An array key in a phrase table, and nothing else.
                if (preg_match("/^\s*'group_memberships' => \[\s*$/", $line) === 1) {
                    continue;
                }

                $tableReferences[] = $relative.':'.($number + 1);
            }
        }

        $this->assertSame(
            [],
            $modelReferences,
            'The GroupMembership model is used outside the People module. A group grants nothing in '
            .'P1-03, and the way that stops being true is one authorisation path reading it '
            .'"just for now".'
        );

        $this->assertSame(
            [],
            $tableReferences,
            'group_memberships is referenced outside People other than as a purge-phrase key. '
            .'Nothing outside People may read who is in a group.'
        );
    }

    /**
     * Negative case 4. NO P1-03 PATH ASSIGNS A ROLE.
     *
     * The temporary users.platform_role seam is P1-00's and P1-05 owns replacing
     * it. P1-03 must not expand it.
     *
     * "Never mentions the column" would be the easy assertion and it would be
     * the WRONG one - it would forbid the two things P1-03 is required to do
     * with it:
     *
     *   - READ it, which correction 2's lockout guard must, to know whether this
     *     is the last active System Administrator;
     *   - write it as literal NULL when provisioning, which is the explicit
     *     statement that a new person is granted nothing.
     *
     * So this asserts the thing that actually matters: no People code ever
     * assigns it a value that is not null, and no People code accepts it as
     * request input. A test that banned the word would have forced the lockout
     * guard to be written some indirect way, which is worse code and a worse
     * guard.
     *
     * P1-05 REMOVED THE COLUMN, so the string this scanned for is gone. The
     * property is unchanged and the needles are now the P1-05 ones: no People
     * code may create a role assignment, and none may accept a role from the
     * request.
     *
     * Mutation: create a RoleAssignment in provision(); or add 'role_code' to a
     * validate() array.
     */
    public function test_no_people_code_assigns_a_platform_role(): void
    {
        foreach ($this->sourceFiles(base_path('app/Modules/People')) as $file) {
            $relative = str_replace(base_path().'/', '', $file);

            // Comments say the word deliberately - they are the record of the
            // decision. Strip them, then look at the code that remains.
            $code = $this->withoutComments(file_get_contents($file) ?: '');

            foreach (explode("\n", $code) as $number => $line) {
                $where = "{$relative}:".($number + 1);

                /*
                 * Creating a role assignment. The People module reads the
                 * ADMINISTRATOR SET GUARD - it must, to refuse deactivating the
                 * last administrator - so the ban is on WRITING, not on naming
                 * the table. A test that banned the word would have forced the
                 * lockout guard to be written some indirect way, which is worse
                 * code and a worse guard.
                 */
                foreach (['RoleAssignment::query()->create(', 'RoleAssignment::create(', "'role_code' =>"] as $write) {
                    $this->assertStringNotContainsString(
                        $write,
                        $line,
                        "{$where} assigns a role. P1-03 grants nothing; P1-05 grants it deliberately, "
                        .'through Roles & Access, and nowhere else.'
                    );
                }

                if (! str_contains($line, 'role_code') && ! str_contains($line, 'platform_role')) {
                    continue;
                }

                // Request input, in any of the shapes that reads it.
                foreach (['validate', 'input(', '->request', 'query('] as $inputShape) {
                    $this->assertStringNotContainsString(
                        $inputShape,
                        $line,
                        "{$where} accepts a role from the request."
                    );
                }
            }
        }

        // And the same for the screens, where a control would be the visible
        // half of the same defect. Granting a role is a Roles & Access screen,
        // never a People one.
        foreach ($this->sourceFiles(base_path('resources/js/Pages/People')) as $file) {
            foreach (['platform_role', 'role_code'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    file_get_contents($file) ?: '',
                    str_replace(base_path().'/', '', $file).' offers a role control.'
                );
            }
        }
    }

    /**
     * Negative case 4, CARRIED FORWARD.
     *
     * This used to assert PlatformRole still had exactly one case, because a
     * second would have been P1-05 being designed by accident. P1-05 has now
     * been designed on purpose, so the property that replaces it is that the
     * People module reads the role model and never writes it.
     *
     * The behavioural half is in PeopleAccessBoundaryTest, against a real HTTP
     * request - both are needed, because a path could create an assignment
     * through a variable and a source scan would never see it.
     */
    public function test_the_people_module_never_writes_a_role(): void
    {
        $writes = [];

        foreach ($this->sourceFiles(base_path('app/Modules/People')) as $file) {
            $code = $this->withoutComments(file_get_contents($file) ?: '');

            foreach (['RoleAssignment::query()->create(', 'DomainEntitlement::', 'EntitlementScope::', 'EntitlementCeiling::'] as $write) {
                if (str_contains($code, $write)) {
                    $writes[] = str_replace(base_path().'/', '', $file).' -> '.$write;
                }
            }
        }

        $this->assertSame(
            [],
            $writes,
            'People code writes the access model. Provisioning somebody, deactivating them or '
            .'putting them in a group grants nothing - only Roles & Access grants.'
        );
    }

    /**
     * Negative case 40. Exactly ONE PurgeDependencies and ONE
     * RequireOrganisation.
     *
     * PurgeDependencies moved to App\Shared\Lifecycle for P1-03 (correction 3).
     * A move that leaves a copy behind is not a move - it is a fork, and the two
     * copies drift until one of them permits a purge the other refuses.
     *
     * RequireOrganisation deliberately did NOT move: it depends on
     * OrganisationService, so promoting it would make Platform depend backwards
     * on Organisation. This asserts the decision, in both directions.
     *
     * Mutation: leave the old App\Modules\Organisation\Support copy in place.
     */
    public function test_the_shared_lifecycle_classes_exist_exactly_once(): void
    {
        $expected = [
            'PurgeDependencies' => 'app/Shared/Lifecycle/PurgeDependencies.php',
            'RequireOrganisation' => 'app/Modules/Organisation/Http/Middleware/RequireOrganisation.php',
        ];

        foreach ($expected as $class => $path) {
            $found = [];

            foreach ($this->sourceFiles(base_path('app')) as $file) {
                if (basename($file) === $class.'.php') {
                    $found[] = str_replace(base_path().'/', '', $file);
                }
            }

            $this->assertSame(
                [$path],
                $found,
                "[{$class}] does not exist exactly once at its declared home. Two copies of a "
                .'lifecycle guard drift until they disagree about what may be deleted.'
            );
        }
    }

    /**
     * Negative case 6, source half. Authentication never MATCHES on email.
     *
     * D-33: the identity key is (provider, external_subject, tenant_id). Email
     * is mutable and reassignable, and P1-03 provisions people BEFORE they have
     * ever signed in - so the provisional email an administrator typed must
     * never be able to bind a sign-in to the wrong record.
     *
     * "Never mentions email" would be wrong here too: after a successful match
     * the resolver WRITES email, refreshing the provisional value with the real
     * one from Entra. That is the design. What must never happen is email
     * appearing in a LOOKUP clause, so that is what is asserted - and the
     * lookup's columns are pinned as an exact set, so an added
     * orWhere('email', ...) fails whether or not it is on its own line.
     *
     * The behavioural half is in UserProvisioningTest: a verified identity whose
     * email matches an existing record, but whose subject does not, is refused.
     *
     * Mutation: add ->orWhere('email', $identity->email) to the lookup.
     */
    public function test_identity_resolution_never_matches_on_email(): void
    {
        $resolver = base_path('app/Modules/Platform/Identity/IdentityResolver.php');

        $this->assertFileExists($resolver);

        $code = $this->withoutComments(file_get_contents($resolver) ?: '');

        // Every column named in a where clause of any kind.
        preg_match_all("/(?:or)?[wW]here\w*\(\s*'([^']+)'/", $code, $matches);

        $this->assertSame(
            ['provider', 'external_subject', 'tenant_id'],
            $matches[1],
            'IdentityResolver does not match on exactly (provider, external_subject, tenant_id). '
            .'An email fallback would let a reassigned mailbox inherit somebody else\'s SemantIQ '
            .'identity, and P1-03 makes that reachable by letting an administrator type an email '
            .'before the person has ever signed in.'
        );
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'jsx', 'js'], true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function withoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}
