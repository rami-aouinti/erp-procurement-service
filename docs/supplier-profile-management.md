# Supplier profile management

Supplier profile data is split into company details, contacts, addresses and bank accounts.

## Bank account API

All endpoints require the same authentication and authorization as the supplier API.

### Create a bank account

\`POST /api/suppliers/{supplierId}/bank-accounts\`

Request:

\`\`\`json
{
  "accountHolder": "Example GmbH",
  "iban": "DE89370400440532013000",
  "bic": "COBADEFFXXX",
  "bankName": "Commerzbank",
  "currency": "EUR",
  "primary": true
}
\`\`\`

The API normalizes the IBAN and BIC, validates the IBAN checksum (ISO 13616 mod-97), validates BIC syntax, and encrypts the account holder and IBAN before persistence.

The response never contains the full IBAN or encrypted payload:

\`\`\`json
{
  "id": 123,
  "supplierId": 42,
  "iban": "DE••••••••••••••••1300",
  "bic": "COBADEFFXXX",
  "bankName": "Commerzbank",
  "currency": "EUR",
  "primary": true
}
\`\`\`

### List bank accounts

\`GET /api/suppliers/{supplierId}/bank-accounts\`

The response contains masked IBANs only. The API does not expose ciphertext, account-holder plaintext, or the encryption key version.

## Encryption key setup

Generate a 32-byte key encoded as base64:

\`\`\`bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
\`\`\`

Set the result in the untracked local \`.env\` file:

\`\`\`dotenv
SUPPLIER_BANK_ENCRYPTION_KEY=your-generated-base64-key
SUPPLIER_BANK_ENCRYPTION_KEY_VERSION=v1
\`\`\`

Never commit the real key. In deployed environments, inject it from the platform secret manager. Back up the key securely: losing it makes existing bank data undecryptable.

## Key rotation

The database stores \`encryption_key_version\` with each bank account. A future rotation procedure must decrypt each record with its recorded old key version and re-encrypt it with the new key before retiring the old key. Do not simply replace the key while encrypted records still use the previous version.

## Security boundaries

- AES-256-GCM with a fresh random 96-bit nonce and authentication tag is used for account-holder and IBAN encryption.
- Plaintext bank details must not be written to logs, messages, search documents, exception messages, or audit payloads.
- Do not add bank account data to the supplier outbox or Elasticsearch indexing.
- Restrict bank-account endpoints to authorized supplier-management roles and use TLS in deployed environments.
