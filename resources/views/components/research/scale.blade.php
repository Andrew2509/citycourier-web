@props([
    'name',
    'minLabel' => 'Sangat sulit',
    'maxLabel' => 'Sangat mudah',
])

<div class="scale">
    <div class="scale-cells">
        @for ($value = 1; $value <= 5; $value++)
            <label class="scale-cell">
                <input type="radio" name="{{ $name }}" value="{{ $value }}" aria-label="{{ $value }} dari 5">
                <span>{{ $value }}</span>
            </label>
        @endfor
    </div>
    <div class="scale-legend">
        <span>1 — {{ $minLabel }}</span>
        <span>5 — {{ $maxLabel }}</span>
    </div>
</div>
