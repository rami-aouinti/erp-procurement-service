<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDocumentationController
{
    #[Route('/api/docs.json', name: 'api_docs_openapi', methods: ['GET'])]
    public function openApi(): JsonResponse
    {
        return new JsonResponse([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'ERP Procurement API',
                'version' => '1.0.0',
                'description' => 'REST API for suppliers in the ERP Procurement application.',
            ],
            'servers' => [['url' => '/']],
            'tags' => [['name' => 'Suppliers', 'description' => 'Supplier master data']],
            'paths' => [
                '/api/suppliers' => [
                    'get' => [
                        'tags' => ['Suppliers'], 'summary' => 'List suppliers', 'operationId' => 'listSuppliers',
                        'parameters' => [
                            ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Search supplier name, email or city.'],
                            ['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['ACTIVE', 'INACTIVE', 'BLOCKED']]],
                        ],
                        'responses' => ['200' => ['description' => 'Supplier collection', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Supplier']]]]]],
                    ],
                    'post' => [
                        'tags' => ['Suppliers'], 'summary' => 'Create a supplier', 'operationId' => 'createSupplier',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SupplierInput'], 'example' => ['name' => 'Acme GmbH', 'email' => 'contact@acme.de', 'phone' => '+49 421 123456', 'taxNumber' => 'DE123456789']]]],
                        'responses' => [
                            '201' => ['description' => 'Supplier created', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Supplier']]]],
                            '400' => ['$ref' => '#/components/responses/BadRequest'], '422' => ['$ref' => '#/components/responses/ValidationError'], '409' => ['$ref' => '#/components/responses/Conflict'],
                        ],
                    ],
                ],
                '/api/suppliers/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1]]],
                    'get' => [
                        'tags' => ['Suppliers'], 'summary' => 'Get a supplier', 'operationId' => 'getSupplier',
                        'responses' => ['200' => ['description' => 'Supplier details', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Supplier']]]], '404' => ['$ref' => '#/components/responses/NotFound']],
                    ],
                    'put' => [
                        'tags' => ['Suppliers'], 'summary' => 'Update a supplier', 'operationId' => 'updateSupplier',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SupplierInput']]]],
                        'responses' => ['200' => ['description' => 'Supplier updated', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Supplier']]]], '400' => ['$ref' => '#/components/responses/BadRequest'], '422' => ['$ref' => '#/components/responses/ValidationError'], '404' => ['$ref' => '#/components/responses/NotFound'], '409' => ['$ref' => '#/components/responses/Conflict']],
                    ],
                    'delete' => [
                        'tags' => ['Suppliers'], 'summary' => 'Delete a supplier', 'operationId' => 'deleteSupplier',
                        'responses' => ['204' => ['description' => 'Supplier deleted'], '404' => ['$ref' => '#/components/responses/NotFound'], '409' => ['$ref' => '#/components/responses/Conflict']],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'SupplierInput' => [
                        'type' => 'object', 'required' => ['name'],
                        'properties' => [
                            'name' => ['type' => 'string', 'maxLength' => 150, 'example' => 'Acme GmbH'],
                            'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 150, 'nullable' => true],
                            'phone' => ['type' => 'string', 'maxLength' => 30, 'nullable' => true],
                            'taxNumber' => ['type' => 'string', 'maxLength' => 50, 'nullable' => true],
                            'status' => ['type' => 'string', 'enum' => ['ACTIVE', 'INACTIVE', 'BLOCKED'], 'default' => 'ACTIVE'],
                            'contactPerson' => ['type' => 'string', 'maxLength' => 150, 'nullable' => true],
                            'street' => ['type' => 'string', 'maxLength' => 180, 'nullable' => true],
                            'city' => ['type' => 'string', 'maxLength' => 100, 'nullable' => true],
                            'postalCode' => ['type' => 'string', 'maxLength' => 20, 'nullable' => true],
                            'country' => ['type' => 'string', 'maxLength' => 2, 'nullable' => true, 'example' => 'DE'],
                        ],
                    ],
                    'Supplier' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/SupplierInput'],
                            ['type' => 'object', 'required' => ['id', 'createdAt'], 'properties' => [
                                'id' => ['type' => 'integer', 'example' => 12],
                                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                            ]],
                        ],
                    ],
                    'Error' => ['type' => 'object', 'required' => ['error'], 'properties' => ['error' => ['type' => 'string']]],
                ],
                'responses' => [
                    'BadRequest' => ['description' => 'Invalid request payload', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    'ValidationError' => ['description' => 'Supplier data failed validation', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    'NotFound' => ['description' => 'Resource not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    'Conflict' => ['description' => 'Email already exists or supplier is referenced by another record', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                ],
            ],
        ]);
    }

    #[Route('/api/docs', name: 'api_docs_ui', methods: ['GET'])]
    public function ui(): Response
    {
        return new Response(<<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ERP Procurement API - Swagger UI</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
  <style>body{margin:0;background:#fafafa}.topbar{display:none}</style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
  <script>window.onload=()=>SwaggerUIBundle({url:'/api/docs.json',dom_id:'#swagger-ui',deepLinking:true,persistAuthorization:true});</script>
</body>
</html>
HTML, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
