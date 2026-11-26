@php
    $bvnByPhone = $service === 'bvn-by-phone';
    $expandedBvn = $bvnByPhone || ($version === 'v2' && $service === 'bvn');
    $phoneInput = $bvnByPhone || $service === 'phone';
@endphp
<section class="portal-panel">
    <header class="portal-panel-head"><h2>Request headers and body</h2></header>
    <div class="portal-panel-body">
        <p>Use your {{ config('app.name') }} API key from the API Docs page. All requests use JSON.</p>
        <pre class="portal-result">Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
Accept: application/json</pre>
        <h3>Example request body</h3>
        <pre class="portal-result">{{ json_encode(['type' => $bvnByPhone ? 'bvn_with_phone' : $service, 'idNumber' => $bvnByPhone ? '08000000000' : ($phoneInput ? '08012345678' : '12345678901'), 'consent' => true], JSON_PRETTY_PRINT) }}</pre>
        <p><code>idNumber</code> must be an 11-digit string{{ $phoneInput ? ' beginning with 0' : '' }}. Send the JSON boolean <code>true</code> for <code>consent</code>.</p>
    </div>
</section>

<section class="portal-panel">
    <header class="portal-panel-head"><h2>Expected response</h2></header>
    <div class="portal-panel-body">
        <p>This example uses illustrative values. Identity fields depend on the provider response. Use <code>transID</code> to find the request in {{ config('app.name') }}.</p>
        <pre class="portal-result">{
  "status": true,
  "cached": false,
  "message": {
    "firstName": "EXAMPLE",
    "middleName": " ",
    "lastName": "USER",
    "mobile": "{{ $service === 'nin' || $expandedBvn ? ' ' : '08012345678' }}",
    "dateOfBirth": "23-11-1995",
    "gender": "M",
    "address": {
      "state": " ",
      "town": " ",
      "addressLine": " ",
      "lga": " "@if ($service === 'nin'),
      "city": " "@endif
    },
    "birthCountry": " ",
    "birthState": " ",
    "birthLGA": " ",
    "title": " ",
    "centralID": " ",
    "educationallevel": " ",
    "employmentstatus": " ",
    "height": " ",
    "maritalstatus": " ",
    "nok_address1": " ",
    "nok_firstname": " ",
    "photo": "BASE64_PHOTO",
    "signature": "{{ $service === 'nin' ? 'data:image/png;base64,BASE64_SIGNATURE' : ' ' }}",
    "idNumber": "12345678901",
    "trackingId": " "@if ($service === 'nin'),
    "nokState": " ",
    "religion": " ",
    "status": "found",
    "isConsent": true,
    "type": "nin",
    "country": "NG"@elseif ($expandedBvn),
    "bvn": "12345678901",
    "phoneNumber1": "08000000000",
    "email": "user@example.com",
    "enrollmentBank": "EXAMPLE BANK",
    "stateOfResidence": " ",
    "watchListed": "NO",
    "base64Image": "BASE64_PHOTO"@endif
  },
  "transID": "YOUR_TRANSACTION_REFERENCE"
}</pre>
        <h3>Error codes</h3>
        <p>When the provider returns <code>status: false</code>, {{ config('app.name') }} forwards its message. A no-match response uses HTTP 422, and other provider errors use HTTP 502.</p>
        <p><code>401</code> invalid key · @if ($version === 'v1')<code>402</code> insufficient balance · @endif<code>422</code> invalid input or no match · <code>429</code> rate limit · <code>501</code> service pending · <code>502</code> invalid provider response · <code>503</code> provider unavailable.</p>
        @if ($version === 'v1')
            <p>If v1 error charging is enabled, timeouts and unsuccessful verifications can incur the service’s configured error fee. An insufficient-balance response carries a blank <code>transID</code>.</p>
        @endif
    </div>
</section>
