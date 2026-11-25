<div class="portal-table-wrap">
    <table class="portal-table">
        <thead><tr><th>Transaction ID</th><th>Tracking ID</th><th>Batch ID</th><th>Reply</th><th>NIN</th><th>Price</th><th>Status</th><th>Refund</th><th>Submitted</th><th>Check status</th></tr></thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>{{ $record->reference }}</td>
                    <td>{{ $record->tracking_id }}</td>
                    <td>@if ($record->batch_id)<a class="portal-link" href="{{ route('instant-ipe.batch', $record->batch_id) }}">{{ $record->batch_id }}</a>@else — @endif</td>
                    <td>{{ $record->reply ?? '—' }}</td>
                    <td>{{ $record->nin ?? '—' }}</td>
                    <td><x-portal.amount :minor="$record->price_minor" /></td>
                    <td><x-portal.status :status="$record->status" :label="\App\Models\InstantIpeRequest::LABELS[$record->status]" /></td>
                    <td>{{ $record->refunded_at ? 'Refunded' : '—' }}</td>
                    <td>{{ $record->created_at }}</td>
                    <td>
                        @if ($record->batch_id)
                            <form method="POST" action="{{ route('instant-ipe.batch.check', $record->batch_id) }}">
                                @csrf
                                <button class="portal-btn" aria-label="Check status for batch {{ $record->batch_id }}">Check batch status</button>
                            </form>
                        @elseif (in_array($record->status, \App\Models\InstantIpeRequest::CHECKABLE, true))
                            <form method="POST" action="{{ route('instant-ipe.check', $record->id) }}">
                                @csrf
                                <button class="portal-btn" aria-label="Check status for {{ $record->tracking_id }}">Check status</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">No Instant-ipe requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($records->hasPages())<div class="portal-pagination">{{ $records->links('pagination::simple-tailwind') }}</div>@endif
