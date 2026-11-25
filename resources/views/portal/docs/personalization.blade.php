<section class="portal-panel">
    <header class="portal-panel-head"><h2>Request headers</h2></header>
    <div class="portal-panel-body">
        <pre class="portal-result">Content-Type: application/json
Accept: application/json</pre>
        <p>Send your {{ config('app.name') }} API key in the JSON <code>token</code> field. A <code>tracking_id</code> contains exactly 15 letters or digits.</p>
    </div>
</section>

<section class="portal-panel">
    <header class="portal-panel-head"><h2>Request submission</h2></header>
    <div class="portal-panel-body">
        <x-portal.endpoint route="api.personalization.submit" />
        <h3>Example request body</h3>
        <pre class="portal-result">{
  "token": "YOUR_API_KEY",
  "tracking_id": "ABC123DEF456GHI",
  "type": "info"
}</pre>
        <h3>Expected response</h3>
        <pre class="portal-result">{
  "success": true,
  "id": 12345,
  "tracking_id": "ABC123DEF456GHI",
  "status": "In-Progress",
  "express": false,
  "message": "Personalization saved. Use the id or tracking_id to check the result."
}</pre>
        <p>The <code>express</code> field is returned when the provider includes it.</p>
    </div>
</section>

<section class="portal-panel">
    <header class="portal-panel-head"><h2>Check status</h2></header>
    <div class="portal-panel-body">
        <x-portal.endpoint route="api.personalization.check" />
        <p>Send either the numeric <code>id</code> or the <code>tracking_id</code> from a submission made by your {{ config('app.name') }} account.</p>
        <h3>Example request body</h3>
        <pre class="portal-result">{"token":"YOUR_API_KEY","id":12345}</pre>
        <p>Or check by tracking ID.</p>
        <pre class="portal-result">{"token":"YOUR_API_KEY","tracking_id":"ABC123DEF456GHI"}</pre>
        <h3>Expected response</h3>
        <p>Read <code>status</code> to determine the outcome. <code>success: true</code> means the check returned a record and can accompany any of the three statuses.</p>
        @foreach ([
            'In-Progress' => ['idNumber' => 'ABC123DEF456GHI', 'photo' => null],
            'Successful' => ['firstName' => 'EXAMPLE', 'lastName' => 'CUSTOMER', 'dateOfBirth' => '12-04-1995', 'idNumber' => '12345678901', 'photo' => 'data:image/jpeg;base64,EXAMPLE_IMAGE_DATA'],
            'Failed' => ['idNumber' => 'XYZ987UVW654RST', 'photo' => null],
        ] as $status => $fields)
            <h3>{{ $status }}</h3>
            <pre class="portal-result">{{ json_encode([
                'success' => true,
                'id' => 12345,
                'tracking_id' => 'ABC123DEF456GHI',
                'status' => $status,
                'data' => array_replace([
                    'firstName' => '', 'middleName' => '', 'lastName' => '', 'dateOfBirth' => '', 'gender' => '',
                    'idNumber' => '', 'tracking_id' => 'ABC123DEF456GHI', 'photo' => null,
                ], $fields),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        @endforeach
        <p>The photo is illustrative and may be <code>null</code> even on success. The provider can return a 15-character identifier in <code>idNumber</code> when a result is in progress or failed.</p>
        <h3>Error responses</h3>
        <pre class="portal-result">{"success":false,"message":"Personalization not found."}
{"success":false,"message":"Insufficient balance."}</pre>
    </div>
</section>
