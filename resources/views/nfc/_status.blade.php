@php
    $nfcBadges = ['in_stock' => 'badge-gray', 'assigned' => 'badge-amber', 'active' => 'badge-green', 'sold' => 'badge-brand', 'defective' => 'badge-red', 'lost' => 'badge-red', 'replaced' => 'badge-gray', 'retired' => 'badge-gray'];
@endphp
<span class="{{ $nfcBadges[$status] ?? 'badge-gray' }}">{{ str_replace('_', ' ', ucfirst($status)) }}</span>
