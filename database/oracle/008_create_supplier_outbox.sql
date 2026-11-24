-- Transactional outbox for reliable supplier read-model synchronization.
-- Safe to execute repeatedly on an already initialized local schema.

BEGIN
    EXECUTE IMMEDIATE '
        CREATE TABLE supplier_outbox_events (
            event_id VARCHAR2(32) PRIMARY KEY,
            event_type VARCHAR2(80) NOT NULL,
            aggregate_id NUMBER(19) NOT NULL,
            payload CLOB NOT NULL,
            status VARCHAR2(20) DEFAULT ''PENDING'' NOT NULL,
            attempts NUMBER(5) DEFAULT 0 NOT NULL,
            occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            next_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            locked_until TIMESTAMP,
            published_at TIMESTAMP,
            last_error VARCHAR2(2000),
            CONSTRAINT chk_supplier_outbox_status
                CHECK (status IN (''PENDING'', ''PROCESSING'', ''PUBLISHED'', ''FAILED''))
        )';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

BEGIN
    EXECUTE IMMEDIATE '
        CREATE INDEX idx_supplier_outbox_dispatch
        ON supplier_outbox_events (status, next_attempt_at, occurred_at)';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

PROMPT Supplier transactional outbox is ready.
