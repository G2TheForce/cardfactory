<?php

namespace App\Http\Controllers\LandingPages;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Services\LandingPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/** /p/{slug}: published landing pages and their enquiry forms. No login needed. */
class PublicLandingPageController extends Controller
{
    public function __construct(private readonly LandingPageService $pages) {}

    public function show(Request $request, string $slug): BaseResponse
    {
        $page = $this->find($slug);
        if (! $page) {
            return $this->unavailable();
        }

        // Staff and the owner looking at their own page aren't counted.
        $user = $request->user();
        if (! $user || ! $user->can('view', $page)) {
            $page->increment('views_count');
        }

        return response()->view('landing-pages.public', [
            'page' => $page,
            'p' => $this->pages->publicData($page),
            'urls' => ['public' => $this->pages->publicUrl($page), 'contact' => route('landing-pages.contact', $page->slug)],
            'preview' => false,
            'export' => null,
        ])->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function contact(Request $request, string $slug): BaseResponse
    {
        $page = $this->find($slug);
        if (! $page) {
            return $this->unavailable();
        }
        $data = $request->validateWithBag('lead', [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40', 'regex:/^[0-9+() .-]{5,40}$/'],
            'message' => ['nullable', 'string', 'max:3000'],
            'company_website' => ['prohibited'], // honeypot field, must stay empty
        ], [
            'email.required_without' => 'Leave an email or a phone number so we can reply.',
            'phone.required_without' => 'Leave an email or a phone number so we can reply.',
            'phone.regex' => 'Enter a phone number using digits, spaces and + ( ) -.',
        ]);

        $this->pages->storeLead($page, $data);

        return $this->backToForm($page)->with('lead_sent', 'Thanks! Your message has been sent. We\'ll be in touch soon.');
    }

    private function find(string $slug): ?LandingPage
    {
        $page = LandingPage::where('slug', strtolower($slug))->with('customer.company')->first();

        return $page && $this->pages->isAvailable($page) ? $page : null;
    }

    private function backToForm(LandingPage $page): RedirectResponse
    {
        return redirect()->to(route('landing-pages.public', $page->slug).'#contact');
    }

    private function unavailable(): Response
    {
        return response()->view('landing-pages.unavailable', [], 404);
    }
}
