<?php

namespace App\Support;

/** Field groups shared by the customer form, validation, visibility and the API. */
final class CustomerFields
{
    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'];

    public const SOCIAL = [
        'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X (Twitter)',
        'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'github' => 'GitHub', 'threads' => 'Threads',
        'telegram' => 'Telegram', 'pinterest' => 'Pinterest', 'snapchat' => 'Snapchat', 'twitch' => 'Twitch',
        'dribbble' => 'Dribbble', 'behance' => 'Behance',
    ];

    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** Fields whose visibility can be switched off for the printed card and the digital profile. */
    public const VISIBLE_FIELDS = [
        'professional_title' => 'Professional title', 'department' => 'Department', 'designation' => 'Designation',
        'mobile' => 'Mobile', 'whatsapp' => 'WhatsApp', 'office_phone' => 'Office phone', 'email' => 'Email',
        'website' => 'Website', 'office_address' => 'Office address', 'city' => 'City', 'country' => 'Country',
        'photo' => 'Profile photo', 'social_links' => 'Social links',
    ];

    public const MAX_CUSTOM = 10;

    /** Field => merge field names it feeds (used to blank hidden fields on cards). */
    public const MERGE_MAP = [
        'professional_title' => ['professional_title'], 'department' => ['department'], 'designation' => ['designation'],
        'mobile' => ['mobile'], 'whatsapp' => ['whatsapp'], 'office_phone' => ['office_phone'], 'email' => ['email'],
        'website' => ['website'], 'office_address' => ['address'], 'city' => ['city'], 'country' => ['country'], 'photo' => ['photo'],
    ];

    /** Visible on the given channel ("card" or "profile")? Defaults to visible. */
    public static function visible(?array $visibility, string $channel, string $field): bool
    {
        return (bool) ($visibility[$channel][$field] ?? true);
    }
}
