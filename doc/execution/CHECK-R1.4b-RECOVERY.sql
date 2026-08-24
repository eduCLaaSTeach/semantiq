-- ============================================================================
-- CHECK-R1.4b-RECOVERY.sql
--
-- Run this FIRST, before doing anything else, on the live database.
--
-- WHY. The R1.4b migration run stopped halfway. Migration 1 succeeded and was
-- recorded. Migration 2 created `retention_policies` and then failed adding a
-- unique key whose auto-generated name was 67 characters, which MySQL rejects
-- (error 1059). Migration 3 never ran. MySQL does not roll DDL back, so the
-- table exists while the migration is NOT recorded as having run, and a plain
-- re-run would fail with "table already exists".
--
-- This script does not change anything. It reports one verdict per row so
-- there are no numbers to compare by eye:
--
--   PASS          nothing to do for that row
--   ACTION NEEDED expected, and the recovery below fixes it
--   STOP          do not proceed; send the output back before running anything
--
-- No database name, host, user or credential appears anywhere in this file.
-- ============================================================================

SELECT '--- R1.4b RECOVERY PRE-CHECK ---' AS report;

-- 1. Migration 1 should have landed completely.
SELECT
    '1. sovereignty_exceptions table' AS checkpoint,
    CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'STOP' END AS verdict,
    CASE WHEN COUNT(*) = 1
         THEN 'Created, as expected.'
         ELSE 'Expected the table to exist. Migration 1 reported DONE.' END AS detail
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sovereignty_exceptions';

SELECT
    '2. sovereignty_exceptions recorded' AS checkpoint,
    CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'STOP' END AS verdict,
    CASE WHEN COUNT(*) = 1
         THEN 'Recorded in migrations. It will be skipped on the re-run.'
         ELSE 'Not recorded. Send this output back before running anything.' END AS detail
FROM migrations
WHERE migration = '2026_08_28_090000_create_sovereignty_exceptions_table';

-- 2. Migration 2 is the half-applied one.
SELECT
    '3. retention_policies table' AS checkpoint,
    CASE WHEN COUNT(*) = 1 THEN 'ACTION NEEDED' ELSE 'PASS' END AS verdict,
    CASE WHEN COUNT(*) = 1
         THEN 'Exists but is incomplete. Step R2 of the recovery drops it.'
         ELSE 'Absent. The CREATE did not survive; just re-run the migration.' END AS detail
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retention_policies';

SELECT
    '4. retention_policies NOT recorded' AS checkpoint,
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'STOP' END AS verdict,
    CASE WHEN COUNT(*) = 0
         THEN 'Correctly unrecorded, so the fixed migration will re-run.'
         ELSE 'Recorded despite failing. Send this output back; do not drop.' END AS detail
FROM migrations
WHERE migration = '2026_08_28_090100_create_retention_policies_table';

-- 3. THE SAFETY GATE FOR THE DROP. The table is brand new and no application
--    code has ever been able to write to it, so it must be empty. If it is
--    not, something is wrong with this diagnosis and nothing may be dropped.
SELECT
    '5. retention_policies is empty' AS checkpoint,
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'STOP' END AS verdict,
    CONCAT(COUNT(*), ' row(s). The drop in step R2 is only safe at 0.') AS detail
FROM retention_policies;

-- 4. Migration 3 never ran, so neither index should be present yet.
SELECT
    '6. new audit indexes absent' AS checkpoint,
    CASE WHEN COUNT(DISTINCT INDEX_NAME) = 0 THEN 'ACTION NEEDED' ELSE 'PASS' END AS verdict,
    CASE WHEN COUNT(DISTINCT INDEX_NAME) = 0
         THEN 'Not yet added, as expected. Step R3 adds them.'
         ELSE 'Already present. Migration 3 partially ran; send this back.' END AS detail
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_events'
  AND INDEX_NAME IN ('audit_events_org_module_occurred_index',
                     'audit_events_org_outcome_occurred_index');

-- 5. THE FAILED RUN MUST NOT HAVE TOUCHED THE AUDIT TRAIL. Both append-only
--    triggers are what make the trail evidence rather than a log.
SELECT
    '7. both audit triggers intact' AS checkpoint,
    CASE WHEN COUNT(*) = 2 THEN 'PASS' ELSE 'STOP' END AS verdict,
    CONCAT(COUNT(*), ' of 2 triggers on audit_events. Anything below 2 is a stop.') AS detail
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'audit_events';

-- 6. Baseline to compare against after the recovery. The index migration must
--    not change this number.
SELECT
    '8. audit_events row count' AS checkpoint,
    'BASELINE' AS verdict,
    CONCAT(COUNT(*), ' rows. Note this down; it must be identical afterwards.') AS detail
FROM audit_events;

SELECT '--- END PRE-CHECK ---' AS report;
