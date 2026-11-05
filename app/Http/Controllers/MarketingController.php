<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\CardTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function home(): View
    {
        return view('marketing.home', [
            'themes' => CardTheme::published()->orderBy('sort_order')->limit(8)->get(),
            'plans' => config('smartcard.plans'),
        ]);
    }

    public function legal(string $page): View
    {
        abort_unless(in_array($page, ['terms', 'privacy'], true), 404);

        return view('marketing.legal', ['page' => $page]);
    }

    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['prohibited'], // honeypot field, must stay empty
        ]);

        Mail::to(config('smartcard.contact_email'))->send(new ContactMessage($data));

        return redirect()->to(route('home').'#contact')->with('contact_sent', 'Thanks! We will get back to you within one business day.');
    }
}
