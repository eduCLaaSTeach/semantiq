-- ============================================================
-- SemantIQ R1.4a check
-- ============================================================
-- Paste the whole file into phpMyAdmin SQL and press Go, once.
-- It changes nothing. Every query only reads.
--
-- You get one table with a RESULT column.
--   PASS          nothing to do
--   ACTION NEEDED do the thing in the last column, then run this again
--   FAIL          send the row to Claude
--
-- Do not compare any numbers yourself. The script does that.
-- ============================================================

SELECT 1 AS step, 'Gate 4 tables exist' AS what_was_checked,
  CASE WHEN (SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('personal_data_categories',
                                   'data_protection_profiles',
                                   'data_sovereignty_profiles')) = 3
       THEN 'PASS' ELSE 'FAIL' END AS result,
  CASE WHEN (SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('personal_data_categories',
                                   'data_protection_profiles',
                                   'data_sovereignty_profiles')) = 3
       THEN 'Nothing to do'
       ELSE 'Send this row to Claude' END AS what_to_do

UNION ALL SELECT 2, 'Privacy contact fields added',
  CASE WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organisations'
                AND COLUMN_NAME IN ('privacy_contact_name','privacy_contact_email',
                                    'privacy_contact_phone','privacy_contact_role')) = 4
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organisations'
                AND COLUMN_NAME IN ('privacy_contact_name','privacy_contact_email',
                                    'privacy_contact_phone','privacy_contact_role')) = 4
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 3, 'Old privacy contact field kept',
  CASE WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organisations'
                AND COLUMN_NAME = 'privacy_contact') = 1
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organisations'
                AND COLUMN_NAME = 'privacy_contact') = 1
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 4, 'Personal data categories seeded',
  CASE
    WHEN (SELECT COUNT(*) FROM personal_data_categories) = 7 THEN 'PASS'
    WHEN (SELECT COUNT(*) FROM personal_data_categories) = 0
     AND (SELECT COUNT(*) FROM audit_events
           WHERE action LIKE 'governance.personal_data_category%') = 0
       THEN 'ACTION NEEDED'
    ELSE 'FAIL' END,
  CASE
    WHEN (SELECT COUNT(*) FROM personal_data_categories) = 7 THEN 'Nothing to do'
    WHEN (SELECT COUNT(*) FROM personal_data_categories) = 0
     AND (SELECT COUNT(*) FROM audit_events
           WHERE action LIKE 'governance.personal_data_category%') = 0
       THEN 'Sign in, open Compliance > Data Protection > Personal / Sensitive Data, then run this file again'
    ELSE 'Send this row to Claude' END

UNION ALL SELECT 5, 'Every category names a table',
  CASE WHEN (SELECT COUNT(*) FROM personal_data_categories) = 0 THEN 'PASS'
       WHEN (SELECT COUNT(*) FROM personal_data_categories
              WHERE source_tables IS NULL OR source_tables = '[]') = 0
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM personal_data_categories) = 0
       THEN 'Nothing yet - depends on step 4'
       WHEN (SELECT COUNT(*) FROM personal_data_categories
              WHERE source_tables IS NULL OR source_tables = '[]') = 0
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 6, 'Sovereignty profile exists',
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles) >= 1
       THEN 'PASS' ELSE 'ACTION NEEDED' END,
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles) >= 1
       THEN 'Nothing to do'
       ELSE 'Sign in and open Compliance > Data Sovereignty > Sovereignty Profile, then run this again' END

UNION ALL SELECT 7, 'Storage geography is Singapore',
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE storage_geography = 'sg') >= 1
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE storage_geography = 'sg') >= 1
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 8, 'Backup geography is Singapore',
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE backup_geography = 'sg') >= 1
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE backup_geography = 'sg') >= 1
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 9, 'External replication is none',
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE external_replication = 'none') >= 1
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE external_replication = 'none') >= 1
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 10, 'No cross-geo switch is turned on',
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE cross_geo_storage = 1 OR cross_geo_processing = 1
                 OR cross_geo_ai = 1 OR cross_geo_conversation_history = 1) = 0
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles
              WHERE cross_geo_storage = 1 OR cross_geo_processing = 1
                 OR cross_geo_ai = 1 OR cross_geo_conversation_history = 1) = 0
       THEN 'Nothing to do'
       ELSE 'A profile allows data to leave its geography. Send this row to Claude' END

UNION ALL SELECT 11, 'At most one approved version of each profile',
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles WHERE status = 'approved') <= 1
        AND (SELECT COUNT(*) FROM data_protection_profiles WHERE status = 'approved') <= 1
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM data_sovereignty_profiles WHERE status = 'approved') <= 1
        AND (SELECT COUNT(*) FROM data_protection_profiles WHERE status = 'approved') <= 1
       THEN 'Nothing to do'
       ELSE 'Two versions are in force at once. Send this row to Claude' END

UNION ALL SELECT 12, 'Every governance row has an organisation',
  CASE WHEN (SELECT COUNT(*) FROM personal_data_categories WHERE organisation_id IS NULL)
          + (SELECT COUNT(*) FROM data_sovereignty_profiles WHERE organisation_id IS NULL)
          + (SELECT COUNT(*) FROM data_protection_profiles WHERE organisation_id IS NULL) = 0
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM personal_data_categories WHERE organisation_id IS NULL)
          + (SELECT COUNT(*) FROM data_sovereignty_profiles WHERE organisation_id IS NULL)
          + (SELECT COUNT(*) FROM data_protection_profiles WHERE organisation_id IS NULL) = 0
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 13, 'Governance changes are being audited',
  CASE WHEN (SELECT COUNT(*) FROM audit_events WHERE action LIKE 'governance.%') >= 1
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM audit_events WHERE action LIKE 'governance.%') >= 1
       THEN 'Nothing to do' ELSE 'Send this row to Claude' END

UNION ALL SELECT 14, 'Audit trail is still append-only',
  CASE WHEN (SELECT COUNT(*) FROM information_schema.TRIGGERS
              WHERE TRIGGER_SCHEMA = DATABASE()
                AND EVENT_OBJECT_TABLE = 'audit_events') = 2
       THEN 'PASS' ELSE 'FAIL' END,
  CASE WHEN (SELECT COUNT(*) FROM information_schema.TRIGGERS
              WHERE TRIGGER_SCHEMA = DATABASE()
                AND EVENT_OBJECT_TABLE = 'audit_events') = 2
       THEN 'Nothing to do'
       ELSE 'STOP. The audit protection is gone. Send this row to Claude' END

ORDER BY step;
