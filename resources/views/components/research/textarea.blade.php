@props([
    'id',
    'name',
    'placeholder' => 'Tuliskan jawaban Anda di sini…',
    'limit' => 1000,
    'rows' => 4,
])

<div class="textarea-wrap">
    <textarea
        class="textarea"
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        maxlength="{{ $limit }}"
        placeholder="{{ $placeholder }}"
        data-counter
    ></textarea>
    <span class="char-count" data-counter-for="{{ $id }}">0 / {{ $limit }}</span>
</div>
