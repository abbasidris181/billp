@props(['status', 'label' => null])
<span @class(['portal-status', 'portal-status-good' => in_array($status, ['successful', 'approved', 'available']), 'portal-status-bad' => in_array($status, ['failed', 'rejected']), 'portal-status-pending' => in_array($status, ['pending', 'needs_confirmation'], true)])>{{ $label ?? ucfirst($status) }}</span>
