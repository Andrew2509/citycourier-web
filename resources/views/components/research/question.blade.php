@props([
    'number',
    'id',
    'title',
    'help' => null,
    'required' => false,
    'type' => 'radio',
    'name' => null,
    'optionalNote' => null,
])

<div
    class="q"
    data-question="{{ $id }}"
    data-type="{{ $type }}"
    data-required="{{ $required ? 'true' : 'false' }}"
    @if ($name) data-name="{{ $name }}" @endif
    role="group"
    aria-labelledby="{{ $id }}-title"
>
    <div class="q-head">
        <span class="q-no" aria-hidden="true">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</span>
        <div class="q-headings">
            <h3 class="q-title" id="{{ $id }}-title">{{ $title }}@if ($required)<span class="q-req" aria-hidden="true">*</span><span class="sr-only"> (wajib diisi)</span>@endif</h3>
            @if ($help)
                <p class="q-help">{{ $help }}</p>
            @endif
            @if ($optionalNote)
                <p class="q-help q-help--optional">{{ $optionalNote }}</p>
            @endif
        </div>
    </div>

    <div class="q-body">
        {{ $slot }}
    </div>

    <p class="q-error" id="{{ $id }}-error" data-error-for="{{ $id }}" role="alert" hidden></p>
</div>
