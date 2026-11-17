@props([
    'name',
    'options',
])

<div class="options options--radio">
    @foreach ($options as $key => $label)
        @php($value = is_int($key) ? $label : $key)

        <label class="option">
            <input type="radio" name="{{ $name }}" value="{{ $value }}">
            <span class="option-mark" aria-hidden="true"></span>
            <span class="option-text">{{ $label }}</span>
        </label>
    @endforeach
</div>
