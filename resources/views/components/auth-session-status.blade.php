@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'message-succes']) }}>
        {{ $status }}
    </div>
@endif
