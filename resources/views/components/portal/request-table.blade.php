@props(['records', 'empty' => 'No verification requests found.', 'admin' => false])
@if ($records->isEmpty())
    <div class="portal-empty"><span><x-portal.icon name="list" /></span><p>{{ $empty }}</p></div>
@else
    <div class="portal-table-wrap">
        <table class="portal-table">
            <thead><tr><th>Reference</th>@if($admin)<th>Account</th>@endif<th>Service</th><th>Version</th><th>Status</th><th>Date</th><th>Result</th></tr></thead>
            <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td><strong>{{ $record->reference }}</strong></td>
                        @if($admin)<td>{{ $record->email }}</td>@endif
                        <td>{{ $record->service }}</td><td>{{ strtoupper($record->version) }}</td>
                        <td><x-portal.status :status="$record->status" /></td><td>{{ $record->created_at }}</td>
                        <td><a class="portal-link" href="{{ route($admin ? 'admin.result' : 'portal.result', $record->id) }}">Details<span class="sr-only"> for {{ $record->reference }}</span></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
