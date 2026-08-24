-- ============================================================================
-- CHECK-R1.4b-RECOVERY.sql
--
-- READ-ONLY PRE-CHECK. Run this before approving any recovery action.
--
-- This file contains SELECT statements only. There is no DROP, DELETE, UPDATE,
-- INSERT or ALTER anywhere in it, no migration command and no rollback. It
-- cannot change the database.
--
-- No database name, host, user, password or connection string appears in this
-- file. It resolves the current schema with DATABASE() so it is safe to commit
-- and safe to paste.
--
-- WHY IT EXISTS. The R1.4b migration run stopped part way through. Migration 1
-- succeeded and was recorded. Migration 2 created `retention_policies` and then
-- failed adding a unique key whose generated name was 67 characters, which
-- MySQL rejects with error 1059. Migration 3 never ran. MySQL does not roll DDL
-- back, so the table exists while the migration is NOT recorded as having run.
--
-- Every row returns exactly one of:
--
--   PASS           correct; nothing to do for that row
--   ACTION NEEDED  expected in this state, and the recovery addresses it
--   STOP           does not match the diagnosis. Do not run anything.
--                  Send the output back first.
--
-- Run BOTH statements below. The second is separate on purpose: it reads
-- `retention_policies` directly, so it can only run if that table exists. If it
-- errors with "table doesn't exist", that is not a failure of the script - it
-- is the answer to check 5, and it means there is nothing to drop. Report it.
-- ============================================================================


-- ---------------------------------------------------------------------------
-- STATEMENT 1 of 2. Checks 1, 2, 3, 4, 5, 7, 8 and 9.
-- Reads only `migrations` and `information_schema`.
-- ---------------------------------------------------------------------------

SELECT `#`, `Check`, `Verdict`, `Detail` FROM (

    -- 1. Migration 1 must be recorded. It reported DONE.
    SELECT 1 AS `#`,
        'create_sovereignty_exceptions_table recorded' AS `Check`,
        CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'STOP' END AS `Verdict`,
        CASE WHEN COUNT(*) = 1
             THEN 'Recorded, as expected. It will be skipped on the re-run.'
             ELSE CONCAT('Found ', COUNT(*), ' rows, expected 1. Does not match the diagnosis.')
        END AS `Detail`
    FROM migrations
    WHERE migration = '2026_08_28_090000_create_sovereignty_exceptions_table'

    UNION ALL

    -- 2. Migration 2 failed, so it must NOT be recorded. This is what lets the
    --    corrected migration run again.
    SELECT 2,
        'create_retention_policies_table NOT recorded',
        CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'STOP' END,
        CASE WHEN COUNT(*) = 0
             THEN 'Correctly unrecorded, so the fixed migration will re-run.'
             ELSE 'Recorded despite failing. Do not drop anything. Send this back.'
        END
    FROM migrations
    WHERE migration = '2026_08_28_090100_create_retention_policies_table'

    UNION ALL

    -- 3. Migration 3 never ran.
    SELECT 3,
        'add_module_and_outcome_indexes NOT recorded',
        CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'STOP' END,
        CASE WHEN COUNT(*) = 0
             THEN 'Never ran, as expected. The run stopped at migration 2.'
             ELSE 'Recorded, but migration 2 failed before it. Send this back.'
        END
    FROM migrations
    WHERE migration = '2026_08_28_090200_add_module_and_outcome_indexes_to_audit_events_table'

    UNION ALL

    -- 4. Migration 1 created its table completely.
    SELECT 4,
        'sovereignty_exceptions table exists',
        CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'STOP' END,
        CASE WHEN COUNT(*) = 1
             THEN 'Present. Migration 1 completed and is not part of the recovery.'
             ELSE 'Absent, but migration 1 reported DONE. Send this back.'
        END
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sovereignty_exceptions'

    UNION ALL

    -- 5. The half-created table. Expected to exist; this is what the recovery
    --    removes so the corrected migration can create it cleanly.
    SELECT 5,
        'retention_policies table exists',
        CASE WHEN COUNT(*) = 1 THEN 'ACTION NEEDED' ELSE 'PASS' END,
        CASE WHEN COUNT(*) = 1
             THEN 'Present but incomplete. This is the table the recovery drops.'
             ELSE 'Absent. Nothing to drop; the corrected migration can just run.'
        END
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retention_policies'

    UNION ALL

    -- 7. The 67 character unique key MySQL refused. It must be absent: the
    --    server rejected the name outright, so it was never created.
    SELECT 7,
        'the rejected 67-char unique index is absent',
        CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'STOP' END,
        CASE WHEN COUNT(*) = 0
             THEN 'Absent, as expected. This is the key whose name broke the run.'
             ELSE 'Present. MySQL should have refused it. Send this back.'
        END
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'retention_policies'
      AND INDEX_NAME = 'retention_policies_organisation_id_personal_data_category_id_unique'

    UNION ALL

    -- 8. The audit trail must still be there. Note this number down: the index
    --    migration must not change it, and the post-check compares against it.
    SELECT 8,
        'audit_events row count',
        CASE WHEN COUNT(*) > 0 THEN 'PASS' ELSE 'STOP' END,
        CASE WHEN COUNT(*) > 0
             THEN CONCAT(COUNT(*), ' rows. WRITE THIS NUMBER DOWN - the post-check needs it.')
             ELSE 'Zero rows. The trail should never be empty. Send this back.'
        END
    FROM audit_events

    UNION ALL

    -- 9. Both append-only triggers must have survived the failed run. These are
    --    what make the trail evidence rather than a log.
    SELECT 9,
        'both audit append-only triggers present',
        CASE WHEN COUNT(*) = 2 THEN 'PASS' ELSE 'STOP' END,
        CASE WHEN COUNT(*) = 2
             THEN 'Both present. The failed run did not touch them.'
             ELSE CONCAT(COUNT(*), ' of 2 triggers. Anything below 2 is a stop.')
        END
    FROM information_schema.TRIGGERS
    WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'audit_events'

) AS pre_check
ORDER BY `#`;


-- ---------------------------------------------------------------------------
-- STATEMENT 2 of 2. Check 6, run separately.
--
-- THIS IS THE SAFETY GATE FOR THE DROP. Nothing may be dropped unless this
-- returns PASS. The table was created minutes before the failure and no
-- application code can write to it while the screens report it missing, so it
-- must be empty. Any other number means the diagnosis is wrong.
--
-- If this errors with "table doesn't exist", that answers check 5 instead:
-- there is nothing to drop. Report the error rather than treating it as a fault.
-- ---------------------------------------------------------------------------

SELECT 6 AS `#`,
    'retention_policies is empty' AS `Check`,
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'STOP' END AS `Verdict`,
    CASE WHEN COUNT(*) = 0
         THEN 'Empty, as expected. The drop is safe to consider.'
         ELSE CONCAT(COUNT(*), ' row(s). DO NOT DROP. Send this back.')
    END AS `Detail`
FROM retention_policies;
