-- CONFIRM-MIGRATION-LEDGER.sql
-- SELECT ONLY. Replaces `php artisan migrate:status` for the acceptance record.
--
-- `migrate:status` reads the `migrations` table, which is what this reads. The
-- one thing it adds over the earlier post-check is whether ANYTHING ELSE is
-- still pending, not just the three R1.4c-i migrations.
--
-- Run with the SemantIQ database selected.

SELECT 'J1. total migrations recorded' AS check_name,
       CONCAT(COUNT(*), ' of 33 files on main') AS observed,
       CASE WHEN COUNT(*) = 33 THEN 'PASS - nothing pending'
            WHEN COUNT(*) <  33 THEN 'REPORT TO ME - something never ran'
            ELSE 'REPORT TO ME - more rows than files' END AS verdict
FROM migrations

UNION ALL
SELECT 'J2. the three R1.4c-i migrations, with their batch',
       GROUP_CONCAT(CONCAT(migration, ' [batch ', batch, ']')
                    ORDER BY migration SEPARATOR ' | '),
       CASE WHEN COUNT(*) = 3 THEN 'PASS' ELSE 'REPORT TO ME' END
FROM migrations
WHERE migration LIKE '2026_08_29_09%'

UNION ALL
SELECT 'J3. they all landed in ONE batch',
       CONCAT('batch ', CAST(IFNULL(MIN(batch), 0) AS CHAR)),
       CASE WHEN COUNT(DISTINCT batch) = 1 THEN 'PASS - one migrate run'
            ELSE 'REPORT TO ME - split across runs' END
FROM migrations
WHERE migration LIKE '2026_08_29_09%'

UNION ALL
SELECT 'J4. highest batch number now',
       CAST(IFNULL(MAX(batch), 0) AS CHAR),
       'INFO - was 6 before this migration, so 7 is expected'
FROM migrations;
