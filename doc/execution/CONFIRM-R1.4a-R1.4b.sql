-- CONFIRM-R1.4a-R1.4b.sql
-- SELECT ONLY. Closes out the two STOP rows in CHECK-R1.4c-i-PRE.sql, which
-- were caused by wrong expectations in that script, not by the database.

SELECT 'H1. all seven R1.4a + R1.4b migrations recorded' AS check_name,
       CONCAT(COUNT(*), ' of 7') AS observed,
       CASE WHEN COUNT(*) = 7 THEN 'PASS' ELSE 'REPORT TO ME' END AS verdict
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
SELECT 'H2. all five R1.4a + R1.4b tables present',
       CONCAT(COUNT(*), ' of 5'),
       CASE WHEN COUNT(*) = 5 THEN 'PASS' ELSE 'REPORT TO ME' END
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('personal_data_categories', 'data_protection_profiles',
                     'data_sovereignty_profiles', 'sovereignty_exceptions',
                     'retention_policies')

UNION ALL
SELECT 'H3. name it: which of the five is present',
       GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME SEPARATOR ', '),
       'INFO'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('personal_data_categories', 'data_protection_profiles',
                     'data_sovereignty_profiles', 'sovereignty_exceptions',
                     'retention_policies')

UNION ALL
SELECT 'H4. the audit index migration recorded',
       CAST(COUNT(*) AS CHAR),
       CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'REPORT TO ME' END
FROM migrations
WHERE migration = '2026_08_28_090200_add_module_and_outcome_indexes_to_audit_events_table';
