DO $$
DECLARE
  r RECORD;
BEGIN
  FOR r IN
    SELECT tablename
    FROM pg_tables
    WHERE schemaname = 'mysimconnect'
    ORDER BY tablename
  LOOP
    EXECUTE format('ALTER TABLE mysimconnect.%I SET SCHEMA public', r.tablename);
  END LOOP;
END $$;

DROP SCHEMA IF EXISTS mysimconnect CASCADE;
