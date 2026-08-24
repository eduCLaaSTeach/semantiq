-- ============================================================================
-- CHECK-R1.4b.sql
--
-- Run this AFTER the recovery has completed, to prove R1.4b landed correctly.
--
-- One verdict per row. Nothing to compare by eye:
--   PASS          correct
--   ACTION NEEDED expected at this stage, and the note says what to do
--   FAIL          wrong; send the output back
--
-- Two rows need a number you noted earlier. Row 9 asks for the audit row count
-- from CHECK-R1.4b-RECOVERY.sql. Substitute it where the file says
-- <AUDIT_ROWS_BEFORE>. If you did not note it, that row alone is unprovable;
-- every other row still stands on its own.
--
-- No database name, host, user or credential appears anywhere in this file.
-- ============================================================================

SELECT '--- R1.4b VERIFICATION ---' AS report;

-- ---------------------------------------------------------------- migrations
SELECT
    '1. all three migrations recorded' AS checkpoint,
    CASE WHEN COUNT(*) = 3 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(*), ' of 3 recorded in the migrations table.') AS detail
FROM migrations
WHERE migration IN (
    '2026_08_28_090000_create_sovereignty_exceptions_table',
    '2026_08_28_090100_create_retention_policies_table',
    '2026_08_28_090200_add_module_and_outcome_indexes_to_audit_events_table'
);

SELECT
    '2. no unexpected migration' AS checkpoint,
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(*), ' migration(s) dated after R1.4b. Expected none.') AS detail
FROM migrations
WHERE migration > '2026_08_28_090200_add_module_and_outcome_indexes_to_audit_events_table';

-- ------------------------------------------------------------------- triggers
SELECT
    '3. both audit triggers still exist' AS checkpoint,
    CASE WHEN COUNT(*) = 2 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(*), ' of 2. These are what make the trail evidence.') AS detail
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'audit_events';

SELECT
    '4. one trigger blocks UPDATE' AS checkpoint,
    CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(*), ' BEFORE UPDATE trigger.') AS detail
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE = 'audit_events'
  AND EVENT_MANIPULATION = 'UPDATE'
  AND ACTION_TIMING = 'BEFORE';

SELECT
    '5. one trigger blocks DELETE' AS checkpoint,
    CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(*), ' BEFORE DELETE trigger.') AS detail
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE = 'audit_events'
  AND EVENT_MANIPULATION = 'DELETE'
  AND ACTION_TIMING = 'BEFORE';

-- -------------------------------------------------------------------- indexes
SELECT
    '6. two new audit indexes exist' AS checkpoint,
    CASE WHEN COUNT(DISTINCT INDEX_NAME) = 2 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(DISTINCT INDEX_NAME), ' of 2 present.') AS detail
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_events'
  AND INDEX_NAME IN ('audit_events_org_module_occurred_index',
                     'audit_events_org_outcome_occurred_index');

SELECT
    '7. the four earlier indexes survived' AS checkpoint,
    CASE WHEN COUNT(DISTINCT INDEX_NAME) = 4 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(DISTINCT INDEX_NAME), ' of 4. The migration adds only; it removes nothing.') AS detail
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'audit_events'
  AND INDEX_NAME IN ('audit_events_organisation_id_occurred_at_index',
                     'audit_events_actor_user_id_occurred_at_index',
                     'audit_events_action_occurred_at_index',
                     'audit_events_resource_type_resource_id_index');

SELECT
    '8. every identifier is legal' AS checkpoint,
    CASE WHEN COUNT(DISTINCT INDEX_NAME) = 0 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(DISTINCT INDEX_NAME), ' index name(s) over 64 characters. This is what broke the first run.') AS detail
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND CHAR_LENGTH(INDEX_NAME) > 64;

-- COUNTING INDEXES: information_schema.STATISTICS HOLDS ONE ROW PER COLUMN.
-- A two-column composite key returns TWO rows for ONE index. Every index check
-- in this file therefore counts DISTINCT INDEX_NAME, never COUNT(*). Getting
-- this wrong reports a correct schema as a failure, which is worse than no
-- check at all: it sends somebody looking for a fault that is not there.
SELECT
    '8b. retention unique key created' AS checkpoint,
    CASE WHEN COUNT(DISTINCT INDEX_NAME) = 1 THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(DISTINCT INDEX_NAME), ' index spanning ', COUNT(*),
           ' column(s). Expected 1 index over 2 columns.') AS detail
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'retention_policies'
  AND INDEX_NAME = 'retention_policies_org_category_unique';

-- ----------------------------------------------------------------- audit rows
-- Substitute the number from row 8 of CHECK-R1.4b-RECOVERY.sql.
SELECT
    '9. audit row count unchanged' AS checkpoint,
    CASE WHEN COUNT(*) = <AUDIT_ROWS_BEFORE> THEN 'PASS' ELSE 'FAIL' END AS verdict,
    CONCAT(COUNT(*), ' rows now, <AUDIT_ROWS_BEFORE> before. The index migration must not change this.') AS detail
FROM audit_events;

-- ----------------------------------------------------------------- retention
SELECT
    '10. retention has 7 categories' AS checkpoint,
    CASE WHEN c.total = 7 THEN 'PASS'
         WHEN c.total = 0 THEN 'ACTION NEEDED'
         ELSE 'FAIL' END AS verdict,
    CASE WHEN c.total = 7 THEN 'Seven active personal data categories, as expected.'
         WHEN c.total = 0 THEN 'None yet. Open the Retention screen once; it seeds on first visit.'
         ELSE CONCAT(c.total, ' categories. Expected 7.') END AS detail
FROM (SELECT COUNT(*) AS total FROM personal_data_categories) c;

SELECT
    '11. retention values Not Configured' AS checkpoint,
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'ACTION NEEDED' END AS verdict,
    CONCAT(COUNT(*), ' policy row(s) carry a retention period. Zero is correct on a fresh release; SemantIQ never fills these in.') AS detail
FROM retention_policies
WHERE retention_months IS NOT NULL;

SELECT
    '12. no retention policy is approved yet' AS checkpoint,
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'ACTION NEEDED' END AS verdict,
    CONCAT(COUNT(*), ' approved. Zero is correct until a person signs one off.') AS detail
FROM retention_policies
WHERE status = 'approved';

-- --------------------------------------------------------------- exceptions
SELECT
    '13. exceptions table readable' AS checkpoint,
    'PASS' AS verdict,
    CONCAT(COUNT(*), ' exception(s) recorded. Zero is correct on a fresh release.') AS detail
FROM sovereignty_exceptions;

SELECT
    '14. no exception is in force yet' AS checkpoint,
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'ACTION NEEDED' END AS verdict,
    CONCAT(COUNT(*), ' approved exception(s). Each one is a departure from the approved sovereignty position.') AS detail
FROM sovereignty_exceptions
WHERE status = 'approved';

-- -------------------------------------------------------------------- auditor
SELECT
    '15. auditor accounts' AS checkpoint,
    'INFORMATION' AS verdict,
    CONCAT(COUNT(*), ' account(s) carry the Auditor capability. An Auditor must be a federated account on this deployment.') AS detail
FROM users
WHERE is_auditor = 1;

SELECT '--- END VERIFICATION ---' AS report;
