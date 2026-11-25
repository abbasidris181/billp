@extends('portal.layout')
@section('title', 'Request Live Access')
@section('content')
    <div class="portal-heading"><div><h1>Request Live Access</h1><p>Submit your business details for production access review.</p></div></div>
    @if($application)
        <section class="portal-panel portal-form-width">
            <header class="portal-panel-head"><h2>Application status</h2><x-portal.status :status="$application->status" /></header>
            <dl class="portal-panel-body portal-definition">
                <div><dt>Business</dt><dd>{{ $application->business_name }}</dd></div>
                <div><dt>Registration number</dt><dd>{{ $application->registration_number }}</dd></div>
                <div><dt>Submitted</dt><dd>{{ $application->created_at }}</dd></div>
            </dl>
        </section>
    @else
        <section class="portal-panel portal-form-width">
            <header class="portal-panel-head"><h2>Business verification</h2></header>
            <form class="portal-panel-body portal-form" method="POST" action="{{ route('portal.apply') }}" enctype="multipart/form-data">
                @csrf
                <label>Legal business name<input name="business_name" value="{{ old('business_name') }}" maxlength="255" required></label>
                <label>Business registration number<input name="registration_number" value="{{ old('registration_number') }}" maxlength="100" required></label>
                <label>How will you use {{ config('app.name') }}?<textarea name="use_case" maxlength="3000" rows="4" required>{{ old('use_case') }}</textarea></label>
                <label>Business registration document<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="upload-hint" required></label>
                <p id="upload-hint">PDF, JPG or PNG, up to 10 MB.</p>
                <div><button class="portal-btn portal-btn-primary">Submit application</button></div>
            </form>
        </section>
    @endif
@endsection
