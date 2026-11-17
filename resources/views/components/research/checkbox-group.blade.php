@props([
    'name',
    'options',
    'otherName' => null,
    'otherPlaceholder' => 'Tuliskan jawaban lainnya',
    'otherTrigger' => 'Lainnya',
    'otherLabel' => 'Sebutkan lainnya',
])

<div class="options options--checkbox">
    @foreach ($options as $key => $label)
        @php
            $value = is_int($key) ? $label : $key;
            $isOther = $otherName && $value === $otherTrigger;
        @endphp

        <label class="option">
            <input
                type="checkbox"
                name="{{ $name }}"
                value="{{ $value }}"
                @if ($isOther) data-reveals="{{ $otherName }}-wrap" @endif
            >
            <span class="option-mark" aria-hidden="true"></span>
            <span class="option-text">{{ $label }}</span>
        </label>
    @endforeach
</div>

@if ($otherName)
    <div class="other-field" id="{{ $otherName }}-wrap" data-other-field hidden>
        <label class="other-label" for="{{ $otherName }}">{{ $otherLabel }}</label>
        <input
            class="text-input"
            type="text"
            id="{{ $otherName }}"
            name="{{ $otherName }}"
            maxlength="120"
            placeholder="{{ $otherPlaceholder }}"
            autocomplete="off"
        >
    </div>
@endif
