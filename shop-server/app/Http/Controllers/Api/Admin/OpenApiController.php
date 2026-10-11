<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OpenApiController extends Controller
{
    public function spec(Request $request): JsonResponse
    {
        return response()->json($this->document($request));
    }

    public function ui(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>mshop API — Swagger</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    body { margin: 0; background: #0b1220; }
    .top { padding: 12px 20px; color: #e2e8f0; font-family: system-ui, sans-serif; background: #111827; }
    .top a { color: #5eead4; }
  </style>
</head>
<body>
  <div class="top">mshop API documentation · <a href="/api/admin/openapi.json">openapi.json</a></div>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: '/api/admin/openapi.json',
      dom_id: '#swagger-ui',
      deepLinking: true,
      persistAuthorization: true,
    });
  </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function document(Request $request): array
    {
        $base = rtrim($request->getSchemeAndHttpHost(), '/');

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'mshop API',
                'version' => '1.0.0',
                'description' => 'Myanmar e-commerce API. Sanctum bearer token auth. Admin-only docs.',
            ],
            'servers' => [
                ['url' => $base.'/api', 'description' => 'Current host'],
            ],
            'components' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum',
                    ],
                ],
            ],
            'security' => [['sanctum' => []]],
            'tags' => [
                ['name' => 'Auth'],
                ['name' => 'Catalog'],
                ['name' => 'Cart & Checkout'],
                ['name' => 'Admin'],
                ['name' => 'Messaging'],
                ['name' => 'Banners'],
            ],
            'paths' => [
                '/auth/register' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'security' => [],
                        'summary' => 'Register customer',
                        'requestBody' => $this->jsonBody([
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'password' => ['type' => 'string'],
                            'password_confirmation' => ['type' => 'string'],
                        ], ['name', 'email', 'password', 'password_confirmation']),
                        'responses' => ['201' => ['description' => 'Created']],
                    ],
                ],
                '/auth/login' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'security' => [],
                        'summary' => 'Login',
                        'requestBody' => $this->jsonBody([
                            'email' => ['type' => 'string'],
                            'password' => ['type' => 'string'],
                            'device_name' => ['type' => 'string'],
                        ], ['email', 'password']),
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/auth/me' => [
                    'get' => [
                        'tags' => ['Auth'],
                        'summary' => 'Current user',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/catalog/categories' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'security' => [],
                        'summary' => 'List categories',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/catalog/products' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'security' => [],
                        'summary' => 'List products',
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'category_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/catalog/banners' => [
                    'get' => [
                        'tags' => ['Banners'],
                        'security' => [],
                        'summary' => 'Active scheduled banners',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/cart' => [
                    'get' => [
                        'tags' => ['Cart & Checkout'],
                        'summary' => 'Get cart',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                    'patch' => [
                        'tags' => ['Cart & Checkout'],
                        'summary' => 'Update cart items',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/orders' => [
                    'post' => [
                        'tags' => ['Cart & Checkout'],
                        'summary' => 'Place order',
                        'responses' => ['201' => ['description' => 'Created']],
                    ],
                    'get' => [
                        'tags' => ['Cart & Checkout'],
                        'summary' => 'My orders',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/admin/settings' => [
                    'get' => [
                        'tags' => ['Admin'],
                        'summary' => 'List settings groups',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/admin/banners' => [
                    'get' => [
                        'tags' => ['Banners'],
                        'summary' => 'Admin list banners',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                    'post' => [
                        'tags' => ['Banners'],
                        'summary' => 'Create banner with schedule',
                        'responses' => ['201' => ['description' => 'Created']],
                    ],
                ],
                '/admin/message-templates' => [
                    'get' => [
                        'tags' => ['Messaging'],
                        'summary' => 'List message templates',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/admin/openapi.json' => [
                    'get' => [
                        'tags' => ['Admin'],
                        'summary' => 'OpenAPI document (admin only)',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function jsonBody(array $properties, array $required = []): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => $required,
                        'properties' => $properties,
                    ],
                ],
            ],
        ];
    }
}
