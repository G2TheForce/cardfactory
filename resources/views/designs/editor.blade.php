@php
    $config = $state + ['svgs' => $svgs];
    $tabs = [
        'elements' => ['Elements', 'shapes'],
        'text' => ['Text', 'type'],
        'uploads' => ['Uploads', 'upload'],
        'background' => ['Background', 'paint-bucket'],
        'layers' => ['Layers', 'layers'],
        'fields' => ['Fields', 'braces'],
    ];
    $weights = [100 => 'Thin', 200 => 'Extra light', 300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'Semibold', 700 => 'Bold', 800 => 'Extra bold', 900 => 'Black'];
    $typeIcons = ['text' => 'type', 'heading' => 'heading', 'shape' => 'square', 'line' => 'minus', 'icon' => 'star', 'social' => 'at-sign', 'image' => 'image', 'logo' => 'gem', 'photo' => 'circle-user', 'qr' => 'qr-code', 'pattern' => 'grid-3x3'];
    $socialLabels = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X', 'github' => 'GitHub', 'whatsapp' => 'WhatsApp', 'website' => 'Website', 'email' => 'Email', 'phone' => 'Phone'];
@endphp
<x-layouts.editor :title="'Edit '.$design->name">
    @push('head')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $fontsUrl }}">
        @if ($fontFaceCss)<style>{!! $fontFaceCss !!}</style>@endif
    @endpush

    <div x-data="cardEditor(@js($config))" class="flex h-full flex-col" x-cloak>
        {{-- ============================================================ toolbar --}}
        <header class="z-30 flex h-14 shrink-0 items-center gap-1 border-b border-gray-200 bg-white px-2 sm:gap-2 sm:px-3">
            <a href="{{ route('designs.index') }}" class="btn-ghost p-2" title="Back to designs" aria-label="Back to designs"><x-lucide-arrow-left class="h-4 w-4" /></a>
            <div class="min-w-0 flex-1 sm:flex-none">
                <input x-model="design.name" @input="dirty = true" maxlength="120" aria-label="Design name"
                       class="w-full min-w-0 rounded-md border-0 bg-transparent px-1.5 py-1 text-sm font-semibold text-gray-900 hover:bg-gray-50 focus:bg-white focus:ring-2 focus:ring-brand-500 sm:w-56">
                <p class="hidden truncate px-1.5 text-[11px] sm:block" :class="error ? 'text-red-600' : 'text-gray-400'" x-text="statusText" role="status"></p>
            </div>

            <div class="mx-1 hidden h-6 w-px bg-gray-200 md:block"></div>
            <button class="btn-ghost p-2 disabled:opacity-30" @click="undo()" :disabled="!canUndo && !dirty" title="Undo (Ctrl+Z)" aria-label="Undo"><x-lucide-undo-2 class="h-4 w-4" /></button>
            <button class="btn-ghost p-2 disabled:opacity-30" @click="redo()" :disabled="!canRedo" title="Redo (Ctrl+Shift+Z)" aria-label="Redo"><x-lucide-redo-2 class="h-4 w-4" /></button>

            <div class="mx-1 hidden h-6 w-px bg-gray-200 md:block"></div>
            <div class="hidden rounded-lg bg-gray-100 p-0.5 sm:flex" role="tablist" aria-label="Card side">
                <template x-for="s in ['front', 'back']" :key="s">
                    <button class="rounded-md px-3 py-1 text-xs font-semibold capitalize transition" role="tab" :aria-selected="side === s"
                            :class="side === s ? 'bg-white text-gray-900 shadow-soft' : 'text-gray-500 hover:text-gray-800'" @click="switchSide(s)" x-text="s"></button>
                </template>
            </div>

            <div class="hidden items-center lg:flex">
                <button class="btn-ghost p-2" @click="setZoom(zoom / 1.25)" title="Zoom out" aria-label="Zoom out"><x-lucide-zoom-out class="h-4 w-4" /></button>
                <button class="w-14 rounded-md py-1 text-xs font-medium text-gray-600 hover:bg-gray-100" @click="fit()" title="Fit to screen" x-text="zoomPercent + '%'"></button>
                <button class="btn-ghost p-2" @click="setZoom(zoom * 1.25)" title="Zoom in" aria-label="Zoom in"><x-lucide-zoom-in class="h-4 w-4" /></button>
            </div>
            <button class="btn-ghost hidden p-2 lg:inline-flex" :class="design.showGuides && 'bg-brand-50 text-brand-700'" @click="toggleGuides()" title="Bleed, trim and safe-area guides" aria-label="Toggle guides"><x-lucide-frame class="h-4 w-4" /></button>
            <select class="select-base hidden w-36 py-1.5 text-xs xl:block" x-model="fields" @change="setFields(fields)" aria-label="Field values shown">
                <option value="customer">{{ $design->customer ? 'Customer data' : 'Sample data' }}</option>
                @if ($design->customer)<option value="sample">Sample data</option>@endif
                <option value="raw">Field tags</option>
            </select>

            <div class="flex-1"></div>
            <span class="hidden h-2 w-2 rounded-full bg-brand-500 sm:block" x-show="rendering" title="Rendering"></span>
            <button class="btn-ghost p-2" @click="openPreview()" title="Preview" aria-label="Preview"><x-lucide-eye class="h-4 w-4" /></button>
            <button class="btn-ghost hidden p-2 sm:inline-flex" @click="settingsOpen = true" title="Card settings" aria-label="Card settings"><x-lucide-settings-2 class="h-4 w-4" /></button>
            <form method="POST" action="{{ route('designs.duplicate', $design) }}" class="hidden sm:block" @submit="if (dirty && !confirm('Duplicate the last saved version? Unsaved changes are not copied.')) $event.preventDefault()">
                @csrf
                <button class="btn-ghost p-2" title="Duplicate design" aria-label="Duplicate design"><x-lucide-copy class="h-4 w-4" /></button>
            </form>
            <button class="btn-primary px-3 py-1.5" @click="save()" :disabled="saving" title="Save (Ctrl+S)">
                <x-lucide-save class="h-4 w-4" /><span class="hidden sm:inline" x-text="saving ? 'Saving…' : 'Save'"></span>
            </button>
        </header>

        <div class="relative flex min-h-0 flex-1">
            {{-- ============================================================ tab rail --}}
            <nav class="z-20 hidden w-[72px] shrink-0 flex-col items-stretch gap-1 border-r border-gray-200 bg-white p-1.5 lg:flex" aria-label="Editor panels">
                @foreach ($tabs as $key => [$label, $icon])
                    <button class="flex flex-col items-center gap-1 rounded-lg py-2 text-[10px] font-medium transition"
                            :class="tab === '{{ $key }}' ? 'bg-brand-50 text-brand-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'" @click="tab = '{{ $key }}'">
                        <x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" />{{ $label }}
                    </button>
                @endforeach
            </nav>

            {{-- ============================================================ left panel --}}
            <aside class="z-20 w-full max-w-[19rem] shrink-0 flex-col overflow-y-auto border-r border-gray-200 bg-white max-lg:fixed max-lg:left-0 max-lg:top-14 max-lg:bottom-14 max-lg:shadow-xl"
                   :class="mobilePanel === 'left' ? 'flex' : 'hidden lg:flex'">
                <div class="flex gap-1 overflow-x-auto border-b border-gray-100 p-2 lg:hidden">
                    @foreach ($tabs as $key => [$label, $icon])
                        <button class="whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-medium" :class="tab === '{{ $key }}' ? 'bg-brand-50 text-brand-700' : 'text-gray-500'" @click="tab = '{{ $key }}'">{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Elements --}}
                <section x-show="tab === 'elements'" class="space-y-6 p-4">
                    <div>
                        <h3 class="editor-heading">Shapes &amp; lines</h3>
                        <div class="grid grid-cols-4 gap-2">
                            <button class="editor-tile h-12" @click="add('shape', { shape: 'rect' })" title="Rectangle"><span class="h-6 w-8 rounded-[2px] bg-gray-700"></span></button>
                            <button class="editor-tile h-12" @click="add('shape', { shape: 'rect', radius: 3 })" title="Rounded rectangle"><span class="h-6 w-8 rounded-md bg-gray-700"></span></button>
                            <button class="editor-tile h-12" @click="add('shape', { shape: 'circle' })" title="Circle"><span class="h-7 w-7 rounded-full bg-gray-700"></span></button>
                            <button class="editor-tile h-12" @click="add('shape', { shape: 'ellipse' })" title="Ellipse"><span class="h-5 w-8 rounded-[50%] bg-gray-700"></span></button>
                            <button class="editor-tile h-12" @click="add('shape', { shape: 'triangle' })" title="Triangle"><svg viewBox="0 0 24 24" class="h-7 w-7 fill-gray-700"><path d="M12 3 22 21H2z"/></svg></button>
                            <button class="editor-tile h-12" @click="add('shape', { shape: 'diamond' })" title="Diamond"><svg viewBox="0 0 24 24" class="h-7 w-7 fill-gray-700"><path d="M12 2 22 12 12 22 2 12z"/></svg></button>
                            <button class="editor-tile h-12" @click="add('line')" title="Line"><span class="h-0.5 w-8 bg-gray-700"></span></button>
                            <button class="editor-tile h-12" @click="add('line', { dash: 'dashed' })" title="Dashed line"><span class="w-8 border-t-2 border-dashed border-gray-700"></span></button>
                        </div>
                    </div>
                    <div>
                        <h3 class="editor-heading">Card essentials</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <button class="editor-tile-wide" @click="add('qr')"><x-lucide-qr-code class="h-4 w-4" /> QR code</button>
                            <button class="editor-tile-wide" @click="add('logo')"><x-lucide-gem class="h-4 w-4" /> Logo slot</button>
                            <button class="editor-tile-wide" @click="add('photo')"><x-lucide-circle-user class="h-4 w-4" /> Photo slot</button>
                            <button class="editor-tile-wide" @click="add('pattern')"><x-lucide-grid-3x3 class="h-4 w-4" /> Pattern</button>
                        </div>
                    </div>
                    <div>
                        <h3 class="editor-heading">Social icons</h3>
                        <div class="grid grid-cols-6 gap-1.5">
                            @foreach ($socialSvgs as $network => $inner)
                                <button class="editor-tile aspect-square p-0" @click="add('social', { network: '{{ $network }}' })" title="{{ $socialLabels[$network] ?? $network }}" aria-label="Add {{ $socialLabels[$network] ?? $network }} icon">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $inner !!}</svg>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <h3 class="editor-heading">Icons</h3>
                        <form class="relative mb-2" @submit.prevent="searchIcons()">
                            <x-lucide-search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input type="search" x-model="iconQuery" @input.debounce.300ms="searchIcons()" placeholder="Search 1,500+ icons" class="input-base py-1.5 pl-8 text-xs" aria-label="Search icons">
                        </form>
                        <div class="grid grid-cols-6 gap-1.5" x-show="!iconQuery">
                            @foreach ($iconSvgs as $name => $inner)
                                <button class="editor-tile aspect-square p-0" @click="add('icon', { icon: '{{ $name }}' })" title="{{ $name }}" aria-label="Add {{ $name }} icon">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $inner !!}</svg>
                                </button>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-6 gap-1.5" x-show="iconQuery">
                            <template x-for="icon in iconResults" :key="icon.name">
                                <button class="editor-tile aspect-square p-0" @click="isImage || selected?.type !== 'icon' ? add('icon', { icon: icon.name }) : (selected.icon = icon.name, changed({ geometry: false }))" :title="icon.name">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-html="icon.svg"></svg>
                                </button>
                            </template>
                            <p class="col-span-6 py-3 text-center text-xs text-gray-400" x-show="iconQuery && !iconResults.length">No icons match.</p>
                        </div>
                    </div>
                </section>

                {{-- Text --}}
                <section x-show="tab === 'text'" class="space-y-3 p-4">
                    <button class="block w-full rounded-lg border border-gray-200 px-3 py-3 text-left text-xl font-bold text-gray-900 hover:border-brand-300 hover:bg-brand-50/40" @click="add('heading')">Add a heading</button>
                    <button class="block w-full rounded-lg border border-gray-200 px-3 py-2.5 text-left text-sm text-gray-700 hover:border-brand-300 hover:bg-brand-50/40" @click="add('text')">Add body text</button>
                    <h3 class="editor-heading pt-3">Contact lines</h3>
                    @foreach (['full_name' => 'Name', 'professional_title' => 'Job title', 'company' => 'Company', 'mobile' => 'Mobile', 'email' => 'Email', 'website' => 'Website', 'address' => 'Address'] as $key => $label)
                        <button class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" @click="add('text', { text: tag('{{ $key }}') })">
                            {{ $label }} <span class="font-mono text-[10px] text-gray-400" x-text="tag('{{ $key }}')"></span>
                        </button>
                    @endforeach
                </section>

                {{-- Uploads --}}
                <section x-show="tab === 'uploads'" class="space-y-5 p-4">
                    <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 px-4 py-6 text-center transition hover:border-brand-300 hover:bg-brand-50/30"
                           @dragover.prevent @drop.prevent="upload($event)">
                        <x-lucide-image-up class="h-6 w-6 text-gray-400" />
                        <span class="mt-2 text-sm font-medium text-gray-700" x-text="uploading ? 'Uploading…' : 'Upload images'"></span>
                        <span class="mt-0.5 text-xs text-gray-400">JPG, PNG or WebP up to 10 MB</span>
                        <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" multiple @change="upload($event)" :disabled="uploading">
                    </label>
                    <p class="text-xs text-gray-500" x-show="isImage">Click an upload to place it in the selected image.</p>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="asset in assets" :key="asset.id">
                            <div class="group relative overflow-hidden rounded-lg border border-gray-200 bg-[conic-gradient(#f3f4f6_25%,#fff_0_50%,#f3f4f6_0_75%,#fff_0)] [background-size:12px_12px]">
                                <button class="block aspect-square w-full" @click="isImage ? replaceImage(asset) : addImage(asset)" :title="asset.name">
                                    <img :src="asset.url" :alt="asset.name" class="h-full w-full object-contain" loading="lazy">
                                </button>
                                <div class="absolute inset-x-1 bottom-1 hidden justify-between gap-1 group-hover:flex">
                                    <button class="rounded bg-white/90 px-1.5 py-0.5 text-[10px] font-medium text-gray-700 shadow" @click="useAsBackground(asset)">Background</button>
                                    <button class="rounded bg-white/90 p-1 text-red-600 shadow" @click="deleteAsset(asset)" aria-label="Delete upload"><x-lucide-trash-2 class="h-3 w-3" /></button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p class="text-center text-xs text-gray-400" x-show="!assets.length">Your uploaded logos and photos appear here.</p>

                    <div class="border-t border-gray-100 pt-4">
                        <h3 class="editor-heading">Custom fonts</h3>
                        <input x-model="fontFamily" maxlength="60" placeholder="Font name (optional)" class="input-base mb-2 py-1.5 text-xs" aria-label="Font name">
                        <label class="btn-secondary btn-sm w-full cursor-pointer">
                            <x-lucide-type class="h-3.5 w-3.5" /> Upload TTF, OTF or WOFF
                            <input type="file" class="sr-only" accept=".ttf,.otf,.woff,.woff2" @change="uploadFont($event)" :disabled="uploading">
                        </label>
                        <ul class="mt-2 space-y-1">
                            <template x-for="font in fonts" :key="font.id">
                                <li class="flex items-center justify-between rounded-md px-2 py-1 text-sm hover:bg-gray-50">
                                    <span :style="`font-family:'${font.family}'`" x-text="font.family"></span>
                                    <button class="text-gray-400 hover:text-red-600" @click="deleteAsset(font)" aria-label="Delete font"><x-lucide-x class="h-3.5 w-3.5" /></button>
                                </li>
                            </template>
                        </ul>
                        <p class="form-hint">Only upload fonts you are licensed to embed in print and web.</p>
                    </div>
                </section>

                {{-- Background & palette --}}
                <section x-show="tab === 'background'" class="space-y-5 p-4">
                    <div>
                        <h3 class="editor-heading">Background of the <span x-text="side"></span></h3>
                        <div class="mb-3 grid grid-cols-3 gap-1 rounded-lg bg-gray-100 p-0.5">
                            <template x-for="t in ['solid', 'gradient', 'image']" :key="t">
                                <button class="rounded-md py-1 text-xs font-medium capitalize" :class="scene.background.type === t ? 'bg-white shadow-soft text-gray-900' : 'text-gray-500'" @click="setBackground(t)" x-text="t"></button>
                            </template>
                        </div>
                        <template x-if="scene.background.type === 'solid'">
                            <x-editor.color model="scene.background.color" label="Colour" />
                        </template>
                        <template x-if="scene.background.type === 'gradient'">
                            <div class="space-y-3">
                                <template x-for="(stop, i) in scene.background.stops" :key="i">
                                    <x-editor.color model="stop.color" label="Stop" />
                                </template>
                                <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Angle <span x-text="scene.background.angle + '°'"></span></span>
                                    <input type="range" min="0" max="360" class="w-full accent-brand-600" x-model.number="scene.background.angle" @input="changed({ geometry: false })"></label>
                            </div>
                        </template>
                        <template x-if="scene.background.type === 'image'">
                            <div class="space-y-3">
                                <div class="grid grid-cols-3 gap-1.5">
                                    <template x-for="asset in assets" :key="asset.id">
                                        <button class="aspect-square overflow-hidden rounded-md border" :class="scene.background.src === asset.src ? 'ring-2 ring-brand-500' : 'border-gray-200'" @click="scene.background.src = asset.src; changed({ geometry: false })">
                                            <img :src="asset.url" alt="" class="h-full w-full object-cover">
                                        </button>
                                    </template>
                                </div>
                                <p class="text-xs text-gray-500" x-show="!assets.length">Upload an image in the Uploads panel first.</p>
                                <select class="select-base py-1.5 text-xs" x-model="scene.background.fit" @change="changed({ geometry: false })" aria-label="Image fit">
                                    <option value="cover">Fill the card</option><option value="contain">Fit inside</option>
                                </select>
                                <x-editor.color model="scene.background.overlay" label="Overlay tint" />
                                <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Overlay strength</span>
                                    <input type="range" min="0" max="1" step="0.05" class="w-full accent-brand-600" x-model.number="scene.background.overlayOpacity" @input="changed({ geometry: false })"></label>
                                <button class="text-xs text-gray-500 underline" @click="scene.background.overlay = null; changed({ geometry: false })">Remove tint</button>
                            </div>
                        </template>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <h3 class="editor-heading">Design palette</h3>
                        <p class="mb-3 text-xs text-gray-500">Elements using palette colours update everywhere when you change these.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="key in paletteKeys" :key="key">
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-1.5">
                                    <input type="color" class="h-7 w-8 cursor-pointer rounded border-0 bg-transparent p-0" x-model="design.palette[key]" @input="changed({ geometry: false })">
                                    <span class="text-xs font-medium capitalize text-gray-700" x-text="key"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </section>

                {{-- Layers --}}
                <section x-show="tab === 'layers'" class="p-2">
                    <p class="px-2 py-2 text-xs text-gray-500">Top of the list is in front. Shift-click to select several.</p>
                    <ul class="space-y-0.5" role="listbox" aria-label="Layers" aria-multiselectable="true">
                        <template x-for="el in layers" :key="el.id">
                            <li class="group flex items-center gap-1 rounded-lg pl-2 pr-1 text-sm" role="option" :aria-selected="selectedIds.includes(el.id)"
                                :class="selectedIds.includes(el.id) ? 'bg-brand-50 text-brand-800' : 'text-gray-700 hover:bg-gray-50'">
                                <button class="flex min-w-0 flex-1 items-center gap-2 py-1.5 text-left" @click="select(el.id, $event)" :class="el.hidden && 'opacity-40'">
                                    @foreach ($typeIcons as $type => $icon)
                                        <x-dynamic-component :component="'lucide-'.$icon" class="h-3.5 w-3.5 shrink-0 text-gray-400" x-show="el.type === '{{ $type }}'" />
                                    @endforeach
                                    <span class="truncate" x-text="el.name || el.type"></span>
                                </button>
                                <button class="rounded p-1 text-gray-400 hover:text-gray-700" @click="arrange('forward', el.id)" title="Move up" aria-label="Move layer up"><x-lucide-chevron-up class="h-3.5 w-3.5" /></button>
                                <button class="rounded p-1 text-gray-400 hover:text-gray-700" @click="arrange('backward', el.id)" title="Move down" aria-label="Move layer down"><x-lucide-chevron-down class="h-3.5 w-3.5" /></button>
                                <button class="rounded p-1 hover:text-gray-700" :class="el.locked ? 'text-amber-600' : 'text-gray-400'" @click="toggle(el.id, 'locked')" :title="el.locked ? 'Unlock' : 'Lock'" :aria-label="el.locked ? 'Unlock layer' : 'Lock layer'">
                                    <x-lucide-lock class="h-3.5 w-3.5" x-show="el.locked" /><x-lucide-lock-open class="h-3.5 w-3.5" x-show="!el.locked" />
                                </button>
                                <button class="rounded p-1 text-gray-400 hover:text-gray-700" @click="toggle(el.id, 'hidden')" :title="el.hidden ? 'Show' : 'Hide'" :aria-label="el.hidden ? 'Show layer' : 'Hide layer'">
                                    <x-lucide-eye-off class="h-3.5 w-3.5" x-show="el.hidden" /><x-lucide-eye class="h-3.5 w-3.5" x-show="!el.hidden" />
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p class="px-2 py-6 text-center text-xs text-gray-400" x-show="!elements.length">This side is empty. Add elements from the Elements or Text panels.</p>
                </section>

                {{-- Fields --}}
                <section x-show="tab === 'fields'" class="space-y-4 p-4">
                    <div>
                        <h3 class="editor-heading">Show on canvas</h3>
                        <div class="grid grid-cols-1 gap-1">
                            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="radio" class="text-brand-600" value="customer" x-model="fields" @change="setFields('customer')"> {{ $design->customer ? 'Data of '.$design->customer->full_name : 'Sample details' }}</label>
                            @if ($design->customer)<label class="flex items-center gap-2 text-sm text-gray-700"><input type="radio" class="text-brand-600" value="sample" x-model="fields" @change="setFields('sample')"> Sample details</label>@endif
                            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="radio" class="text-brand-600" value="raw" x-model="fields" @change="setFields('raw')"> Field tags</label>
                        </div>
                        <p class="form-hint">Fields are filled with each customer's details when cards are generated, so one design works for a whole team.</p>
                    </div>
                    <div>
                        <h3 class="editor-heading">Insert a field</h3>
                        <p class="mb-2 text-xs text-gray-500" x-text="isText ? 'Adds the field to the selected text.' : 'Adds a new text box with the field.'"></p>
                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                            @foreach ($state['mergeFields'] as $key => $label)
                                <li><button class="flex w-full items-center justify-between px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50" @click="insertField('{{ $key }}')">{{ $label }} <span class="font-mono text-[10px] text-gray-400" x-text="tag('{{ $key }}')"></span></button></li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </aside>

            {{-- ============================================================ canvas --}}
            <main x-ref="viewport" class="relative min-w-0 flex-1 overflow-auto bg-[radial-gradient(circle,#d1d5db_1px,transparent_1px)] [background-size:18px_18px]"
                  @mousedown.self="deselect()">
                <div class="flex min-h-full min-w-full items-center justify-center p-6 sm:p-10" @mousedown.self="deselect()">
                    <div x-ref="stage" class="relative shrink-0 bg-white shadow-[0_20px_50px_-12px_rgba(15,23,42,0.35)]">
                        <div x-ref="svg" class="pointer-events-none absolute inset-0 select-none [&>svg]:block [&>svg]:h-full [&>svg]:w-full"></div>
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </div>
                <div class="pointer-events-none absolute bottom-3 left-3 hidden rounded-md bg-white/90 px-2 py-1 text-[11px] text-gray-500 shadow-sm sm:block">
                    <span x-text="design.width + ' × ' + design.height + ' mm'"></span>
                    <span x-show="design.showGuides"> · <span class="text-red-500">bleed</span> · <span class="text-sky-500">safe area</span></span>
                </div>
                <div class="pointer-events-none absolute bottom-3 right-3 rounded-md bg-gray-900/80 px-2.5 py-1 text-xs text-white" x-show="notice" x-transition x-text="notice" role="status"></div>
            </main>

            {{-- ============================================================ properties --}}
            <aside class="z-20 w-full max-w-[19rem] shrink-0 flex-col overflow-y-auto border-l border-gray-200 bg-white max-lg:fixed max-lg:right-0 max-lg:top-14 max-lg:bottom-14 max-lg:shadow-xl"
                   :class="mobilePanel === 'right' ? 'flex' : 'hidden lg:flex'" aria-label="Properties">
                {{-- Nothing selected --}}
                <div x-show="!selectedIds.length" class="space-y-5 p-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Card</h2>
                        <p class="mt-1 text-xs text-gray-500">Select an element on the card or in Layers to edit it.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:hidden">
                        <template x-for="s in ['front', 'back']" :key="s">
                            <button class="rounded-md border px-3 py-1.5 text-xs font-semibold capitalize" :class="side === s ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600'" @click="switchSide(s)" x-text="s"></button>
                        </template>
                    </div>
                    <div>
                        <h3 class="editor-heading">Palette</h3>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="key in paletteKeys" :key="key">
                                <label class="relative h-8 w-8 cursor-pointer overflow-hidden rounded-full border border-black/10" :style="`background:${design.palette[key]}`" :title="key">
                                    <input type="color" class="absolute inset-0 cursor-pointer opacity-0" x-model="design.palette[key]" @input="changed({ geometry: false })" :aria-label="'Palette ' + key">
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 text-xs leading-relaxed text-gray-600">
                        <p class="mb-1 font-semibold text-gray-700">Shortcuts</p>
                        <p>Ctrl+Z / Ctrl+Shift+Z: undo, redo</p>
                        <p>Ctrl+D: duplicate · Del: delete</p>
                        <p>Arrows: nudge 0.5 mm (Shift: 5 mm)</p>
                        <p>Ctrl+] / Ctrl+[: forward, backward</p>
                        <p>Shift while resizing: free scale</p>
                    </div>
                </div>

                {{-- Several selected --}}
                <div x-show="selectedIds.length > 1" class="space-y-4 p-4">
                    <h2 class="text-sm font-semibold text-gray-900"><span x-text="selectedIds.length"></span> elements selected</h2>
                    @include('designs.partials.arrange')
                </div>

                {{-- One selected --}}
                <template x-if="selected">
                    <div class="space-y-4 p-4">
                        <div class="flex items-center gap-2">
                            <input x-model="selected.name" @change="changed({ geometry: false })" maxlength="60" class="min-w-0 flex-1 rounded-md border-0 px-1 py-0.5 text-sm font-semibold text-gray-900 hover:bg-gray-50 focus:ring-2 focus:ring-brand-500" aria-label="Element name">
                            <span class="badge-gray capitalize" x-text="selected.type"></span>
                        </div>

                        {{-- Text --}}
                        <template x-if="isText">
                            <div class="space-y-3">
                                <textarea x-ref="textInput" x-model="selected.text" @input.debounce.150ms="textChanged()" rows="3" maxlength="500" class="input-base text-sm" aria-label="Text"></textarea>
                                <select class="select-base py-1.5 text-xs" x-model="selected.fontFamily" @change="changed()" aria-label="Font">
                                    <template x-for="f in fontOptions" :key="f.name">
                                        <option :value="f.name" x-text="f.name + (f.category === 'custom' ? ' (uploaded)' : '')" :selected="f.name === selected.fontFamily"></option>
                                    </template>
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <x-editor.num model="selected.fontSize" label="Size" step="0.5" min="2" max="200" suffix="pt" />
                                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Weight</span>
                                        <select class="select-base py-1.5 text-xs" x-model.number="selected.fontWeight" @change="changed()">
                                            @foreach ($weights as $w => $label)<option value="{{ $w }}">{{ $label }}</option>@endforeach
                                        </select></label>
                                </div>
                                <x-editor.color model="selected.color" label="Colour" />
                                <div class="flex items-center gap-1">
                                    <template x-for="a in ['left', 'center', 'right']" :key="a">
                                        <button class="rounded-md p-1.5" :class="selected.align === a ? 'bg-brand-50 text-brand-700' : 'text-gray-500 hover:bg-gray-100'" @click="selected.align = a; changed({ geometry: false })" :aria-label="'Align ' + a">
                                            <x-lucide-align-left class="h-4 w-4" x-show="a === 'left'" /><x-lucide-align-center class="h-4 w-4" x-show="a === 'center'" /><x-lucide-align-right class="h-4 w-4" x-show="a === 'right'" />
                                        </button>
                                    </template>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button class="rounded-md p-1.5" :class="selected.fontStyle === 'italic' ? 'bg-brand-50 text-brand-700' : 'text-gray-500 hover:bg-gray-100'" @click="selected.fontStyle = selected.fontStyle === 'italic' ? 'normal' : 'italic'; changed()" aria-label="Italic"><x-lucide-italic class="h-4 w-4" /></button>
                                    <button class="rounded-md p-1.5" :class="selected.underline ? 'bg-brand-50 text-brand-700' : 'text-gray-500 hover:bg-gray-100'" @click="selected.underline = !selected.underline; changed({ geometry: false })" aria-label="Underline"><x-lucide-underline class="h-4 w-4" /></button>
                                    <select class="select-base ml-auto w-24 py-1 text-xs" x-model="selected.textTransform" @change="changed()" aria-label="Letter case">
                                        <option value="none">Aa</option><option value="uppercase">AA</option><option value="lowercase">aa</option><option value="capitalize">Title</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <x-editor.num model="selected.letterSpacing" label="Spacing" step="10" min="-200" max="1000" />
                                    <x-editor.num model="selected.lineHeight" label="Line height" step="0.05" min="0.5" max="4" />
                                </div>
                            </div>
                        </template>

                        {{-- Shape --}}
                        <template x-if="selected.type === 'shape'">
                            <div class="space-y-3">
                                <select class="select-base py-1.5 text-xs" x-model="selected.shape" @change="changed({ geometry: false })" aria-label="Shape">
                                    @foreach ($state['shapes'] as $shape)<option value="{{ $shape }}">{{ ucfirst($shape) }}</option>@endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-0.5">
                                    <button class="rounded-md py-1 text-xs font-medium" :class="typeof selected.fill !== 'object' ? 'bg-white shadow-soft' : 'text-gray-500'" @click="setFill('solid')">Solid</button>
                                    <button class="rounded-md py-1 text-xs font-medium" :class="typeof selected.fill === 'object' ? 'bg-white shadow-soft' : 'text-gray-500'" @click="setFill('gradient')">Gradient</button>
                                </div>
                                <template x-if="typeof selected.fill !== 'object'"><x-editor.color model="selected.fill" label="Fill" /></template>
                                <template x-if="typeof selected.fill === 'object'">
                                    <div class="space-y-3">
                                        <template x-for="(stop, i) in selected.fill.stops" :key="i"><x-editor.color model="stop.color" label="Stop" /></template>
                                        <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Angle</span>
                                            <input type="range" min="0" max="360" class="w-full accent-brand-600" x-model.number="selected.fill.angle" @input="changed({ geometry: false })"></label>
                                    </div>
                                </template>
                                <x-editor.color model="selected.stroke" label="Outline" />
                                <div class="grid grid-cols-2 gap-2">
                                    <x-editor.num model="selected.strokeWidth" label="Outline width" step="0.1" min="0" max="20" suffix="mm" :geometry="false" />
                                    <div x-show="selected.shape === 'rect'"><x-editor.num model="selected.radius" label="Corner radius" step="0.5" min="0" max="200" suffix="mm" :geometry="false" /></div>
                                </div>
                            </div>
                        </template>

                        {{-- Line --}}
                        <template x-if="selected.type === 'line'">
                            <div class="space-y-3">
                                <x-editor.color model="selected.stroke" label="Colour" />
                                <div class="grid grid-cols-2 gap-2">
                                    <x-editor.num model="selected.strokeWidth" label="Thickness" step="0.1" min="0.05" max="20" suffix="mm" :geometry="false" />
                                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Style</span>
                                        <select class="select-base py-1.5 text-xs" x-model="selected.dash" @change="changed({ geometry: false })"><option value="solid">Solid</option><option value="dashed">Dashed</option><option value="dotted">Dotted</option></select></label>
                                </div>
                            </div>
                        </template>

                        {{-- Icon --}}
                        <template x-if="selected.type === 'icon'">
                            <div class="space-y-3">
                                <p class="text-xs text-gray-500">Icon: <span class="font-mono" x-text="selected.icon"></span>. Search in Elements and click an icon to swap it.</p>
                                <div class="grid grid-cols-6 gap-1.5">
                                    @foreach ($iconSvgs as $name => $inner)
                                        <button class="editor-tile aspect-square p-0" :class="selected.icon === '{{ $name }}' && 'ring-2 ring-brand-500'" @click="selected.icon = '{{ $name }}'; selected.name = '{{ $name }}'; changed({ geometry: false })" title="{{ $name }}">
                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $inner !!}</svg>
                                        </button>
                                    @endforeach
                                </div>
                                <x-editor.color model="selected.color" label="Colour" />
                                <x-editor.num model="selected.strokeWidth" label="Line weight" step="0.25" min="0.5" max="4" :geometry="false" />
                            </div>
                        </template>

                        {{-- Social --}}
                        <template x-if="selected.type === 'social'">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <select class="select-base py-1.5 text-xs" x-model="selected.network" @change="selected.name = selected.network; changed({ geometry: false })" aria-label="Network">
                                        @foreach ($socialLabels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <select class="select-base py-1.5 text-xs" x-model="selected.style" @change="changed({ geometry: false })" aria-label="Style">
                                        <option value="plain">Plain</option><option value="circle">Circle</option><option value="square">Square</option>
                                    </select>
                                </div>
                                <x-editor.color model="selected.color" label="Icon colour" />
                                <div x-show="selected.style !== 'plain'"><x-editor.color model="selected.background" label="Badge colour" /></div>
                            </div>
                        </template>

                        {{-- Images --}}
                        <template x-if="isImage">
                            <div class="space-y-3">
                                <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Image source</span>
                                    <select class="select-base py-1.5 text-xs" @change="selected.src = $event.target.value === 'upload' ? (assets[0]?.src ?? null) : $event.target.value; if ($event.target.value === 'upload') tab = 'uploads', mobilePanel = window.innerWidth < 1024 ? 'left' : null; changed({ geometry: false })">
                                        <option :value="tag('logo')" :selected="selected.src === tag('logo')">Customer's company logo</option>
                                        <option :value="tag('photo')" :selected="selected.src === tag('photo')">Customer's profile photo</option>
                                        <option value="upload" :selected="selected.src && !selected.src.startsWith('{')">Uploaded image</option>
                                    </select></label>
                                <button class="btn-secondary btn-sm w-full" @click="tab = 'uploads'; mobilePanel = window.innerWidth < 1024 ? 'left' : null"><x-lucide-image-up class="h-3.5 w-3.5" /> Choose from uploads</button>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Fit</span>
                                        <select class="select-base py-1.5 text-xs" x-model="selected.fit" @change="changed({ geometry: false })"><option value="contain">Fit</option><option value="cover">Fill</option></select></label>
                                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">Mask</span>
                                        <select class="select-base py-1.5 text-xs" x-model="selected.mask" @change="changed({ geometry: false })"><option value="none">None</option><option value="circle">Circle</option><option value="rounded">Rounded</option></select></label>
                                    <x-editor.num model="selected.radius" label="Corner radius" step="0.5" min="0" max="100" suffix="mm" :geometry="false" />
                                    <x-editor.num model="selected.border" label="Border" step="0.1" min="0" max="10" suffix="mm" :geometry="false" />
                                </div>
                                <div x-show="selected.border > 0"><x-editor.color model="selected.borderColor" label="Border colour" /></div>
                            </div>
                        </template>

                        {{-- QR --}}
                        <template x-if="selected.type === 'qr'">
                            <div class="space-y-3">
                                <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-gray-500">QR content</span>
                                    <input class="input-base py-1.5 text-xs" x-model="selected.data" @input.debounce.400ms="changed({ geometry: false })" maxlength="1000"></label>
                                <div class="flex flex-wrap gap-1">
                                    <button class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700 hover:bg-gray-200" @click="selected.data = tag('profile_url'); changed({ geometry: false })">Digital profile</button>
                                    <button class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700 hover:bg-gray-200" @click="selected.data = 'https://' + tag('website'); changed({ geometry: false })">Website</button>
                                    <button class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700 hover:bg-gray-200" @click="selected.data = 'mailto:' + tag('email'); changed({ geometry: false })">Email</button>
                                    <button class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700 hover:bg-gray-200" @click="selected.data = 'tel:' + tag('mobile'); changed({ geometry: false })">Phone</button>
                                </div>
                                <x-editor.color model="selected.color" label="Dots" />
                                <x-editor.color model="selected.background" label="Background" />
                                <x-editor.num model="selected.margin" label="Quiet zone (modules)" step="1" min="0" max="10" :geometry="false" />
                                <p class="text-[11px] text-gray-500">Keep strong contrast and a quiet zone of at least 1 so phones can scan it.</p>
                            </div>
                        </template>

                        {{-- Pattern --}}
                        <template x-if="selected.type === 'pattern'">
                            <div class="space-y-3">
                                <select class="select-base py-1.5 text-xs" x-model="selected.pattern" @change="changed({ geometry: false })" aria-label="Pattern">
                                    @foreach ($state['patterns'] as $pattern)<option value="{{ $pattern }}">{{ ucfirst($pattern) }}</option>@endforeach
                                </select>
                                <x-editor.color model="selected.color" label="Colour" />
                                <div class="grid grid-cols-2 gap-2">
                                    <x-editor.num model="selected.spacing" label="Spacing" step="0.5" min="0.5" max="50" suffix="mm" :geometry="false" />
                                    <x-editor.num model="selected.size" label="Size" step="0.05" min="0.05" max="20" suffix="mm" :geometry="false" />
                                </div>
                            </div>
                        </template>

                        {{-- Position & common --}}
                        <div class="space-y-3 border-t border-gray-100 pt-4">
                            <h3 class="editor-heading">Position &amp; size</h3>
                            <div class="grid grid-cols-2 gap-2">
                                <x-editor.num model="selected.x" label="X" suffix="mm" />
                                <x-editor.num model="selected.y" label="Y" suffix="mm" />
                                <x-editor.num model="selected.width" label="Width" min="0.1" suffix="mm" />
                                <div x-show="!isText && selected.type !== 'line'"><x-editor.num model="selected.height" label="Height" min="0.1" suffix="mm" /></div>
                                <x-editor.num model="selected.rotation" label="Rotation" step="1" min="-360" max="360" suffix="°" />
                            </div>
                            <label class="block"><span class="mb-1 flex justify-between text-[11px] font-medium uppercase tracking-wide text-gray-500">Opacity <span x-text="Math.round(selected.opacity * 100) + '%'"></span></span>
                                <input type="range" min="0" max="1" step="0.05" class="w-full accent-brand-600" x-model.number="selected.opacity" @input="changed({ geometry: false })"></label>
                            <div class="flex gap-2">
                                <button class="btn-secondary btn-sm flex-1" @click="toggle(selected.id, 'locked')"><x-lucide-lock class="h-3.5 w-3.5" /> <span x-text="selected.locked ? 'Unlock' : 'Lock'"></span></button>
                                <button class="btn-secondary btn-sm flex-1" @click="toggle(selected.id, 'hidden')"><x-lucide-eye-off class="h-3.5 w-3.5" /> Hide</button>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 pt-4">@include('designs.partials.arrange')</div>
                    </div>
                </template>
            </aside>
        </div>

        {{-- ============================================================ mobile bar --}}
        <nav class="z-30 flex h-14 shrink-0 items-center justify-around border-t border-gray-200 bg-white lg:hidden" aria-label="Editor panels">
            @foreach (['elements' => ['Add', 'plus'], 'uploads' => ['Uploads', 'upload'], 'background' => ['Style', 'paint-bucket'], 'layers' => ['Layers', 'layers']] as $key => [$label, $icon])
                <button class="flex flex-col items-center gap-0.5 px-3 text-[10px] font-medium" :class="mobilePanel === 'left' && tab === '{{ $key }}' ? 'text-brand-700' : 'text-gray-500'"
                        @click="mobilePanel = (mobilePanel === 'left' && tab === '{{ $key }}') ? null : 'left'; tab = '{{ $key }}'">
                    <x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" />{{ $label }}
                </button>
            @endforeach
            <button class="flex flex-col items-center gap-0.5 px-3 text-[10px] font-medium" :class="mobilePanel === 'right' ? 'text-brand-700' : 'text-gray-500'" @click="mobilePanel = mobilePanel === 'right' ? null : 'right'">
                <x-lucide-sliders-horizontal class="h-5 w-5" />Edit
            </button>
        </nav>

        {{-- ============================================================ preview --}}
        <div x-show="previewOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/80 p-4" @click.self="previewOpen = false" role="dialog" aria-modal="true" aria-label="Preview">
            <div class="w-full max-w-2xl">
                <div class="mb-4 flex items-center justify-between text-white">
                    <div class="flex rounded-lg bg-white/10 p-0.5">
                        <template x-for="s in ['front', 'back']" :key="s">
                            <button class="rounded-md px-3 py-1 text-xs font-semibold capitalize" :class="previewSide === s ? 'bg-white text-gray-900' : 'text-white/70'" @click="previewSide = s" x-text="s"></button>
                        </template>
                    </div>
                    <button class="rounded-md p-1.5 text-white/70 hover:bg-white/10 hover:text-white" @click="previewOpen = false" aria-label="Close preview"><x-lucide-x class="h-5 w-5" /></button>
                </div>
                <div class="[perspective:1600px]" @click="previewSide = previewSide === 'front' ? 'back' : 'front'">
                    <div class="relative transition-transform duration-700 [transform-style:preserve-3d]" :style="previewSide === 'back' && 'transform: rotateY(180deg)'">
                        <div class="card-svg overflow-hidden rounded-xl shadow-2xl [backface-visibility:hidden]" x-html="previewSvgs.front"></div>
                        <div class="card-svg absolute inset-0 overflow-hidden rounded-xl shadow-2xl [backface-visibility:hidden] [transform:rotateY(180deg)]" x-html="previewSvgs.back"></div>
                    </div>
                </div>
                <p class="mt-4 text-center text-xs text-white/60">Click the card to flip it. Trimmed size, without bleed and guides.</p>
            </div>
        </div>

        {{-- ============================================================ settings --}}
        <div x-show="settingsOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4" @click.self="settingsOpen = false" role="dialog" aria-modal="true" aria-labelledby="settings-title">
            <div class="card w-full max-w-md" x-data="{ size: '' }">
                <div class="card-header"><h2 id="settings-title" class="card-title">Card settings</h2>
                    <button class="text-gray-400 hover:text-gray-700" @click="settingsOpen = false" aria-label="Close"><x-lucide-x class="h-4 w-4" /></button></div>
                <div class="space-y-4 p-5">
                    <label class="block"><span class="form-label">Status</span>
                        <select class="select-base" x-model="design.status" @change="dirty = true">
                            @foreach ($statuses as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach
                        </select></label>
                    <label class="block"><span class="form-label">Card size</span>
                        <select class="select-base" x-model="size" @change="if (size) { const [w, h] = size.split('x').map(Number); applySize(design.height > design.width ? { width: h, height: w } : { width: w, height: h }); size = '' }">
                            <option value="">Change size…</option>
                            @foreach ($sizes as $s)<option value="{{ $s['width'] }}x{{ $s['height'] }}">{{ $s['label'] }}</option>@endforeach
                        </select>
                        <span class="form-hint">Current: <span x-text="design.width + ' × ' + design.height + ' mm'"></span>. Elements keep their positions.</span></label>
                    <button class="btn-secondary btn-sm" @click="applySize({ width: design.height, height: design.width })"><x-lucide-rotate-cw-square class="h-3.5 w-3.5" /> Switch to <span x-text="design.width > design.height ? 'portrait' : 'landscape'"></span></button>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block"><span class="form-label">Bleed (mm)</span>
                            <input type="number" step="0.5" min="0" max="6" class="input-base" x-model.number="design.bleed" @change="applySize({ width: design.width, height: design.height })"></label>
                        <label class="block"><span class="form-label">Corner radius (mm)</span>
                            <input type="number" step="0.5" min="0" max="10" class="input-base" x-model.number="design.radius" @change="changed({ geometry: false })"></label>
                    </div>
                    <p class="text-xs text-gray-500">Bleed is the extra background printed beyond the trim line (3 mm is standard). Keep text inside the blue safe area.</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
                    <button class="btn-primary" @click="settingsOpen = false; save()">Save changes</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.editor>
