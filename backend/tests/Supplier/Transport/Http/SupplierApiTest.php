<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Transport\Http;

use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Repository\SupplierRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SupplierApiTest extends KernelTestCase
{
    private Connection $connection;
    private HttpKernelInterface $httpKernel;
    private string $email;
    private ?int $indexedSupplierId = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->httpKernel = static::getContainer()->get('http_kernel');
        $this->connection->beginTransaction();
        $this->email = sprintf('supplier-test-%s@example.test', bin2hex(random_bytes(6)));
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        // Elasticsearch is outside the Oracle transaction: clean up projections explicitly.
        if ($this->indexedSupplierId !== null) {
            try {
                static::getContainer()->get(SupplierSearchIndex::class)->delete($this->indexedSupplierId);
            } catch (\Throwable) {
                // Preserve the original test failure if Elasticsearch is unavailable.
            }
        }

        parent::tearDown();
    }

    public function testOnboardingAndConfigurationEndpointsRequireAKeycloakAccessToken(): void
    {
        $onboarding = $this->request('GET', '/api/supplier-onboarding/queue', null, null);
        $preferences = $this->request('GET', '/api/configuration/me/preferences', null, null);
        $siteConfiguration = $this->request('GET', '/api/configuration/site', null, null);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $onboarding->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $preferences->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $siteConfiguration->getStatusCode());
    }


    public function testLegacySupplierEndpointsRequireAKeycloakIdentity(): void
    {
        foreach ([
            '/api/suppliers',
            '/api/suppliers/42/profile',
            '/api/suppliers/42/contacts',
            '/api/suppliers/42/addresses',
            '/api/suppliers/42/bank-accounts',
        ] as $uri) {
            self::assertSame(
                Response::HTTP_UNAUTHORIZED,
                $this->request('GET', $uri, null, null)->getStatusCode(),
                sprintf('Expected %s to reject an anonymous request.', $uri),
            );
        }
    }

    public function testLegacySupplierRoutesEnforceRoleBoundaries(): void
    {
        self::assertSame(
            Response::HTTP_OK,
            $this->request('GET', '/api/suppliers', null, ['supplier-reviewer'])->getStatusCode(),
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->request('POST', '/api/suppliers', ['name' => 'Forbidden Direct Supplier'], ['supplier-reviewer'])->getStatusCode(),
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->request('GET', '/api/suppliers/42/bank-accounts', null, ['supplier-reviewer'])->getStatusCode(),
        );
    }


    public function testSupplierClassificationEndpointsRequireAuthenticationAndAdminTaxonomyWrites(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', '/api/supplier-classifications/categories', null, null)->getStatusCode());
        self::assertSame(Response::HTTP_OK, $this->request('GET', '/api/supplier-classifications/categories', null, ['supplier-reviewer'])->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $this->request('POST', '/api/supplier-classifications/categories', ['code'=>'NO-ROLE','name'=>'No role'], ['procurement-user'])->getStatusCode());

        $created = $this->request('POST', '/api/supplier-classifications/categories', ['code'=>'API-'.bin2hex(random_bytes(3)), 'name'=>'API category']);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string)$created->getContent());

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->request('GET', '/api/suppliers?categoryId=not-an-id')->getStatusCode());
    }

    public function testSupplierCrudLifecycle(): void
    {
        $listBefore = $this->request('GET', '/api/suppliers');
        self::assertSame(Response::HTTP_OK, $listBefore->getStatusCode());
        self::assertIsArray(json_decode((string) $listBefore->getContent(), true));

        $created = $this->request('POST', '/api/suppliers', [
            'name' => 'Functional Test Supplier',
            'email' => $this->email,
            'phone' => '+49 421 123456',
            'taxNumber' => 'DE-TEST-123',
            'status' => 'ACTIVE',
            'contactPerson' => 'Jane Smith',
            'street' => 'Hafenstraße 12',
            'city' => 'Bremen',
            'postalCode' => '28195',
            'country' => 'DE',
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $supplier = json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsInt($supplier['id']);
        self::assertSame($this->email, $supplier['email']);
        self::assertSame('ACTIVE', $supplier['status']);
        self::assertSame('Jane Smith', $supplier['contactPerson']);
        self::assertSame('Bremen', $supplier['city']);
        self::assertSame('DE', $supplier['country']);
        $id = $supplier['id'];

        $fetched = $this->request('GET', '/api/suppliers/' . $id);
        self::assertSame(Response::HTTP_OK, $fetched->getStatusCode());
        self::assertSame($id, json_decode((string) $fetched->getContent(), true, 512, JSON_THROW_ON_ERROR)['id']);

        $updated = $this->request('PUT', '/api/suppliers/' . $id, [
            'name' => 'Updated Functional Supplier',
            'email' => $this->email,
            'phone' => null,
            'taxNumber' => 'DE-UPDATED',
            'status' => 'BLOCKED',
            'contactPerson' => 'John Smith',
            'street' => 'Neue Straße 5',
            'city' => 'Hamburg',
            'postalCode' => '20095',
            'country' => 'DE',
        ]);
        self::assertSame(Response::HTTP_OK, $updated->getStatusCode(), (string) $updated->getContent());
        $updatedSupplier = json_decode((string) $updated->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Updated Functional Supplier', $updatedSupplier['name']);
        self::assertSame('BLOCKED', $updatedSupplier['status']);
        self::assertSame('Hamburg', $updatedSupplier['city']);

        $deleted = $this->request('DELETE', '/api/suppliers/' . $id);
        self::assertSame(Response::HTTP_NO_CONTENT, $deleted->getStatusCode());

        $eventTypes = $this->connection->fetchFirstColumn(
            'SELECT event_type FROM supplier_outbox_events WHERE aggregate_id = :id',
            ['id' => $id],
        );
        self::assertCount(3, $eventTypes);
        self::assertEqualsCanonicalizing(['SupplierCreated', 'SupplierUpdated', 'SupplierDeleted'], $eventTypes);

        $auditEntries = $this->connection->fetchAllAssociative(
            'SELECT entity_type, action, DBMS_LOB.SUBSTR(changed_fields, 4000, 1) changed_fields FROM supplier_audit_logs WHERE supplier_id = :id ORDER BY id',
            ['id' => $id],
        );
        self::assertCount(3, $auditEntries);
        self::assertSame(['CREATED', 'UPDATED', 'DELETED'], array_column($auditEntries, 'ACTION'));
        foreach ($auditEntries as $entry) {
            $fields = json_decode((string) $entry['CHANGED_FIELDS'], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($fields);

            if ($entry['ACTION'] === 'DELETED') {
                self::assertSame(['deleted'], $fields);
            } else {
                self::assertContains('email', $fields);
            }

            self::assertNotContains($this->email, $fields, 'Audit data must contain field names, never field values.');
        }

        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', '/api/suppliers/' . $id)->getStatusCode());
    }

    public function testCreateRejectsInvalidPayload(): void
    {
        $response = $this->request('POST', '/api/suppliers', ['name' => ' ', 'email' => 'not-an-email']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertArrayHasKey('error', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testCreateRejectsDuplicateEmail(): void
    {
        $payload = ['name' => 'Duplicate Test Supplier', 'email' => $this->email];
        $created = $this->request('POST', '/api/suppliers', $payload);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $createdId = (int) json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        $duplicate = $this->request('POST', '/api/suppliers', ['name' => 'Second Supplier', 'email' => $this->email]);
        self::assertSame(Response::HTTP_CONFLICT, $duplicate->getStatusCode());

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', '/api/suppliers/' . $createdId)->getStatusCode());
    }

    public function testMissingSupplierReturnsNotFoundForGetAndUpdate(): void
    {
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', '/api/suppliers/2147483000')->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('PUT', '/api/suppliers/2147483000', ['name' => 'Missing'])->getStatusCode());
    }


    public function testDeleteRejectsSupplierReferencedByPurchaseOrders(): void
    {
        $supplierId = $this->connection->fetchOne('SELECT supplier_id FROM purchase_orders FETCH FIRST 1 ROWS ONLY');
        if ($supplierId === false) {
            self::markTestSkipped('Seed a purchase order to test the supplier foreign-key restriction.');
        }

        $response = $this->request('DELETE', '/api/suppliers/' . (int) $supplierId);
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testSupplierSearchFindsIndexedFields(): void
    {
        $created = $this->request('POST', '/api/suppliers', [
            'name' => 'Elasticsearch Demo Vendor',
            'email' => $this->email,
            'city' => 'Düsseldorf',
            'status' => 'ACTIVE',
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $id = (int) json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        // Search indexing is asynchronous through the outbox consumer. This
        // test owns its database transaction, so seed the projection explicitly.
        $supplier = static::getContainer()->get(SupplierRepository::class)->findById($id);
        self::assertNotNull($supplier);
        static::getContainer()->get(SupplierSearchIndex::class)->index($supplier);
        $this->indexedSupplierId = $id;
        $this->indexedSupplierId = $id;

        $results = $this->request('GET', '/api/suppliers?search=Elasticsearch%20Demo');
        self::assertSame(Response::HTTP_OK, $results->getStatusCode());
        $items = json_decode((string) $results->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains($id, array_column($items, 'id'));

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', '/api/suppliers/' . $id)->getStatusCode());
    }

    public function testCreateRejectsNonStringFields(): void
    {
        $response = $this->request('POST', '/api/suppliers', ['name' => 'Invalid payload', 'phone' => 12345]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testOpenApiSpecificationAndSwaggerUiAreAvailable(): void
    {
        $spec = $this->request('GET', '/api/docs.json');
        self::assertSame(Response::HTTP_OK, $spec->getStatusCode());
        $document = json_decode((string) $spec->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('3.0.3', $document['openapi']);
        self::assertArrayHasKey('/api/suppliers', $document['paths']);
        self::assertArrayHasKey('/api/configuration/site', $document['paths']);
        self::assertArrayHasKey('/api/configuration/me/preferences', $document['paths']);
        self::assertArrayHasKey('/api/suppliers/{id}', $document['paths']);
        self::assertArrayHasKey('/api/suppliers/{id}/contacts/{contactId}', $document['paths']);
        self::assertArrayHasKey('/api/suppliers/{id}/addresses/{addressId}', $document['paths']);

        $ui = $this->request('GET', '/api/docs');
        self::assertSame(Response::HTTP_OK, $ui->getStatusCode());
        self::assertStringContainsString('swagger-ui', (string) $ui->getContent());
    }

    public function testSupplierContactsAndAddressesReturnPersistedIdsAndMaintainSinglePrimary(): void
    {
        $created = $this->request('POST', '/api/suppliers', [
            'name' => 'Profile Relations Supplier',
            'email' => $this->email,
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $supplierId = (int) json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        $firstContact = $this->request('POST', '/api/suppliers/' . $supplierId . '/contacts', [
            'contactType' => 'GENERAL',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.test',
            'primary' => true,
        ]);
        self::assertSame(Response::HTTP_CREATED, $firstContact->getStatusCode(), (string) $firstContact->getContent());
        $firstContactData = json_decode((string) $firstContact->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsInt($firstContactData['id']);
        self::assertSame($supplierId, $firstContactData['supplierId']);

        $secondContact = $this->request('POST', '/api/suppliers/' . $supplierId . '/contacts', [
            'contactType' => 'GENERAL',
            'firstName' => 'Grace',
            'lastName' => 'Hopper',
            'primary' => true,
        ]);
        self::assertSame(Response::HTTP_CREATED, $secondContact->getStatusCode(), (string) $secondContact->getContent());
        $secondContactData = json_decode((string) $secondContact->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsInt($secondContactData['id']);

        $contacts = $this->request('GET', '/api/suppliers/' . $supplierId . '/contacts');
        self::assertSame(Response::HTTP_OK, $contacts->getStatusCode());
        $contactItems = json_decode((string) $contacts->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $contactItems);
        self::assertSame(1, count(array_filter($contactItems, static fn (array $item): bool => $item['primary'] === true)));
        self::assertSame($secondContactData['id'], $contactItems[0]['id']);


        $updatedContact = $this->request('PUT', '/api/suppliers/' . $supplierId . '/contacts/' . $secondContactData['id'], [
            'contactType' => 'GENERAL',
            'firstName' => 'Grace',
            'lastName' => 'Hopper',
            'jobTitle' => 'Admiral',
            'email' => 'grace@example.test',
            'phone' => null,
            'primary' => true,
        ]);
        self::assertSame(Response::HTTP_OK, $updatedContact->getStatusCode(), (string) $updatedContact->getContent());
        self::assertSame('Admiral', json_decode((string) $updatedContact->getContent(), true, 512, JSON_THROW_ON_ERROR)['jobTitle']);

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $this->request('DELETE', '/api/suppliers/' . $supplierId . '/contacts/' . $secondContactData['id'])->getStatusCode(),
        );
        $contactsAfterDelete = json_decode(
            (string) $this->request('GET', '/api/suppliers/' . $supplierId . '/contacts')->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertCount(1, $contactsAfterDelete);
        self::assertSame($firstContactData['id'], $contactsAfterDelete[0]['id']);
        self::assertTrue($contactsAfterDelete[0]['primary']);

        $firstAddress = $this->request('POST', '/api/suppliers/' . $supplierId . '/addresses', [
            'addressType' => 'BILLING',
            'street' => 'Hauptstraße 1',
            'postalCode' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'default' => true,
        ]);
        self::assertSame(Response::HTTP_CREATED, $firstAddress->getStatusCode(), (string) $firstAddress->getContent());
        $firstAddressData = json_decode((string) $firstAddress->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsInt($firstAddressData['id']);

        $secondAddress = $this->request('POST', '/api/suppliers/' . $supplierId . '/addresses', [
            'addressType' => 'BILLING',
            'street' => 'Alexanderplatz 2',
            'postalCode' => '10178',
            'city' => 'Berlin',
            'country' => 'DE',
            'default' => true,
        ]);
        self::assertSame(Response::HTTP_CREATED, $secondAddress->getStatusCode(), (string) $secondAddress->getContent());
        $secondAddressData = json_decode((string) $secondAddress->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsInt($secondAddressData['id']);

        $addresses = $this->request('GET', '/api/suppliers/' . $supplierId . '/addresses');
        self::assertSame(Response::HTTP_OK, $addresses->getStatusCode());
        $addressItems = json_decode((string) $addresses->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $addressItems);
        self::assertSame(1, count(array_filter($addressItems, static fn (array $item): bool => $item['default'] === true)));
        self::assertSame($secondAddressData['id'], $addressItems[0]['id']);

        $updatedAddress = $this->request('PUT', '/api/suppliers/' . $supplierId . '/addresses/' . $secondAddressData['id'], [
            'addressType' => 'BILLING',
            'label' => 'Head office',
            'companyName' => 'Profile Relations Supplier GmbH',
            'street' => 'Alexanderplatz 10',
            'street2' => '2. OG',
            'postalCode' => '10178',
            'city' => 'Berlin',
            'region' => 'Berlin',
            'country' => 'DE',
            'default' => true,
        ]);
        self::assertSame(Response::HTTP_OK, $updatedAddress->getStatusCode(), (string) $updatedAddress->getContent());
        $updatedAddressData = json_decode((string) $updatedAddress->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Alexanderplatz 10', $updatedAddressData['street']);
        self::assertSame('Head office', $updatedAddressData['label']);

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $this->request('DELETE', '/api/suppliers/' . $supplierId . '/addresses/' . $secondAddressData['id'])->getStatusCode(),
        );
        $addressesAfterDelete = json_decode(
            (string) $this->request('GET', '/api/suppliers/' . $supplierId . '/addresses')->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertCount(1, $addressesAfterDelete);
        self::assertSame($firstAddressData['id'], $addressesAfterDelete[0]['id']);
        self::assertTrue($addressesAfterDelete[0]['default']);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', '/api/suppliers/' . $supplierId)->getStatusCode());

        $auditCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM supplier_audit_logs WHERE supplier_id = :supplierId',
            ['supplierId' => $supplierId],
        );
        self::assertSame(10, $auditCount, 'Supplier, contact and address lifecycle operations must be audited.');
    }

    /** @param array<string, mixed>|null $payload */
    /** @param array<string, mixed>|null $payload @param list<string>|null $roles */
    private function request(string $method, string $uri, ?array $payload = null, ?array $roles = ['supplier-admin']): Response
    {
        $content = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
        $request = Request::create($uri, $method, server: ['CONTENT_TYPE' => 'application/json'], content: $content);
        if ($roles !== null) {
            // Internal request attribute simulates an upstream-authenticated identity.
            // It is not derived from any HTTP header and cannot be supplied by a client.
            $request->attributes->set('_keycloak_user', [
                'id' => 'supplier-api-test-user',
                'username' => 'supplier-api-test',
                'roles' => $roles,
            ]);
        }

        return $this->httpKernel->handle($request, HttpKernelInterface::MAIN_REQUEST);
    }
}
