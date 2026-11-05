<?php

namespace App\Services;

use App\Models\DigitalProfile;
use App\Models\NfcAssignment;
use App\Models\NfcCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links physical NFC cards to digital profiles.
 *
 * The app never writes tags itself: a browser cannot reliably program NFC
 * hardware. Each card is encoded once (with a phone app, a USB writer or Web
 * NFC on Android Chrome) with its own permanent URL, /n/{card identifier}.
 * That URL is resolved here on every tap, so reassigning a card to another
 * profile needs no re-encoding, and a lost card can be switched off.
 */
class NFCManagementService
{
    public const TAG_TYPES = ['NTAG213', 'NTAG215', 'NTAG216', 'MIFARE', 'OTHER'];

    /** Card statuses that can't take a new assignment. */
    private const UNUSABLE = ['lost', 'defective', 'retired', 'replaced'];

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Assign a card (existing or new) to a profile. A card that is already
     * linked elsewhere is only moved when $data['move'] is true.
     *
     * @param  array{identifier: string, uid?: ?string, tag_type?: ?string, move?: bool}  $data
     *
     * @throws ValidationException
     */
    public function assign(DigitalProfile $profile, array $data, User $actor, ?NfcAssignment $replaces = null): NfcAssignment
    {
        $identifier = $this->normaliseIdentifier($data['identifier']);

        return DB::transaction(function () use ($profile, $data, $actor, $identifier, $replaces) {
            $card = NfcCard::where('card_identifier', $identifier)->lockForUpdate()->first();
            if ($card && in_array($card->status, self::UNUSABLE, true)) {
                throw ValidationException::withMessages(['identifier' => "Card {$identifier} is marked as {$card->status} and can't be assigned."]);
            }

            $card ??= NfcCard::create([
                'card_identifier' => $identifier,
                'tag_type' => in_array($data['tag_type'] ?? null, self::TAG_TYPES, true) ? $data['tag_type'] : 'NTAG215',
                'status' => 'in_stock',
            ]);

            $open = $this->openAssignment($card);
            if ($open && (int) $open->digital_profile_id === (int) $profile->id) {
                throw ValidationException::withMessages(['identifier' => "Card {$identifier} is already linked to this profile."]);
            }
            if ($open) {
                if (empty($data['move'])) {
                    $who = $open->customer?->full_name ?? 'another customer';
                    throw ValidationException::withMessages(['identifier' => "Card {$identifier} is linked to {$who}. Tick \"Move it here\" to reassign it."]);
                }
                $this->end($open, 'inactive', 'Reassigned to /card/'.$profile->slug);
            }

            if (! empty($data['uid'])) {
                $this->setUid($card, $data['uid']);
            }

            // A card that was already encoded keeps working: its URL never changes.
            $encoded = $card->encoded_at !== null;
            $assignment = NfcAssignment::create([
                'nfc_card_id' => $card->id,
                'customer_id' => $profile->customer_id,
                'digital_profile_id' => $profile->id,
                'assigned_by' => $actor->id,
                'replaces_assignment_id' => $replaces?->id,
                'status' => $encoded ? 'active' : 'assigned',
                'profile_url' => $this->tagUrl($card),
                'reason' => $open ? 'Reassigned from '.($open->customer?->full_name ?? 'another profile') : null,
                'assigned_at' => now(),
                'activated_at' => $encoded ? now() : null,
            ]);
            $card->status = $encoded ? 'active' : 'assigned';
            $card->save();

            $this->logger->log($open ? 'nfc.reassigned' : 'nfc.assigned', $profile,
                ($open ? 'Moved' : 'Assigned').' NFC card '.$card->card_identifier.' to /card/'.$profile->slug, ['card' => $card->card_identifier], $actor->id);

            return $assignment;
        });
    }

    /**
     * Record that the tag has been written (and tested). Activates the assignment.
     *
     * @throws ValidationException
     */
    public function markEncoded(DigitalProfile $profile, NfcCard $card, User $actor, ?string $uid = null): NfcAssignment
    {
        return DB::transaction(function () use ($profile, $card, $actor, $uid) {
            $assignment = $this->assignmentFor($profile, $card);
            if ($uid) {
                $this->setUid($card, $uid);
            }
            $card->forceFill(['encoded_at' => $card->encoded_at ?? now(), 'status' => 'active'])->save();
            $this->activate($assignment);
            $this->logger->log('nfc.encoded', $profile, 'Marked NFC card '.$card->card_identifier.' as encoded', ['card' => $card->card_identifier], $actor->id);

            return $assignment;
        });
    }

    /**
     * Replace a lost or damaged card with a new one. The old card stops
     * opening the profile immediately.
     *
     * @param  array{identifier: string, uid?: ?string, tag_type?: ?string, reason: string}  $data
     *
     * @throws ValidationException
     */
    public function replace(DigitalProfile $profile, NfcCard $old, array $data, User $actor): NfcAssignment
    {
        return DB::transaction(function () use ($profile, $old, $data, $actor) {
            $current = $this->assignmentFor($profile, $old);
            if ($this->normaliseIdentifier($data['identifier']) === $old->card_identifier) {
                throw ValidationException::withMessages(['identifier' => 'Enter the identifier of the new card.']);
            }
            $lost = ($data['reason'] ?? 'lost') === 'lost';
            $this->end($current, 'replaced', $lost ? 'Card lost' : 'Card damaged');
            $old->update(['status' => $lost ? 'lost' : 'defective']);

            $new = $this->assign($profile, ['identifier' => $data['identifier'], 'uid' => $data['uid'] ?? null, 'tag_type' => $data['tag_type'] ?? $old->tag_type], $actor, $current);
            $this->logger->log('nfc.replaced', $profile, 'Replaced '.($lost ? 'lost' : 'damaged').' NFC card '.$old->card_identifier.' with '.$new->nfcCard->card_identifier, [], $actor->id);

            return $new;
        });
    }

    /** Unlink a card from the profile. The card goes back into stock and can be reassigned later. */
    public function unassign(DigitalProfile $profile, NfcCard $card, User $actor): void
    {
        DB::transaction(function () use ($profile, $card, $actor) {
            $this->end($this->assignmentFor($profile, $card), 'revoked', 'Unassigned');
            $card->update(['status' => 'in_stock']);
            $this->logger->log('nfc.unassigned', $profile, 'Unassigned NFC card '.$card->card_identifier.' from /card/'.$profile->slug, [], $actor->id);
        });
    }

    /** Unlink every card from a profile (used when the profile is deleted). */
    public function releaseAll(DigitalProfile $profile, User $actor, string $reason): void
    {
        NfcAssignment::where('digital_profile_id', $profile->id)->whereNull('ended_at')->with('nfcCard')->get()
            ->each(function (NfcAssignment $a) use ($reason) {
                $this->end($a, 'revoked', $reason);
                $a->nfcCard?->update(['status' => 'in_stock']);
            });
    }

    /**
     * Resolve a tap on /n/{identifier}. The first tap of an assigned card
     * activates it.
     *
     * @return array{card: NfcCard, assignment: NfcAssignment}|null
     */
    public function resolveTap(string $identifier): ?array
    {
        $card = NfcCard::where('card_identifier', $this->normaliseIdentifier($identifier, false))->first();
        if (! $card || in_array($card->status, self::UNUSABLE, true)) {
            return null;
        }
        $assignment = $this->openAssignment($card);
        if (! $assignment || ! $assignment->digitalProfile) {
            return null;
        }
        if ($assignment->status === 'assigned') {
            $card->forceFill(['encoded_at' => $card->encoded_at ?? now(), 'status' => 'active'])->save();
            $this->activate($assignment);
        }

        return ['card' => $card, 'assignment' => $assignment];
    }

    /** Permanent URL encoded onto a physical card. */
    public function tagUrl(NfcCard $card): string
    {
        return route('nfc.tap', $card->card_identifier);
    }

    /** Open assignments for a profile, newest first, with their cards. */
    public function cardsFor(DigitalProfile $profile)
    {
        return NfcAssignment::where('digital_profile_id', $profile->id)->whereNull('ended_at')
            ->with('nfcCard')->latest('assigned_at')->get();
    }

    /** Ended assignments (history) for a profile. */
    public function historyFor(DigitalProfile $profile, int $limit = 10)
    {
        return NfcAssignment::where('digital_profile_id', $profile->id)->whereNotNull('ended_at')
            ->with('nfcCard')->latest('ended_at')->limit($limit)->get();
    }

    /** @throws ValidationException */
    public function normaliseUid(string $uid): string
    {
        $hex = strtoupper(preg_replace('/[^0-9a-f]/i', '', $uid));
        if (! in_array(strlen($hex), [8, 14, 20], true)) {
            throw ValidationException::withMessages(['uid' => 'A tag UID is 4, 7 or 10 bytes of hex, e.g. 04:A2:3B:1C:5D:80:00.']);
        }

        return implode(':', str_split($hex, 2));
    }

    /** @throws ValidationException */
    public function normaliseIdentifier(string $identifier, bool $strict = true): string
    {
        $id = strtoupper(trim($identifier));
        if ($strict && ! preg_match('/^[A-Z0-9][A-Z0-9-]{3,31}$/', $id)) {
            throw ValidationException::withMessages(['identifier' => 'Card identifiers use 4–32 letters, digits and dashes, e.g. SC-7K2M9Q.']);
        }

        return $id;
    }

    private function openAssignment(NfcCard $card): ?NfcAssignment
    {
        return NfcAssignment::where('nfc_card_id', $card->id)->whereNull('ended_at')->with(['customer', 'digitalProfile'])->latest('assigned_at')->first();
    }

    /** @throws ValidationException */
    private function assignmentFor(DigitalProfile $profile, NfcCard $card): NfcAssignment
    {
        $assignment = $this->openAssignment($card);
        if (! $assignment || (int) $assignment->digital_profile_id !== (int) $profile->id) {
            throw ValidationException::withMessages(['card' => 'This card is not linked to this profile.']);
        }

        return $assignment;
    }

    private function activate(NfcAssignment $assignment): void
    {
        if ($assignment->status !== 'active') {
            $assignment->update(['status' => 'active', 'activated_at' => $assignment->activated_at ?? now()]);
        }
    }

    private function end(NfcAssignment $assignment, string $status, string $reason): void
    {
        $assignment->update(['status' => $status, 'reason' => $reason, 'ended_at' => now()]);
    }

    /** @throws ValidationException */
    private function setUid(NfcCard $card, string $uid): void
    {
        $uid = $this->normaliseUid($uid);
        if (NfcCard::withTrashed()->where('uid', $uid)->whereKeyNot($card->getKey())->exists()) {
            throw ValidationException::withMessages(['uid' => 'Another card already has this UID.']);
        }
        $card->uid = $uid;
        $card->save();
    }
}
