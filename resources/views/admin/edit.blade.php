@extends('layouts.app')

@section('title', __('messages.edit_image_heading').' — '.config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold text-white">{{ __('messages.edit_image_heading') }}</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mt-4 bg-neutral-900 border border-neutral-800 rounded p-3 inline-block">
        <img src="{{ $image->public_url }}" alt="" class="max-h-48 w-auto bg-black">
    </div>

    <form method="post" action="{{ route('admin.images.update', ['uuid' => $image->uuid]) }}"
          class="mt-6 space-y-4 bg-neutral-900 border border-neutral-800 rounded p-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm text-neutral-300 mb-1">{{ __('messages.description') }}</label>
            <textarea name="description" rows="5" maxlength="5000"
                      class="w-full bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">{{ old('description', $image->description) }}</textarea>
        </div>

        <div>
            <label class="block text-sm text-neutral-300 mb-1">{{ __('messages.tags') }}</label>
            <input type="text" name="tags" value="{{ old('tags', $tagList) }}"
                   class="w-full bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm"
                   placeholder="{{ __('messages.tags_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm text-neutral-300 mb-1">{{ __('messages.source_links') }}</label>
            <div id="sources" class="space-y-2"></div>
            <button type="button" id="add-source"
                    class="mt-2 text-xs bg-neutral-800 hover:bg-neutral-700 border border-neutral-700 text-neutral-200 px-2 py-1 rounded">
                {{ __('messages.add_source') }}
            </button>
        </div>

        <div class="pt-2 flex gap-3">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-medium px-4 py-2 rounded">
                {{ __('messages.save_changes') }}
            </button>
            <a href="{{ route('image.show', ['uuid' => $image->uuid]) }}"
               class="text-neutral-400 hover:text-white px-3 py-2 text-sm">
                {{ __('messages.cancel') }}
            </a>
        </div>
    </form>

    <script>
        (function () {
            const wrap = document.getElementById('sources');
            const addBtn = document.getElementById('add-source');
            const labelPlaceholder = @json(__('messages.label_optional'));
            const existing = @json($image->sources->map(fn ($s) => ['label' => $s->label, 'url' => $s->url])->all());
            let i = 0;
            function row(prefill) {
                const idx = i++;
                const div = document.createElement('div');
                div.className = 'flex gap-2';
                const labelInput = document.createElement('input');
                labelInput.type = 'text';
                labelInput.name = `sources[${idx}][label]`;
                labelInput.placeholder = labelPlaceholder;
                labelInput.className = 'w-1/3 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm';
                if (prefill && prefill.label) labelInput.value = prefill.label;
                const urlInput = document.createElement('input');
                urlInput.type = 'url';
                urlInput.name = `sources[${idx}][url]`;
                urlInput.placeholder = 'https://…';
                urlInput.className = 'flex-1 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm';
                if (prefill && prefill.url) urlInput.value = prefill.url;
                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'text-xs text-red-400 px-2';
                rm.textContent = '×';
                rm.addEventListener('click', () => div.remove());
                div.append(labelInput, urlInput, rm);
                wrap.appendChild(div);
            }
            addBtn.addEventListener('click', () => row());
            if (existing.length) {
                existing.forEach(s => row(s));
            } else {
                row();
            }
        })();
    </script>
@endsection
