@php
    use App\Services\CardExportService;
    $w = (float) $design->width_mm;
    $h = (float) $design->height_mm;
@endphp
<x-layouts.app :title="'Export · '.$design->name">
    <x-page-header :title="'Export '.$design->name" :description="number_format($w, 1).' × '.number_format($h, 1).' mm · '.ucfirst($design->orientation).($design->customer ? ' · '.$design->customer->full_name : '')">
        <x-slot:actions>
            <a href="{{ route('designs.edit', $design) }}" class="btn-secondary"><x-lucide-pen-tool class="h-4 w-4" /> Open editor</a>
        </x-slot:actions>
    </x-page-header>

    @unless ($chrome)
        <div class="mb-6 flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
            <x-lucide-circle-alert class="mt-0.5 h-4 w-4 shrink-0" />
            <p>PNG and PDF need headless Chrome on the server, which isn't installed. SVG export works. See "Exports" in the README to enable the others.</p>
        </div>
    @endunless

    <div class="grid gap-6 xl:grid-cols-[1fr_400px]">
        <form method="POST" action="{{ route('designs.export.store', $design) }}" class="card"
              x-data="{
                  format: @js(old('format', $chrome ? 'pdf' : 'svg')),
                  dpi: @js((int) old('dpi', 300)),
                  bleed: @js((float) old('bleed_mm', 3)),
                  marks: @js((bool) old('crop_marks', true)),
                  sides: @js(old('sides', 'both')),
                  w: {{ $w }}, h: {{ $h }},
                  get pageW() { return this.w + 2 * this.bleed + (this.marks && this.format !== 'svg' ? 16 : 0) },
                  get pageH() { return this.h + 2 * this.bleed + (this.marks && this.format !== 'svg' ? 16 : 0) },
                  get px() { return Math.round(this.pageW / 25.4 * this.dpi) + ' × ' + Math.round(this.pageH / 25.4 * this.dpi) + ' px' },
              }">
            @csrf
            <div class="card-body space-y-6">
                <fieldset>
                    <legend class="form-label">Format</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            'pdf' => ['Print-ready PDF', 'Vector, one page per side, trim and bleed boxes set. Send this to your printer.', 'file-text'],
                            'png' => ['PNG image', 'High-resolution image at 150–600 DPI. Good for proofs and online printers.', 'image'],
                            'svg' => ['SVG vector', 'Editable vector file with fonts and images embedded.', 'pen-tool'],
                        ] as $key => [$label, $hint, $icon])
                            <label class="cursor-pointer rounded-xl border border-gray-200 p-4 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20 {{ ! $chrome && $key !== 'svg' ? 'pointer-events-none opacity-50' : '' }}">
                                <input type="radio" name="format" value="{{ $key }}" x-model="format" class="sr-only" @disabled(! $chrome && $key !== 'svg')>
                                <span class="flex items-center gap-2 text-sm font-semibold text-gray-900"><x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4 text-brand-600" /> {{ $label }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ $hint }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('format')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="sides">Sides</label>
                        <select id="sides" name="sides" x-model="sides" class="select-base">
                            @foreach (CardExportService::SIDES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                        <p class="form-hint" x-show="sides === 'both' && format !== 'pdf'">Both sides download together as a ZIP.</p>
                    </div>
                    <div x-show="format === 'png'">
                        <label class="form-label" for="dpi">Resolution</label>
                        <select id="dpi" name="dpi" x-model.number="dpi" class="select-base">
                            <option value="150">150 DPI (draft)</option>
                            <option value="300">300 DPI (print)</option>
                            <option value="600">600 DPI (fine print)</option>
                        </select>
                        <p class="form-hint">Each side: <span x-text="px"></span></p>
                    </div>
                    <div>
                        <label class="form-label" for="bleed">Bleed</label>
                        <div class="flex items-center gap-2">
                            <input id="bleed" type="range" min="0" max="{{ CardExportService::MAX_BLEED }}" step="0.5" name="bleed_mm" x-model.number="bleed" class="flex-1 accent-brand-600">
                            <span class="w-14 text-right text-sm tabular-nums text-gray-700" x-text="bleed.toFixed(1) + ' mm'"></span>
                        </div>
                        <p class="form-hint">Background that extends past the cut line. Most printers ask for 2–3 mm. Use 0 for screen images.</p>
                    </div>
                    <div>
                        <label class="form-label" for="radius">Corner radius</label>
                        <input id="radius" type="number" name="corner_radius_mm" min="0" max="10" step="0.5" value="{{ old('corner_radius_mm', (float) $design->corner_radius_mm) }}" class="input-base">
                        <p class="form-hint">Used for rounded corners and guides. Printers cut corners with a die, so leave the artwork square for print.</p>
                    </div>
                </div>

                <fieldset class="space-y-3">
                    <legend class="form-label">Options</legend>
                    <label class="flex items-start gap-3" x-show="format !== 'svg'">
                        <input type="hidden" name="crop_marks" value="0"><input type="checkbox" name="crop_marks" value="1" x-model="marks" class="checkbox-base mt-0.5">
                        <span><span class="block text-sm font-medium text-gray-900">Crop marks</span><span class="block text-xs text-gray-500">Adds 8 mm around the card with trim marks and a job label.</span></span>
                    </label>
                    <label class="flex items-start gap-3" x-show="format !== 'pdf'">
                        <input type="hidden" name="transparent" value="0"><input type="checkbox" name="transparent" value="1" class="checkbox-base mt-0.5" @checked(old('transparent'))>
                        <span><span class="block text-sm font-medium text-gray-900">Transparent background</span><span class="block text-xs text-gray-500">Leaves out the card background, e.g. for printing on coloured or metal cards.</span></span>
                    </label>
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="round_corners" value="0"><input type="checkbox" name="round_corners" value="1" class="checkbox-base mt-0.5" @checked(old('round_corners'))>
                        <span><span class="block text-sm font-medium text-gray-900">Round the corners</span><span class="block text-xs text-gray-500">Clips the artwork to the corner radius (for screen use and proofs).</span></span>
                    </label>
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="guides" value="0"><input type="checkbox" name="guides" value="1" class="checkbox-base mt-0.5" @checked(old('guides'))>
                        <span><span class="block text-sm font-medium text-gray-900">Show bleed and safe-area guides</span><span class="block text-xs text-gray-500">For proofs only. Don't send guides to print.</span></span>
                    </label>
                    <label class="flex items-start gap-3" x-show="format === 'pdf'">
                        <input type="hidden" name="cmyk" value="0"><input type="checkbox" name="cmyk" value="1" class="checkbox-base mt-0.5" @checked(old('cmyk')) @disabled(! $ghostscript)>
                        <span><span class="block text-sm font-medium text-gray-900">Convert to CMYK</span><span class="block text-xs text-gray-500">{{ $ghostscript ? 'Converts colours to CMYK for offset printing. Colours can look slightly duller.' : 'Needs Ghostscript on the server, which isn\'t installed.' }}</span></span>
                    </label>
                    @error('cmyk')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>

                <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    Output page: <span class="font-semibold tabular-nums text-gray-900" x-text="pageW.toFixed(1) + ' × ' + pageH.toFixed(1) + ' mm'"></span>
                    · finished card {{ number_format($w, 1) }} × {{ number_format($h, 1) }} mm
                </p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                <a href="{{ route('exports.index') }}" class="btn-secondary">My exports</a>
                <button class="btn-primary"><x-lucide-download class="h-4 w-4" /> Export</button>
            </div>
        </form>

        <aside class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Saved design</h2></div>
                <div class="card-body space-y-4 bg-gray-50">
                    <div><p class="mb-1.5 text-xs font-medium text-gray-500">Front</p><x-card-preview :svg="$preview['front']" /></div>
                    <div><p class="mb-1.5 text-xs font-medium text-gray-500">Back</p><x-card-preview :svg="$preview['back']" /></div>
                </div>
            </div>
            @if ($recent->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Your recent exports</h2><a href="{{ route('exports.index') }}" class="text-xs font-semibold text-brand-600">All</a></div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($recent as $e)
                            <li class="flex items-center justify-between px-5 py-2.5">
                                <span>{{ strtoupper($e->format) }} · {{ $e->created_at->diffForHumans() }}</span>
                                @if ($e->status === 'completed' && $e->file_path)
                                    <a href="{{ route('exports.download', $e) }}" class="font-semibold text-brand-600">Download</a>
                                @else
                                    <span class="text-xs text-gray-500">{{ ucfirst($e->status) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.app>
