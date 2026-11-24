<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Infrastructure\Http;

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
        parent::tearDown();
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
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $supplier = json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsInt($supplier['id']);
        self::assertSame($this->email, $supplier['email']);
        $id = $supplier['id'];

        $fetched = $this->request('GET', '/api/suppliers/'.$id);
        self::assertSame(Response::HTTP_OK, $fetched->getStatusCode());
        self::assertSame($id, json_decode((string) $fetched->getContent(), true, 512, JSON_THROW_ON_ERROR)['id']);

        $updated = $this->request('PUT', '/api/suppliers/'.$id, [
            'name' => 'Updated Functional Supplier',
            'email' => $this->email,
            'phone' => null,
            'taxNumber' => 'DE-UPDATED',
        ]);
        self::assertSame(Response::HTTP_OK, $updated->getStatusCode(), (string) $updated->getContent());
        self::assertSame('Updated Functional Supplier', json_decode((string) $updated->getContent(), true, 512, JSON_THROW_ON_ERROR)['name']);

        $deleted = $this->request('DELETE', '/api/suppliers/'.$id);
        self::assertSame(Response::HTTP_NO_CONTENT, $deleted->getStatusCode());

        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', '/api/suppliers/'.$id)->getStatusCode());
    }

    public function testCreateRejectsInvalidPayload(): void
    {
        $response = $this->request('POST', '/api/suppliers', ['name' => ' ', 'email' => 'not-an-email']);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertArrayHasKey('error', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testCreateRejectsDuplicateEmail(): void
    {
        $payload = ['name' => 'Duplicate Test Supplier', 'email' => $this->email];
        self::assertSame(Response::HTTP_CREATED, $this->request('POST', '/api/suppliers', $payload)->getStatusCode());

        $duplicate = $this->request('POST', '/api/suppliers', ['name' => 'Second Supplier', 'email' => $this->email]);
        self::assertSame(Response::HTTP_CONFLICT, $duplicate->getStatusCode());
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

        $response = $this->request('DELETE', '/api/suppliers/'.(int) $supplierId);
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
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
        self::assertArrayHasKey('/api/suppliers/{id}', $document['paths']);

        $ui = $this->request('GET', '/api/docs');
        self::assertSame(Response::HTTP_OK, $ui->getStatusCode());
        self::assertStringContainsString('swagger-ui', (string) $ui->getContent());
    }

    /** @param array<string, mixed>|null $payload */
    private function request(string $method, string $uri, ?array $payload = null): Response
    {
        $content = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
        $request = Request::create($uri, $method, server: ['CONTENT_TYPE' => 'application/json'], content: $content);
        return $this->httpKernel->handle($request, HttpKernelInterface::MAIN_REQUEST);
    }
}
