@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'bag' => 'default'])
@php
    $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password' && $type !== 'file') value="{{ old($key, $value) }}" @endif
           @required($required)
           {{ $attributes->except(['class', 'id'])->merge(['class' => 'input-base']) }}
           @error($key, $bag) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
    @error($key, $bag)<p id="{{ $id }}-error" class="form-error">{{ $message }}</p>@enderror
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
</div>
