<?php

declare(strict_types=1);

use App\Exceptions\ApiErrorRenderer;
use App\Http\Middleware\AssignCorrelationId;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;

test('a validation error carries correlationId and code alongside the native message/errors shape', function () {
    $response = $this->postJson('/login', ['email' => 'not-an-email'])
        ->assertUnprocessable();

    $response->assertJsonStructure(['correlationId', 'code', 'message', 'errors']);
    $response->assertJsonPath('code', 'validation_failed');
    expect($response->headers->get(AssignCorrelationId::HEADER))->toBe($response->json('correlationId'));
});

test('an unauthenticated response carries correlationId and code', function () {
    $response = $this->getJson('/api/ping')->assertUnauthorized();

    $response->assertJsonPath('code', 'unauthenticated');
    expect($response->json('correlationId'))->not->toBeEmpty();
    expect($response->headers->get(AssignCorrelationId::HEADER))->toBe($response->json('correlationId'));
});

test('a forbidden (cross-user) response carries correlationId and code', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $listing = Listing::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)
        ->patchJson("/api/listings/{$listing->id}/status", ['status' => 'applied'])
        ->assertForbidden();

    $response->assertJsonPath('code', 'forbidden');
    expect($response->json('correlationId'))->not->toBeEmpty();
});

test('a not-found response (unknown route-bound model) carries correlationId and code', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->putJson('/api/admin/sites/999999', ['name' => 'Does not exist'])
        ->assertNotFound();

    $response->assertJsonPath('code', 'not_found');
    expect($response->json('correlationId'))->not->toBeEmpty();
});

test('an inbound X-Correlation-Id header is trusted and echoed back', function () {
    $response = $this->withHeaders([AssignCorrelationId::HEADER => 'test-correlation-123'])
        ->getJson('/api/ping');

    expect($response->headers->get(AssignCorrelationId::HEADER))->toBe('test-correlation-123');
});

test('ApiErrorRenderer maps a generic throwable to a sanitized 500 envelope', function () {
    $request = Request::create('/api/whatever', 'GET');
    $request->headers->set('Accept', 'application/json');
    $request->attributes->set(AssignCorrelationId::ATTRIBUTE, 'fixed-correlation-id');

    $response = ApiErrorRenderer::render(new RuntimeException('some internal detail'), $request);

    expect($response)->not->toBeNull();
    expect($response->getStatusCode())->toBe(500);
    $body = json_decode($response->getContent(), true);
    expect($body['code'])->toBe('server_error');
    expect($body['correlationId'])->toBe('fixed-correlation-id');
    expect($body['message'])->not->toContain('some internal detail');
});
