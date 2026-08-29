-- ============================================================================
-- CHECK-R1.4c-i-PRE.sql
--
-- Run this BEFORE `php artisan migrate --force` for R1.4c-i.
--
-- SELECT ONLY. This script contains no CREATE, ALTER, DROP, INSERT, UPDATE,
-- DELETE, TRUNCATE, GRANT or REVOKE. It cannot change anything.
--
-- HOW TO RUN: phpMyAdmin -> select the SemantIQ database FIRST (the left-hand
-- panel, click the database name) -> SQL tab -> paste all of this -> Go.
--
-- Selecting the database first matters. Two earlier diagnostics returned empty
-- because they were run with no database selected.
--
-- READ THE `verdict` COLUMN. Every row should say PASS. If any row says
-- STOP or ACTION NEEDED, do not run the migration; send me the output.
-- ============================================================================

SELECT 'A1. database selected' AS check_name,
       IFNULL(DATABASE(), '(none)') AS observed,
       CASE WHEN DATABASE() IS NULL
            THEN 'STOP - no database selected, every row below will be wrong'
            ELSE 'PASS' END AS verdict

UNION ALL SELECT '---', '---', '---'

-- ---------------------------------------------------------------- prereq 3
-- None of the three target tables may already exist.
UNION ALL
SELECT 'B1. privacy_requests must NOT exist yet',
       CAST(COUNT(*) AS CHAR),
       CASE WHEN COUNT(*) = 0 THEN 'PASS'
            ELSE 'STOP - table already exists, unexpected' END
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_requests'

UNION ALL
SELECT 'B2. privacy_request_records must NOT exist yet',
       CAST(COUNT(*) AS CHAR),
       CASE WHEN COUNT(*) = 0 THEN 'PASS'
            ELSE 'STOP - table already exists, unexpected' END
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_request_records'

UNION ALL
SELECT 'B3. privacy_correction_notes must NOT exist yet',
       CAST(COUNT(*) AS CHAR),
       CASE WHEN COUNT(*) = 0 THEN 'PASS'
            ELSE 'STOP - table already exists, unexpected' END
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_correction_notes'

UNION ALL SELECT '---', '---', '---'

-- ---------------------------------------------------------------- prereq 4
-- The R1.4a and R1.4b migrations must already be recorded as run.
UNION ALL
-- CORRECTED TWICE, and the second correction is the interesting one.
--
-- The first version expected SEVEN migrations from LIKE patterns that only
-- four filenames can ever match. The fix replaced it with SIX exact names -
-- and silently dropped a real one, `add_module_and_outcome_indexes_to_
-- audit_events_table`. So it could PASS while an R1.4b migration was missing,
-- while claiming the previous batches were fully migrated.
--
-- Both errors had the same cause: a list written by hand. The names below are
-- the complete set of `2026_08_27_*` and `2026_08_28_*` migrations at commit
-- 660d74c427a9e9dab3d611d39e69396cab5953e9, and
-- `MigrationExpectationTest` fails the build if this file and the repository
-- ever disagree.
SELECT 'C1. R1.4a + R1.4b migrations recorded',
       CONCAT(COUNT(*), ' of 7 expected'),
       CASE WHEN COUNT(*) = 7 THEN 'PASS'
            ELSE 'STOP - the previous batches are not fully migrated' END
FROM migrations
WHERE migration IN (
  '2026_08_27_090000_create_personal_data_categories_table',
  '2026_08_27_090100_create_data_protection_profiles_table',
  '2026_08_27_090200_create_data_sovereignty_profiles_table',
  '2026_08_27_090300_add_structured_privacy_contact_to_organisations_table',
  '2026_08_28_090000_create_sovereignty_exceptions_table',
  '2026_08_28_090100_create_retention_policies_table',
  '2026_08_28_090200_add_module_and_outcome_indexes_to_audit_events_table')

UNION ALL
-- Seven migrations, five NEW TABLES. `add_structured_privacy_contact_to_
-- organisations_table` and `add_module_and_outcome_indexes_to_audit_events_
-- table` both alter an existing table rather than creating one, so five is
-- correct here and is not a copy of the number above.
SELECT 'C2. tables the previous batches created are present',
       CONCAT(COUNT(*), ' of 5 expected'),
       CASE WHEN COUNT(*) = 5 THEN 'PASS'
            ELSE 'STOP - a previous batch table is missing' END
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('personal_data_categories', 'data_protection_profiles',
                     'data_sovereignty_profiles', 'sovereignty_exceptions',
                     'retention_policies')

UNION ALL
SELECT 'C3. the R1.4b retention unique key, after the 1059 recovery',
       CONCAT(COUNT(DISTINCT INDEX_NAME), ' index(es) named correctly'),
       CASE WHEN COUNT(DISTINCT INDEX_NAME) = 1 THEN 'PASS'
            ELSE 'STOP - the R1.4b recovery is not in the state expected' END
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'retention_policies'
  AND INDEX_NAME = 'retention_policies_org_category_unique'

UNION ALL SELECT '---', '---', '---'

-- ---------------------------------------------------------------- prereq 3b
-- The three R1.4c-i migrations must NOT already be recorded.
UNION ALL
-- EXACT NAMES, NOT A LIKE PATTERN. A pattern is a guess about filenames, and
-- this file has already been wrong twice for exactly that reason.
SELECT 'D1. R1.4c-i migrations not yet recorded',
       CONCAT(COUNT(*), ' of 3 must be absent'),
       CASE WHEN COUNT(*) = 0 THEN 'PASS'
            ELSE 'STOP - already recorded, do not run the migration' END
FROM migrations
WHERE migration IN (
  '2026_08_29_090000_create_privacy_requests_table',
  '2026_08_29_090100_create_privacy_request_records_table',
  '2026_08_29_090200_create_privacy_correction_notes_table')

UNION ALL SELECT '---', '---', '---'

-- ------------------------------------------------------- context, not a gate
UNION ALL
SELECT 'E1. audit events currently held (for the after comparison)',
       CAST(COUNT(*) AS CHAR), 'INFO'
FROM audit_events

UNION ALL
SELECT 'E2. highest migration batch number',
       CAST(IFNULL(MAX(batch), 0) AS CHAR), 'INFO'
FROM migrations;
