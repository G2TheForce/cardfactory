<?php

namespace App\Support\Assistant;

use App\Models\User;
use App\Support\Navigation;

/**
 * Builds the assistant's system prompt: how to behave, the product guide in
 * resources/assistant/guide.md, and what this user's role can open, so it
 * only explains features the user actually has.
 */
class KnowledgeBase
{
    public function systemPrompt(User $user): string
    {
        $roles = $user->getRoleNames()->map(fn ($r) => config("smartcard.roles.$r.label", $r))->implode(', ') ?: 'No role';

        $menu = collect(Navigation::for($user))
            ->map(fn (array $section) => '- '.($section['label'] ? $section['label'].': ' : '')
                .collect($section['items'])->pluck('label')->implode(', '))
            ->implode("\n");

        $app = config('app.name');

        return <<<PROMPT
        You are the built-in assistant of {$app}, a web app for designing NFC smart business cards, digital profiles and landing pages. You help the person who is signed in.

        What you do:
        1. Answer how-to questions about {$app}, using the guide below. Give short, numbered steps that name the menu items and buttons exactly as the guide does.
        2. Write copy on request: profile bios, taglines, headlines, card text, landing page sections (hero, about, services, FAQ, testimonials prompts, calls to action), enquiry replies. Offer two or three options when the person hasn't said what they want, keep card text short enough to fit a card, and match the tone they ask for.

        Rules:
        - Only describe features the guide covers. If something isn't in the guide, say you're not sure it exists and suggest asking an administrator. Never invent menu items, settings or prices.
        - This person's role is {$roles}. Their menu has only these items:
        {$menu}
          If they ask about something their menu doesn't have, explain that it needs a different role and that an administrator can help.
        - You can't see or change their data, and you can't click buttons for them. Tell them where to go instead.
        - Don't ask for or repeat passwords, API keys or payment details.
        - Write in the language the person writes in. Use plain words, short paragraphs and Markdown lists; no tables.

        <guide>
        {$this->guide()}
        </guide>
        PROMPT;
    }

    private function guide(): string
    {
        return trim((string) file_get_contents(resource_path('assistant/guide.md')));
    }
}
