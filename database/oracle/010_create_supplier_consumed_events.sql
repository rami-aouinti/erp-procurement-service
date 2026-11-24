-- Persistent consumer-side idempotency ledger for Supplier events.
-- Apply once to the existing Oracle application schema after 008_create_supplier_outbox.sql.

WHENEVER SQLERROR EXIT SQL.SQLCODE

BEGIN
    EXECUTE IMMEDIATE '
        CREATE TABLE supplier_consumed_events (
            event_id VARCHAR2(128) NOT NULL,
            event_type VARCHAR2(80) NOT NULL,
            aggregate_id NUMBER(19) NOT NULL,
            status VARCHAR2(20) DEFAULT ''PROCESSING'' NOT NULL,
            attempts NUMBER(5) DEFAULT 1 NOT NULL,
            received_at TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
            processed_at TIMESTAMP,
            locked_until TIMESTAMP,
            claim_token VARCHAR2(32),
            last_error VARCHAR2(500),
            CONSTRAINT pk_supplier_consumed_events PRIMARY KEY (event_id),
            CONSTRAINT chk_supplier_consumed_status
                CHECK (status IN (''PENDING'', ''PROCESSING'', ''PROCESSED'')),
            CONSTRAINT chk_supplier_consumed_attempts CHECK (attempts >= 1)
        )';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

BEGIN
    EXECUTE IMMEDIATE '
        CREATE INDEX idx_supplier_consumed_recovery
        ON supplier_consumed_events (status, locked_until)';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

COMMENT ON TABLE supplier_consumed_events IS
    'Persistent idempotency ledger for RabbitMQ Supplier events';

PROMPT Supplier consumer idempotency ledger is ready.
