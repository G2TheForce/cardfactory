<x-layouts.app title="New bulk run">
    <x-page-header title="New bulk run" description="Upload a spreadsheet with one customer per row. You'll match the columns and check every row before anything is created." />

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="card lg:col-span-2" x-data="{ name: '' }">
            @csrf
            <div class="card-body space-y-5">
                <label for="file" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center transition hover:border-brand-400 hover:bg-brand-50/40">
                    <x-lucide-file-spreadsheet class="h-10 w-10 text-brand-500" />
                    <span class="mt-3 text-sm font-semibold text-gray-900" x-text="name || 'Choose a CSV or Excel file'"></span>
                    <span class="mt-1 text-xs text-gray-500">.csv, .xlsx, .xls or .ods · up to {{ number_format($maxRows) }} rows · {{ Illuminate\Support\Number::fileSize($maxKb * 1024) }}</span>
                    <input id="file" name="file" type="file" required class="sr-only" accept=".csv,.txt,.xlsx,.xls,.ods" @change="name = $event.target.files[0]?.name || ''">
                </label>
                @error('file')<p class="form-error">{{ $message }}</p>@enderror
                <p class="text-sm text-gray-600">The first row must hold column names. Common names such as "Name", "Phone", "Job title" and "Company" are matched automatically.</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                <a href="{{ route('imports.index') }}" class="btn-secondary">Cancel</a>
                <button class="btn-primary"><x-lucide-upload class="h-4 w-4" /> Upload and continue</button>
            </div>
        </form>

        <div class="card">
            <div class="card-body space-y-4">
                <h2 class="font-semibold text-gray-900">Start from our template</h2>
                <p class="text-sm text-gray-600">Every column the generator understands, with an example row. Delete the columns you don't need.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('imports.template', 'xlsx') }}" class="btn-secondary btn-sm"><x-lucide-file-spreadsheet class="h-4 w-4" /> Excel template</a>
                    <a href="{{ route('imports.template', 'csv') }}" class="btn-secondary btn-sm"><x-lucide-file-text class="h-4 w-4" /> CSV template</a>
                </div>
                <details class="text-sm">
                    <summary class="cursor-pointer font-medium text-gray-700">Supported columns</summary>
                    <ul class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-gray-600">
                        @foreach ($fields as $key => $field)<li>{{ $field['label'] }}@if ($key === 'full_name') <span class="text-red-500">*</span>@endif</li>@endforeach
                    </ul>
                </details>
            </div>
        </div>
    </div>
</x-layouts.app>
