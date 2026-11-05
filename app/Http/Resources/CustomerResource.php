<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'full_name' => $this->full_name,
            'professional_title' => $this->professional_title,
            'designation' => $this->designation,
            'department' => $this->department,
            'photo_url' => $this->profile_photo_path ? Storage::disk('public')->url($this->profile_photo_path) : null,
            'contact' => [
                'mobile' => $this->mobile,
                'whatsapp' => $this->whatsapp,
                'office_phone' => $this->office_phone,
                'email' => $this->email,
                'website' => $this->website,
            ],
            'address' => [
                'office_address' => $this->office_address,
                'city' => $this->city,
                'province' => $this->province,
                'country' => $this->country,
                'map_url' => $this->map_url,
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            ],
            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id' => $this->company->ulid,
                'name' => $this->company->name,
                'website' => $this->company->website,
                'logo_url' => $this->company->logo_path ? Storage::disk('public')->url($this->company->logo_path) : null,
            ] : null),
            'social_links' => (object) ($this->social_links ?? []),
            'business' => (object) ($this->business ?? []),
            'custom_fields' => $this->custom_fields ?? [],
            'custom_links' => $this->custom_links ?? [],
            'introduction' => $this->introduction,
            'portfolio_url' => $this->portfolio_url,
            'booking_url' => $this->booking_url,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'has_login' => $this->user_id !== null,
            'assigned_designer' => $this->whenLoaded('assignedDesigner', fn () => $this->assignedDesigner ? ['id' => $this->assignedDesigner->ulid, 'name' => $this->assignedDesigner->name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
