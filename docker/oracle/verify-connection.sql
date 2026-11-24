-- Run this script as the application user connected to FREEPDB1.
-- Example:
-- docker compose exec oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"'

SELECT
    USER AS username,
    SYS_CONTEXT('USERENV', 'CON_NAME') AS container_name,
    SYSTIMESTAMP AS checked_at
FROM DUAL;
