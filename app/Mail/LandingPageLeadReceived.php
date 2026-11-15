<?php

namespace App\Mail;

use App\Models\LandingPageLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the page owner about a new enquiry from their landing page. */
class LandingPageLeadReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public LandingPageLead $lead, public string $pageName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->lead->email ? [new Address($this->lead->email, $this->lead->name)] : [],
            subject: 'New enquiry from '.$this->lead->name.' via '.$this->pageName,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.landing-page-lead');
    }
}
