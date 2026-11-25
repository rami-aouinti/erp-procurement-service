-- Extensible global application settings and per-user UI preferences.
-- Run as ERP_APP after the existing Oracle schema scripts.
WHENEVER SQLERROR EXIT SQL.SQLCODE

BEGIN
    EXECUTE IMMEDIATE '
        CREATE TABLE app_configuration_settings (
            setting_key VARCHAR2(100) PRIMARY KEY,
            setting_value CLOB NOT NULL,
            updated_by VARCHAR2(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
            version NUMBER(10) DEFAULT 1 NOT NULL,
            CONSTRAINT chk_app_config_value_json CHECK (setting_value IS JSON),
            CONSTRAINT chk_app_config_version CHECK (version > 0)
        )';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

BEGIN
    EXECUTE IMMEDIATE '
        CREATE TABLE app_user_preferences (
            user_id VARCHAR2(255) NOT NULL,
            preference_key VARCHAR2(80) NOT NULL,
            preference_value CLOB NOT NULL,
            updated_at TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
            CONSTRAINT pk_app_user_preferences PRIMARY KEY (user_id, preference_key),
            CONSTRAINT chk_app_user_pref_json CHECK (preference_value IS JSON)
        )';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

PROMPT Configuration settings and user preferences schema is ready.
