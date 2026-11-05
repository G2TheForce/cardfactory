<form method="POST" action="{{ route('imports.export', $import) }}" class="card"
      x-data="{ kind: @js(old('kind', 'zip')), format: @js(old('format', 'pdf')) }">
    @csrf
    <div class="card-header"><div><h2 class="font-semibold text-gray-900">Download all cards</h2><p class="text-sm text-gray-500">Rendered in the background at print size. You'll find the file on the Exports page.</p></div></div>
    <div class="card-body grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <fieldset class="space-y-2">
            <legend class="form-label">What to make</legend>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="kind" value="zip" x-model="kind" class="h-4 w-4 border-gray-300 text-brand-600"> ZIP with a file per card</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="kind" value="combined" x-model="kind" class="h-4 w-4 border-gray-300 text-brand-600"> One combined PDF</label>
            @error('kind')<p class="form-error">{{ $message }}</p>@enderror
        </fieldset>
        <div x-show="kind === 'zip'">
            <label for="format" class="form-label">File type</label>
            <select id="format" name="format" class="select-base" x-model="format">
                @foreach (\App\Services\CardExportService::FORMATS as $key => $label)
                    <option value="{{ $key }}" @disabled($key !== 'svg' && ! $chrome)>{{ $label }}</option>
                @endforeach
            </select>
            @error('format')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="sides" class="form-label">Sides</label>
            <select id="sides" name="sides" class="select-base">
                @foreach (\App\Services\CardExportService::SIDES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <div x-show="kind === 'zip' && format === 'png'">
            <label for="dpi" class="form-label">Resolution</label>
            <select id="dpi" name="dpi" class="select-base">
                @foreach (\App\Services\CardExportService::DPIS as $dpi)<option value="{{ $dpi }}" @selected($dpi === 300)>{{ $dpi }} DPI</option>@endforeach
            </select>
        </div>
        <div>
            <label for="bleed_mm" class="form-label">Bleed (mm)</label>
            <input id="bleed_mm" name="bleed_mm" type="number" min="0" max="{{ \App\Services\CardExportService::MAX_BLEED }}" step="0.5" value="{{ old('bleed_mm', 3) }}" class="input-base">
            @error('bleed_mm')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="space-y-2 md:col-span-2">
            <label class="flex items-center gap-2 text-sm" x-show="kind === 'combined' || format !== 'svg'">
                <input type="hidden" name="crop_marks" value="0"><input type="checkbox" name="crop_marks" value="1" class="checkbox-base"> Crop marks
            </label>
            <label class="flex items-center gap-2 text-sm" x-show="kind === 'combined' || format === 'pdf'">
                <input type="hidden" name="cmyk" value="0"><input type="checkbox" name="cmyk" value="1" class="checkbox-base" @disabled(! $ghostscript)> Convert to CMYK
                @unless ($ghostscript)<span class="text-xs text-gray-400">(needs Ghostscript on the server)</span>@endunless
            </label>
            @error('cmyk')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>
    @unless ($chrome)
        <p class="px-5 pb-4 text-sm text-amber-700">PNG and PDF need headless Chrome on the server. SVG works now.</p>
    @endunless
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4">
        <div class="text-xs text-gray-500">
            @if ($recentExports->isNotEmpty())
                Recent: @foreach ($recentExports as $e)<span class="mr-2">{{ ! empty($e->options['combined']) ? 'Combined PDF' : 'ZIP '.strtoupper($e->options['format'] ?? '') }} · {{ ucfirst($e->status) }}</span>@endforeach
                <a href="{{ route('exports.index') }}" class="text-brand-700 hover:underline">Open Exports</a>
            @endif
        </div>
        <button class="btn-primary"><x-lucide-download class="h-4 w-4" /> Create download</button>
    </div>
</form>
