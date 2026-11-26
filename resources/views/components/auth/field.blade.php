@props(['name', 'label', 'type' => 'text', 'autocomplete', 'value' => null])

<div @class(['auth-field', 'auth-field-error' => $errors->has($name)])>
    <div class="auth-input-wrap">
        <input
            {{ $attributes->class(['auth-input', 'auth-input-password' => $type === 'password']) }}
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            autocomplete="{{ $autocomplete }}"
            placeholder=" "
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            required
        >
        <label for="{{ $name }}">{{ $label }}</label>
        @if ($type === 'password')
            <button class="auth-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-controls="{{ $name }}" hidden>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"/>
                    <circle cx="12" cy="12" r="2.5"/>
                    <path class="auth-eye-slash" d="m3 3 18 18"/>
                </svg>
            </button>
        @endif
    </div>
    @error($name)
        <p id="{{ $name }}-error" class="auth-error">{{ $message }}</p>
    @enderror
</div>
