@php $orderBadges = ['pending' => 'badge-gray', 'paid' => 'badge-brand', 'processing' => 'badge-amber', 'shipped' => 'badge-amber', 'completed' => 'badge-green', 'cancelled' => 'badge-red']; @endphp
<span class="{{ $orderBadges[$status] ?? 'badge-gray' }}">{{ ucfirst($status) }}</span>
