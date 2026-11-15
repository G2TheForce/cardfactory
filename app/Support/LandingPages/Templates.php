<?php

namespace App\Support\LandingPages;

/**
 * Starter templates for new landing pages: a style plus a list of sections
 * with ready-to-edit copy. {company}, {name}, {email}, {phone}, {whatsapp}
 * and {address} are filled in from the customer when the page is created.
 */
class Templates
{
    public const TEMPLATES = [
        'cafe' => [
            'name' => 'Café & restaurant',
            'description' => 'Warm, food-first page with menu highlights, gallery and opening hours.',
            'style' => ['palette' => 'terracotta', 'font' => 'editorial', 'buttons' => 'pill'],
            'sections' => [
                ['hero', ['variant' => 'split', 'eyebrow' => 'Freshly made every day', 'heading' => 'Good food, slow mornings at {company}', 'subheading' => 'Seasonal plates, single-origin coffee and pastries baked before sunrise. Drop in, or book a table for the weekend.', 'button_label' => 'Book a table', 'button_url' => '#contact', 'secondary_label' => 'See the menu', 'secondary_url' => '#services']],
                ['services', ['eyebrow' => 'Menu highlights', 'heading' => 'Made in our kitchen', 'columns' => '3', 'items' => [
                    ['icon' => 'coffee', 'title' => 'Coffee bar', 'text' => 'Espresso, pour-over and cold brew from local roasters.', 'price' => 'from $3'],
                    ['icon' => 'cake-slice', 'title' => 'Bakery', 'text' => 'Croissants, sourdough and cakes, baked fresh each morning.', 'price' => 'from $4'],
                    ['icon' => 'utensils', 'title' => 'All-day brunch', 'text' => 'Eggs your way, grain bowls and seasonal specials.', 'price' => 'from $9'],
                ]]],
                ['gallery', ['eyebrow' => 'Inside {company}', 'heading' => 'A look around', 'columns' => '3']],
                ['testimonials', ['heading' => 'Our regulars say it best', 'items' => [
                    ['quote' => 'The best croissant in town, and the staff remember your order.', 'name' => 'Priya K.', 'role' => 'Regular since 2021', 'rating' => '5'],
                    ['quote' => 'Our favourite weekend brunch spot. Book ahead!', 'name' => 'Daniel & Mia', 'role' => 'Local family', 'rating' => '5'],
                ]]],
                ['contact', ['eyebrow' => 'Visit us', 'heading' => 'Find {company}', 'text' => 'Walk-ins welcome. For groups of six or more, send us a message.', 'email' => '{email}', 'phone' => '{phone}', 'whatsapp' => '{whatsapp}', 'address' => '{address}', 'hours' => "Mon–Fri 7:00–18:00\nSat–Sun 8:00–16:00", 'show_form' => true, 'show_map' => true]],
            ],
        ],
        'real-estate' => [
            'name' => 'Real estate',
            'description' => 'Polished agency page with listings, track record and a valuation call to action.',
            'style' => ['palette' => 'midnight', 'font' => 'elegant', 'buttons' => 'square'],
            'sections' => [
                ['hero', ['variant' => 'cover', 'eyebrow' => '{company}', 'heading' => 'Find the place you will call home', 'subheading' => 'Local experts in buying, selling and renting. Honest advice, sharp marketing and a team that answers the phone.', 'button_label' => 'Book a free valuation', 'button_url' => '#contact', 'secondary_label' => 'Our services', 'secondary_url' => '#services']],
                ['stats', ['items' => [['value' => '350+', 'label' => 'Homes sold'], ['value' => '98%', 'label' => 'Asking price achieved'], ['value' => '21', 'label' => 'Days to sell, on average'], ['value' => '15', 'label' => 'Years in the area']]]],
                ['services', ['eyebrow' => 'What we do', 'heading' => 'Every step, handled', 'columns' => '3', 'items' => [
                    ['icon' => 'house', 'title' => 'Selling', 'text' => 'Pricing, staging, photography and negotiation to get you the best result.'],
                    ['icon' => 'key-round', 'title' => 'Buying', 'text' => 'Early access to listings and a buyer agent on your side.'],
                    ['icon' => 'building-2', 'title' => 'Rentals', 'text' => 'Tenant screening, contracts and full property management.'],
                ]]],
                ['gallery', ['eyebrow' => 'Featured listings', 'heading' => 'On the market now', 'columns' => '3']],
                ['testimonials', ['heading' => 'Clients who moved with us', 'items' => [
                    ['quote' => 'Sold in under two weeks, above asking. Couldn’t have asked for more.', 'name' => 'Sarah L.', 'role' => 'Seller', 'rating' => '5'],
                ]]],
                ['cta', ['heading' => 'What is your home worth?', 'text' => 'Get a free, no-obligation valuation from a local agent within 48 hours.', 'button_label' => 'Request a valuation', 'button_url' => '#contact']],
                ['contact', ['heading' => 'Talk to {company}', 'email' => '{email}', 'phone' => '{phone}', 'whatsapp' => '{whatsapp}', 'address' => '{address}', 'show_form' => true]],
            ],
        ],
        'consultant' => [
            'name' => 'Consultant & agency',
            'description' => 'Clean B2B page with services, results, packages and FAQs.',
            'style' => ['palette' => 'ocean', 'font' => 'modern', 'buttons' => 'rounded'],
            'sections' => [
                ['hero', ['variant' => 'centered', 'eyebrow' => 'Strategy · Growth · Operations', 'heading' => 'Clear plans. Measurable growth.', 'subheading' => '{company} helps growing businesses fix what slows them down and double down on what works.', 'button_label' => 'Book a free call', 'button_url' => '#contact', 'secondary_label' => 'How we work', 'secondary_url' => '#services']],
                ['about', ['eyebrow' => 'About {company}', 'heading' => 'Senior advice, without the overhead', 'body' => "We are a small team of operators who have built and scaled companies ourselves.\n\nNo 80-page decks: we work alongside your team, agree on the numbers that matter and stay until they move."]],
                ['services', ['eyebrow' => 'Services', 'heading' => 'Where we help', 'columns' => '3', 'items' => [
                    ['icon' => 'chart-no-axes-column', 'title' => 'Growth strategy', 'text' => 'Market sizing, positioning and a 12-month plan your team can run.'],
                    ['icon' => 'zap', 'title' => 'Operations', 'text' => 'Processes, tools and dashboards that save hours every week.'],
                    ['icon' => 'users', 'title' => 'Leadership coaching', 'text' => 'One-to-one coaching for founders and new managers.'],
                ]]],
                ['pricing', ['eyebrow' => 'Packages', 'heading' => 'Simple, fixed pricing', 'items' => [
                    ['name' => 'Audit', 'price' => '$1,500', 'period' => 'one-off', 'features' => "Two-week review\nFindings report\nAction plan", 'button_label' => 'Get started', 'button_url' => '#contact'],
                    ['name' => 'Partner', 'price' => '$4,000', 'period' => '/ month', 'features' => "Weekly sessions\nHands-on support\nMonthly reporting\nCancel any time", 'button_label' => 'Book a call', 'button_url' => '#contact', 'featured' => true],
                ]]],
                ['faq', ['heading' => 'Questions, answered', 'items' => [
                    ['question' => 'How quickly can we start?', 'answer' => 'Usually within two weeks of our first call.'],
                    ['question' => 'Do you work remotely?', 'answer' => 'Yes. We work with clients everywhere and visit on site when it helps.'],
                ]]],
                ['contact', ['heading' => 'Let’s talk', 'text' => 'Tell us a little about your business and we’ll get back within one working day.', 'email' => '{email}', 'phone' => '{phone}', 'show_form' => true]],
            ],
        ],
        'salon' => [
            'name' => 'Salon & spa',
            'description' => 'Soft, elegant page with treatments, prices, gallery and booking.',
            'style' => ['palette' => 'rose', 'font' => 'elegant', 'buttons' => 'pill'],
            'sections' => [
                ['hero', ['variant' => 'split', 'eyebrow' => 'Hair · Skin · Wellbeing', 'heading' => 'Take an hour for yourself', 'subheading' => 'Treatments by experienced stylists and therapists in a calm, beautiful space at {company}.', 'button_label' => 'Book an appointment', 'button_url' => '#contact', 'secondary_label' => 'Treatments & prices', 'secondary_url' => '#services']],
                ['services', ['eyebrow' => 'Treatments', 'heading' => 'Our menu', 'columns' => '2', 'items' => [
                    ['icon' => 'scissors', 'title' => 'Cut & style', 'text' => 'Consultation, wash, cut and finish.', 'price' => '$45'],
                    ['icon' => 'sparkles', 'title' => 'Colour', 'text' => 'Balayage, highlights and full colour.', 'price' => 'from $80'],
                    ['icon' => 'leaf', 'title' => 'Facials', 'text' => 'Deep cleansing and hydrating facials for every skin type.', 'price' => '$60'],
                    ['icon' => 'heart', 'title' => 'Massage', 'text' => 'Relaxing or deep-tissue, 60 or 90 minutes.', 'price' => 'from $70'],
                ]]],
                ['gallery', ['eyebrow' => 'Our work', 'heading' => 'Fresh from the chair', 'columns' => '4']],
                ['testimonials', ['heading' => 'Kind words', 'items' => [
                    ['quote' => 'I leave feeling like a new person every time.', 'name' => 'Anisha R.', 'role' => 'Client', 'rating' => '5'],
                ]]],
                ['contact', ['eyebrow' => 'Book', 'heading' => 'Reserve your visit', 'email' => '{email}', 'phone' => '{phone}', 'whatsapp' => '{whatsapp}', 'address' => '{address}', 'hours' => "Tue–Sat 10:00–19:00\nSun–Mon closed", 'show_form' => true]],
            ],
        ],
        'fitness' => [
            'name' => 'Fitness studio',
            'description' => 'Energetic dark page with classes, results, memberships and a trial offer.',
            'style' => ['palette' => 'aurora', 'font' => 'friendly', 'buttons' => 'pill'],
            'sections' => [
                ['hero', ['variant' => 'cover', 'eyebrow' => '{company}', 'heading' => 'Stronger every week', 'subheading' => 'Small-group classes, expert coaches and a community that keeps you coming back.', 'button_label' => 'Claim a free class', 'button_url' => '#contact', 'secondary_label' => 'Memberships', 'secondary_url' => '#pricing']],
                ['stats', ['items' => [['value' => '40+', 'label' => 'Classes a week'], ['value' => '12', 'label' => 'Certified coaches'], ['value' => '1,200', 'label' => 'Members']]]],
                ['services', ['eyebrow' => 'Classes', 'heading' => 'Find your session', 'columns' => '3', 'items' => [
                    ['icon' => 'dumbbell', 'title' => 'Strength', 'text' => 'Coached lifting for every level.'],
                    ['icon' => 'zap', 'title' => 'HIIT', 'text' => '45 minutes, maximum effort, great music.'],
                    ['icon' => 'leaf', 'title' => 'Mobility & yoga', 'text' => 'Recover better and move freely.'],
                ]]],
                ['pricing', ['eyebrow' => 'Memberships', 'heading' => 'Train your way', 'items' => [
                    ['name' => 'Drop-in', 'price' => '$15', 'period' => '/ class', 'features' => "Any class\nNo commitment", 'button_label' => 'Book a class', 'button_url' => '#contact'],
                    ['name' => 'Unlimited', 'price' => '$89', 'period' => '/ month', 'features' => "Unlimited classes\nFree induction\nMember events", 'button_label' => 'Join now', 'button_url' => '#contact', 'featured' => true],
                    ['name' => 'Personal training', 'price' => '$55', 'period' => '/ session', 'features' => "One-to-one coaching\nProgram + nutrition", 'button_label' => 'Enquire', 'button_url' => '#contact'],
                ]]],
                ['cta', ['heading' => 'Your first class is on us', 'text' => 'Come and try a session, meet the coaches and see if it’s for you.', 'button_label' => 'Claim free class', 'button_url' => '#contact']],
                ['contact', ['heading' => 'Get started', 'email' => '{email}', 'phone' => '{phone}', 'whatsapp' => '{whatsapp}', 'address' => '{address}', 'show_form' => true]],
            ],
        ],
        'launch' => [
            'name' => 'Product launch',
            'description' => 'Bold page to announce a product or event, with features, pricing and FAQs.',
            'style' => ['palette' => 'gold', 'font' => 'modern', 'buttons' => 'rounded'],
            'sections' => [
                ['hero', ['variant' => 'centered', 'eyebrow' => 'Now available', 'heading' => 'Meet the new standard from {company}', 'subheading' => 'Everything you loved, rebuilt to be faster, simpler and more beautiful. Be one of the first to get it.', 'button_label' => 'Get early access', 'button_url' => '#contact', 'secondary_label' => 'See features', 'secondary_url' => '#services']],
                ['services', ['eyebrow' => 'Features', 'heading' => 'Built for the way you work', 'columns' => '3', 'items' => [
                    ['icon' => 'rocket', 'title' => 'Fast', 'text' => 'Set up in minutes, not days.'],
                    ['icon' => 'shield', 'title' => 'Secure', 'text' => 'Your data stays private and protected.'],
                    ['icon' => 'gem', 'title' => 'Beautiful', 'text' => 'Designed down to the last detail.'],
                ]]],
                ['stats', ['heading' => 'Early results', 'items' => [['value' => '3x', 'label' => 'Faster setup'], ['value' => '4.9', 'label' => 'Beta rating'], ['value' => '10K', 'label' => 'On the waitlist']]]],
                ['faq', ['heading' => 'Good to know', 'items' => [
                    ['question' => 'When does it ship?', 'answer' => 'Early-access customers get it first, then everyone next month.'],
                    ['question' => 'Is there a free trial?', 'answer' => 'Yes, 14 days, no card required.'],
                ]]],
                ['cta', ['heading' => 'Don’t miss the launch', 'text' => 'Join the list and we’ll let you know the moment it’s ready.', 'button_label' => 'Join the waitlist', 'button_url' => '#contact']],
                ['contact', ['heading' => 'Get in touch', 'email' => '{email}', 'show_form' => true]],
            ],
        ],
        'blank' => [
            'name' => 'Blank page',
            'description' => 'Just a hero and contact section. Add the rest yourself.',
            'style' => ['palette' => 'midnight', 'font' => 'modern', 'buttons' => 'rounded'],
            'sections' => [
                ['hero', ['variant' => 'centered', 'heading' => '{company}', 'subheading' => 'Tell visitors what you do in one or two sentences.', 'button_label' => 'Contact us', 'button_url' => '#contact']],
                ['contact', ['heading' => 'Contact', 'email' => '{email}', 'phone' => '{phone}', 'show_form' => true]],
            ],
        ],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::TEMPLATES[$key]);
    }

    /**
     * Sections and style for a new page from the template, with the
     * customer's details filled in.
     *
     * @param  array<string, ?string>  $context  company, name, email, phone, whatsapp, address
     * @return array{style: array, sections: array}
     */
    public static function build(string $key, array $context): array
    {
        $template = self::TEMPLATES[$key] ?? self::TEMPLATES['blank'];
        $replace = [];
        foreach (['company', 'name', 'email', 'phone', 'whatsapp', 'address'] as $token) {
            $replace['{'.$token.'}'] = (string) ($context[$token] ?? '');
        }
        if ($replace['{company}'] === '') {
            $replace['{company}'] = $replace['{name}'] ?: 'us';
        }
        $fill = function ($value) use (&$fill, $replace) {
            return is_array($value) ? array_map($fill, $value) : (is_string($value) ? trim(strtr($value, $replace)) : $value);
        };

        $palette = $template['style']['palette'];

        return [
            'style' => array_merge(Styles::defaults($palette), $template['style']),
            'sections' => array_map(fn ($s) => Sections::make($s[0], $fill($s[1])), $template['sections']),
        ];
    }
}
