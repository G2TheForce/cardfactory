<?php

/*
| Fonts available in the theme customizer and card designer.
| "google" fonts are loaded from Google Fonts; custom uploaded fonts are added
| per user through uploaded_assets (type "font").
*/

return [
    'google' => [
        'Inter' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700, 800]],
        'Poppins' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700, 800]],
        'Montserrat' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700, 800]],
        'Roboto' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 700]],
        'Open Sans' => ['category' => 'sans-serif', 'weights' => [300, 400, 600, 700]],
        'Lato' => ['category' => 'sans-serif', 'weights' => [300, 400, 700]],
        'Raleway' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700]],
        'Nunito Sans' => ['category' => 'sans-serif', 'weights' => [300, 400, 600, 700, 800]],
        'Work Sans' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700]],
        'DM Sans' => ['category' => 'sans-serif', 'weights' => [400, 500, 700]],
        'Manrope' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700, 800]],
        'Space Grotesk' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700]],
        'Outfit' => ['category' => 'sans-serif', 'weights' => [300, 400, 500, 600, 700]],
        'Sora' => ['category' => 'sans-serif', 'weights' => [300, 400, 600, 700]],
        'Josefin Sans' => ['category' => 'sans-serif', 'weights' => [300, 400, 600, 700]],
        'Playfair Display' => ['category' => 'serif', 'weights' => [400, 500, 600, 700, 800]],
        'Cormorant Garamond' => ['category' => 'serif', 'weights' => [300, 400, 500, 600, 700]],
        'Lora' => ['category' => 'serif', 'weights' => [400, 500, 600, 700]],
        'Merriweather' => ['category' => 'serif', 'weights' => [300, 400, 700]],
        'Libre Baskerville' => ['category' => 'serif', 'weights' => [400, 700]],
        'Cinzel' => ['category' => 'serif', 'weights' => [400, 500, 600, 700]],
        'EB Garamond' => ['category' => 'serif', 'weights' => [400, 500, 600, 700]],
        'DM Serif Display' => ['category' => 'serif', 'weights' => [400]],
        'Bebas Neue' => ['category' => 'display', 'weights' => [400]],
        'Oswald' => ['category' => 'display', 'weights' => [300, 400, 500, 600, 700]],
        'Archivo Black' => ['category' => 'display', 'weights' => [400]],
        'Great Vibes' => ['category' => 'handwriting', 'weights' => [400]],
        'Dancing Script' => ['category' => 'handwriting', 'weights' => [400, 500, 600, 700]],
        'JetBrains Mono' => ['category' => 'monospace', 'weights' => [300, 400, 500, 700]],
        'IBM Plex Mono' => ['category' => 'monospace', 'weights' => [300, 400, 500, 600, 700]],
    ],

    'fallbacks' => [
        'sans-serif' => 'ui-sans-serif, system-ui, Arial, sans-serif',
        'serif' => 'Georgia, Times New Roman, serif',
        'display' => 'Impact, Arial Black, sans-serif',
        'handwriting' => 'cursive',
        'monospace' => 'ui-monospace, Menlo, monospace',
    ],
];
