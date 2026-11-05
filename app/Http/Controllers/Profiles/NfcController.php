<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Models\DigitalProfile;
use App\Models\NfcCard;
use App\Services\NFCManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Staff (nfc.manage) link physical NFC cards to a profile. */
class NfcController extends Controller
{
    public function __construct(private readonly NFCManagementService $nfc) {}

    public function store(Request $request, DigitalProfile $profile): RedirectResponse
    {
        $this->authorize('manageNfc', $profile);
        $data = $request->validateWithBag('nfc', [
            'identifier' => ['required', 'string', 'max:32'],
            'uid' => ['nullable', 'string', 'max:40'],
            'tag_type' => ['nullable', Rule::in(NFCManagementService::TAG_TYPES)],
            'move' => ['nullable', 'boolean'],
        ]);

        $assignment = $this->withBag('nfc', fn () => $this->nfc->assign($profile, $data, $request->user()));

        return $this->back($profile, $assignment->status === 'active'
            ? 'Card '.$assignment->nfcCard->card_identifier.' now opens this profile. It was already encoded, so nothing needs rewriting.'
            : 'Card '.$assignment->nfcCard->card_identifier.' assigned. Write its URL to the tag, then mark it as encoded.');
    }

    public function encoded(Request $request, DigitalProfile $profile, NfcCard $card): RedirectResponse
    {
        $this->authorize('manageNfc', $profile);
        $data = $request->validateWithBag('nfc', ['uid' => ['nullable', 'string', 'max:40']]);
        $this->withBag('nfc', fn () => $this->nfc->markEncoded($profile, $card, $request->user(), $data['uid'] ?? null));

        return $this->back($profile, 'Card '.$card->card_identifier.' is active.');
    }

    public function replace(Request $request, DigitalProfile $profile, NfcCard $card): RedirectResponse
    {
        $this->authorize('manageNfc', $profile);
        $data = $request->validateWithBag('replace', [
            'identifier' => ['required', 'string', 'max:32'],
            'uid' => ['nullable', 'string', 'max:40'],
            'reason' => ['required', Rule::in(['lost', 'damaged'])],
        ]);
        $new = $this->withBag('replace', fn () => $this->nfc->replace($profile, $card, $data, $request->user()));

        return $this->back($profile, 'Card '.$card->card_identifier.' no longer opens this profile. Write the URL to '.$new->nfcCard->card_identifier.' and mark it as encoded.');
    }

    public function destroy(Request $request, DigitalProfile $profile, NfcCard $card): RedirectResponse
    {
        $this->authorize('manageNfc', $profile);
        $this->withBag('nfc', fn () => $this->nfc->unassign($profile, $card, $request->user()));

        return $this->back($profile, 'Card '.$card->card_identifier.' unassigned and returned to stock.');
    }

    private function back(DigitalProfile $profile, string $message): RedirectResponse
    {
        return redirect()->to(route('profiles.show', $profile).'#nfc')->with('success', $message);
    }

    /** Run a service call, reporting validation errors in the given error bag. */
    private function withBag(string $bag, callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e->errorBag($bag)->redirectTo(route('profiles.show', request()->route('profile')).'#nfc');
        }
    }
}
