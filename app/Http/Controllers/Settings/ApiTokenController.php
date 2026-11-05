<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Personal access tokens (Laravel Sanctum) for the REST API.
 */
class ApiTokenController extends Controller
{
    public const ABILITIES = [
        'read' => 'Read your cards, profiles and analytics',
        'write' => 'Create and update cards and profiles',
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        return view('settings.tokens', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'abilities' => self::ABILITIES,
            'plainTextToken' => session('plainTextToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:'.implode(',', array_keys(self::ABILITIES))],
            'expires_in_days' => ['nullable', 'integer', 'in:30,90,365'],
        ]);

        $token = $request->user()->createToken(
            $data['name'],
            $data['abilities'],
            isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
        );

        $this->logger->log('api-token.created', $token->accessToken, "Created API token \"{$data['name']}\"", ['abilities' => $data['abilities']]);

        return redirect()->route('settings.tokens')
            ->with('plainTextToken', $token->plainTextToken)
            ->with('success', 'Token created. Copy it now: it will not be shown again.');
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->firstOrFail();
        $token->delete();

        $this->logger->log('api-token.revoked', null, "Revoked API token \"{$token->name}\"");

        return redirect()->route('settings.tokens')->with('success', 'Token revoked.');
    }
}
