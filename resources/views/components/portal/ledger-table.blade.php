@props(['records', 'empty'])
@if ($records->isEmpty())
    <div class="portal-empty"><span><x-portal.icon name="wallet" /></span><p>{{ $empty }}</p></div>
@else
    <div class="portal-table-wrap">
        <table class="portal-table">
            <thead><tr><th>Description</th><th>Type</th><th>Date</th><th class="portal-align-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($records as $entry)
                    <tr><td><strong>{{ $entry->description }}</strong></td><td>{{ $entry->amount_minor < 0 ? 'Debit' : 'Credit' }}</td><td>{{ $entry->created_at }}</td><td class="portal-align-right"><x-portal.amount :minor="$entry->amount_minor" /></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
