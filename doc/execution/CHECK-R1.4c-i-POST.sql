-- ============================================================================
-- CHECK-R1.4c-i-POST.sql
--
-- Run this AFTER `php artisan migrate --force` for R1.4c-i.
--
-- SELECT ONLY. No CREATE, ALTER, DROP, INSERT, UPDATE, DELETE, TRUNCATE,
-- GRANT or REVOKE. It cannot change anything, and it creates no test data.
--
-- HOW TO RUN: phpMyAdmin -> select the SemantIQ database FIRST -> SQL tab ->
-- paste all of this -> Go.
--
-- READ THE `verdict` COLUMN. Every row should say PASS.
--
-- WHY COUNT(DISTINCT INDEX_NAME) AND NOT COUNT(*): information_schema.STATISTICS
-- holds ONE ROW PER COLUMN of an index, not one row per index. A two-column
-- index returns two rows. Counting rows made a correct R1.4b schema look
-- broken once; this is that lesson written down.
-- ============================================================================

SELECT 'A1. database selected' AS check_name,
       IFNULL(DATABASE(), '(none)') AS observed,
       'expected: the SemantIQ database' AS expected,
       CASE WHEN DATABASE() IS NULL THEN 'STOP - select the database first'
            ELSE 'PASS' END AS verdict

UNION ALL SELECT '---', '---', '---', '---'

-- --------------------------------------------------------------- the tables
UNION ALL
SELECT 'B1. three tables exist',
       CAST(COUNT(*) AS CHAR), '3',
       CASE WHEN COUNT(*) = 3 THEN 'PASS' ELSE 'STOP - a table is missing' END
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('privacy_requests', 'privacy_request_records',
                     'privacy_correction_notes')

UNION ALL
SELECT 'B2. privacy_requests column count',
       CAST(COUNT(*) AS CHAR), '30',
       CASE WHEN COUNT(*) = 30 THEN 'PASS' ELSE 'STOP - shape differs' END
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_requests'

UNION ALL
SELECT 'B3. privacy_request_records column count',
       CAST(COUNT(*) AS CHAR), '14',
       CASE WHEN COUNT(*) = 14 THEN 'PASS' ELSE 'STOP - shape differs' END
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_request_records'

UNION ALL
SELECT 'B4. privacy_correction_notes column count',
       CAST(COUNT(*) AS CHAR), '12',
       CASE WHEN COUNT(*) = 12 THEN 'PASS' ELSE 'STOP - shape differs' END
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_correction_notes'

UNION ALL SELECT '---', '---', '---', '---'

-- ------------------------------------------------- the named keys and indexes
UNION ALL
SELECT 'C1. privacy_requests_org_reference_unique',
       CONCAT(COUNT(DISTINCT INDEX_NAME), ' index, ',
              CAST(COUNT(*) AS CHAR), ' column(s), unique=',
              CAST(IFNULL(MIN(NON_UNIQUE), 9) AS CHAR)),
       '1 index, 2 columns, unique=0',
       CASE WHEN COUNT(DISTINCT INDEX_NAME) = 1 AND COUNT(*) = 2
                 AND MIN(NON_UNIQUE) = 0
            THEN 'PASS' ELSE 'STOP' END
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_requests'
  AND INDEX_NAME = 'privacy_requests_org_reference_unique'

UNION ALL
SELECT 'C2. privacy_requests_org_status_due_index',
       CONCAT(COUNT(DISTINCT INDEX_NAME), ' index, ',
              CAST(COUNT(*) AS CHAR), ' column(s)'),
       '1 index, 3 columns',
       CASE WHEN COUNT(DISTINCT INDEX_NAME) = 1 AND COUNT(*) = 3
            THEN 'PASS' ELSE 'STOP' END
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_requests'
  AND INDEX_NAME = 'privacy_requests_org_status_due_index'

UNION ALL
SELECT 'C3. privacy_requests_org_subject_index',
       CONCAT(COUNT(DISTINCT INDEX_NAME), ' index, ',
              CAST(COUNT(*) AS CHAR), ' column(s)'),
       '1 index, 2 columns',
       CASE WHEN COUNT(DISTINCT INDEX_NAME) = 1 AND COUNT(*) = 2
            THEN 'PASS' ELSE 'STOP' END
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_requests'
  AND INDEX_NAME = 'privacy_requests_org_subject_index'

UNION ALL
SELECT 'C4. privacy_request_records_org_request_band_index',
       CONCAT(COUNT(DISTINCT INDEX_NAME), ' index, ',
              CAST(COUNT(*) AS CHAR), ' column(s)'),
       '1 index, 3 columns',
       CASE WHEN COUNT(DISTINCT INDEX_NAME) = 1 AND COUNT(*) = 3
            THEN 'PASS' ELSE 'STOP' END
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_request_records'
  AND INDEX_NAME = 'privacy_request_records_org_request_band_index'

UNION ALL
SELECT 'C5. privacy_correction_notes_org_event_index',
       CONCAT(COUNT(DISTINCT INDEX_NAME), ' index, ',
              CAST(COUNT(*) AS CHAR), ' column(s)'),
       '1 index, 2 columns',
       CASE WHEN COUNT(DISTINCT INDEX_NAME) = 1 AND COUNT(*) = 2
            THEN 'PASS' ELSE 'STOP' END
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_correction_notes'
  AND INDEX_NAME = 'privacy_correction_notes_org_event_index'

UNION ALL SELECT '---', '---', '---', '---'

-- ------------------------------------------------------- foreign key wiring
UNION ALL
SELECT 'D1. privacy_requests foreign keys',
       CAST(COUNT(DISTINCT CONSTRAINT_NAME) AS CHAR), '8',
       CASE WHEN COUNT(DISTINCT CONSTRAINT_NAME) = 8 THEN 'PASS'
            ELSE 'STOP - foreign key wiring differs' END
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_requests'
  AND REFERENCED_TABLE_NAME IS NOT NULL

UNION ALL
SELECT 'D2. privacy_request_records foreign keys',
       CAST(COUNT(DISTINCT CONSTRAINT_NAME) AS CHAR), '2',
       CASE WHEN COUNT(DISTINCT CONSTRAINT_NAME) = 2 THEN 'PASS'
            ELSE 'STOP' END
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_request_records'
  AND REFERENCED_TABLE_NAME IS NOT NULL

UNION ALL
SELECT 'D3. privacy_correction_notes foreign keys',
       CAST(COUNT(DISTINCT CONSTRAINT_NAME) AS CHAR), '5',
       CASE WHEN COUNT(DISTINCT CONSTRAINT_NAME) = 5 THEN 'PASS'
            ELSE 'STOP' END
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_correction_notes'
  AND REFERENCED_TABLE_NAME IS NOT NULL

UNION ALL
SELECT 'D4. the correction-note link to audit_events is RESTRICT',
       IFNULL(MAX(DELETE_RULE), '(absent)'), 'RESTRICT',
       CASE WHEN MAX(DELETE_RULE) = 'RESTRICT' THEN 'PASS'
            ELSE 'STOP - an annotation could vanish with its event' END
FROM information_schema.REFERENTIAL_CONSTRAINTS rc
JOIN information_schema.KEY_COLUMN_USAGE k
  ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
 AND k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
  AND k.TABLE_NAME = 'privacy_correction_notes'
  AND k.COLUMN_NAME = 'audit_event_id'

UNION ALL SELECT '---', '---', '---', '---'

-- ------------------------------------------------------- migration ledger
UNION ALL
SELECT 'E1. the three migrations are recorded',
       CAST(COUNT(*) AS CHAR), '3',
       CASE WHEN COUNT(*) = 3 THEN 'PASS' ELSE 'STOP' END
FROM migrations
WHERE migration IN ('2026_08_29_090000_create_privacy_requests_table',
                    '2026_08_29_090100_create_privacy_request_records_table',
                    '2026_08_29_090200_create_privacy_correction_notes_table')

UNION ALL SELECT '---', '---', '---', '---'

-- ----------------------------------- the tables must be EMPTY. No test data.
UNION ALL
SELECT 'F1. privacy_requests is empty',
       CAST(COUNT(*) AS CHAR), '0',
       CASE WHEN COUNT(*) = 0 THEN 'PASS'
            ELSE 'INFO - rows present, report but do not alter' END
FROM privacy_requests

UNION ALL
SELECT 'F2. privacy_request_records is empty',
       CAST(COUNT(*) AS CHAR), '0',
       CASE WHEN COUNT(*) = 0 THEN 'PASS'
            ELSE 'INFO - rows present, report but do not alter' END
FROM privacy_request_records

UNION ALL
SELECT 'F3. privacy_correction_notes is empty',
       CAST(COUNT(*) AS CHAR), '0',
       CASE WHEN COUNT(*) = 0 THEN 'PASS'
            ELSE 'INFO - rows present, report but do not alter' END
FROM privacy_correction_notes

UNION ALL SELECT '---', '---', '---', '---'

-- ------------- the triggers are a SEPARATE approved step. Expected absent now.
UNION ALL
SELECT 'G1. correction-note triggers NOT yet installed',
       CAST(COUNT(*) AS CHAR), '0 at this stage',
       CASE WHEN COUNT(*) = 0 THEN 'PASS - separate approved step still pending'
            ELSE 'INFO - triggers already present, report to me' END
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE = 'privacy_correction_notes';
