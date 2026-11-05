<div class="space-y-3">
    <div>
        <h3 class="editor-heading" x-text="selectedIds.length > 1 ? 'Align to each other' : 'Align to card'"></h3>
        <div class="grid grid-cols-6 gap-1">
            @foreach (['left' => 'align-start-vertical', 'center' => 'align-center-vertical', 'right' => 'align-end-vertical', 'top' => 'align-start-horizontal', 'middle' => 'align-center-horizontal', 'bottom' => 'align-end-horizontal'] as $edge => $icon)
                <button class="grid place-items-center rounded-md py-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900" @click="align('{{ $edge }}')" title="Align {{ $edge }}" aria-label="Align {{ $edge }}">
                    <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4" />
                </button>
            @endforeach
        </div>
    </div>
    <div>
        <h3 class="editor-heading">Arrange</h3>
        <div class="grid grid-cols-4 gap-1">
            @foreach (['front' => ['bring-to-front', 'To front'], 'forward' => ['arrow-up', 'Forward'], 'backward' => ['arrow-down', 'Backward'], 'back' => ['send-to-back', 'To back']] as $where => [$icon, $label])
                <button class="flex flex-col items-center gap-0.5 rounded-md py-1.5 text-[10px] text-gray-500 hover:bg-gray-100 hover:text-gray-900" @click="arrange('{{ $where }}')" title="{{ $label }}">
                    <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4" />{{ $label }}
                </button>
            @endforeach
        </div>
    </div>
    <div class="flex gap-2">
        <button class="btn-secondary btn-sm flex-1" @click="duplicate()"><x-lucide-copy class="h-3.5 w-3.5" /> Duplicate</button>
        <button class="btn-secondary btn-sm flex-1 text-red-600 hover:bg-red-50" @click="remove()"><x-lucide-trash-2 class="h-3.5 w-3.5" /> Delete</button>
    </div>
</div>
