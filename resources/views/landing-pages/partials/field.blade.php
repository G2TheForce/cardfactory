{{--
    One editor field, rendered by Alpine from the section schema.
    $target: the JS object holding the values ("section.data" or "item"); "field" is the current schema field.
--}}
<template x-if="field.type === 'text'">
    <div>
        <label class="form-label" x-text="field.label"></label>
        <input type="text" class="input-base" x-model="{{ $target }}[field.name]" :maxlength="field.max">
    </div>
</template>
<template x-if="field.type === 'textarea'">
    <div>
        <label class="form-label" x-text="field.label"></label>
        <textarea class="input-base" rows="3" x-model="{{ $target }}[field.name]" :maxlength="field.max"></textarea>
    </div>
</template>
<template x-if="field.type === 'url'">
    <div>
        <label class="form-label" x-text="field.label"></label>
        <input type="text" class="input-base font-mono text-xs" x-model="{{ $target }}[field.name]" placeholder="#contact, https://…, tel:… or mailto:…" maxlength="500">
    </div>
</template>
<template x-if="field.type === 'select'">
    <div>
        <label class="form-label" x-text="field.label"></label>
        <select class="select-base" x-model="{{ $target }}[field.name]">
            <template x-for="[value, text] in Object.entries(field.options)" :key="value">
                <option :value="value" x-text="text" :selected="String({{ $target }}[field.name]) === value"></option>
            </template>
        </select>
    </div>
</template>
<template x-if="field.type === 'toggle'">
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" class="checkbox-base" x-model="{{ $target }}[field.name]"> <span x-text="field.label"></span>
    </label>
</template>
<template x-if="field.type === 'icon'">
    <div>
        <label class="form-label" x-text="field.label"></label>
        <select class="select-base" x-model="{{ $target }}[field.name]">
            <template x-for="icon in icons" :key="icon">
                <option :value="icon" x-text="icon.replaceAll('-', ' ')" :selected="{{ $target }}[field.name] === icon"></option>
            </template>
        </select>
    </div>
</template>
<template x-if="field.type === 'image'">
    <div>
        <label class="form-label" x-text="field.label"></label>
        <div class="flex items-center gap-3">
            <span class="grid h-16 w-24 shrink-0 place-items-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-50">
                <img x-show="imageUrl({{ $target }}[field.name])" :src="imageUrl({{ $target }}[field.name])" alt="" class="h-full w-full object-cover">
                <x-lucide-image class="h-5 w-5 text-gray-300" x-show="! imageUrl({{ $target }}[field.name])" />
            </span>
            <label class="btn-secondary btn-sm cursor-pointer">
                <x-lucide-upload class="h-3.5 w-3.5" /> <span x-text="uploading === ({{ $keyExpr }}) ? 'Uploading…' : ({{ $target }}[field.name] ? 'Replace' : 'Upload')">Upload</span>
                <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" @change="upload($event, {{ $target }}, field.name, {{ $keyExpr }})">
            </label>
            <button type="button" class="btn-ghost btn-sm" x-show="{{ $target }}[field.name]" @click="{{ $target }}[field.name] = ''">Remove</button>
        </div>
    </div>
</template>
