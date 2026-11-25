<section class="portal-panel">
    <header class="portal-panel-head"><h2>Request headers</h2></header>
    <div class="portal-panel-body">
        <pre class="portal-result">Content-Type: application/json
Accept: application/json</pre>
        <p>Send your {{ config('app.name') }} API key in the JSON <code>token</code> field. The <code>tracking_id</code> must contain exactly 15 letters or digits.</p>
    </div>
</section>

<section class="portal-panel">
    <header class="portal-panel-head"><h2>Request submission</h2></header>
    <div class="portal-panel-body">
        <x-portal.endpoint route="api.clearance.submit" />
        <h3>Example request body</h3>
        <pre class="portal-result">{
  "token": "YOUR_API_KEY",
  "tracking_id": "0SLT9LJS17C5G5D"
}</pre>
        <h3>Expected response</h3>
        <pre class="portal-result">{"success":"Clearance request submitted successfully."}</pre>
        <h3>Submission errors · HTTP 400</h3>
        <pre class="portal-result">{"error":"Previous clearance failed. Attempt saved but not charged."}
{"error":"Duplicate Entry detected. Attempt saved as Failed."}
{"error":"Insufficient Balance."}</pre>
    </div>
</section>

<section class="portal-panel">
    <header class="portal-panel-head"><h2>Check status</h2></header>
    <div class="portal-panel-body">
        <x-portal.endpoint route="api.clearance.status" />
        <p>Use the tracking ID from a clearance submission made by your {{ config('app.name') }} account.</p>
        <h3>Example request body</h3>
        <pre class="portal-result">{
  "token": "YOUR_API_KEY",
  "tracking_id": "0SLT9LJS17C5G5D"
}</pre>
        <h3>Expected response · successful</h3>
        <pre class="portal-result">{
  "status": "Successful",
  "reply": "ABC123DEF456GHI",
  "nin": "12345678901",
  "name": "EXAMPLE CUSTOMER",
  "dob": "1995-04-12"
}</pre>
        <h3>Pending</h3>
        <pre class="portal-result">{"status":"New","reply":null,"nin":null,"name":null,"dob":null}</pre>
        <h3>Failed</h3>
        <pre class="portal-result">{"status":"Failed","reply":"Ready for Abis","nin":null,"name":null,"dob":null}
{"status":"Failed","reply":"No Record Found","nin":null,"name":null,"dob":null}
{"status":"Failed","reply":"Invalid","nin":null,"name":null,"dob":null}
{"status":"Failed","reply":"Blocked","nin":null,"name":null,"dob":null}</pre>
        <p>{{ config('app.name') }} returns the provider’s <code>reply</code> verbatim.</p>
        <h3>Status-check error · HTTP 404</h3>
        <pre class="portal-result">{"error":"Clearance record not found."}</pre>
    </div>
</section>
