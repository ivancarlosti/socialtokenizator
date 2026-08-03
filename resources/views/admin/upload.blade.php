@extends('layouts.app')

@section('title', __('messages.upload_heading').' — '.config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold text-copy">{{ __('messages.upload_heading') }}</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.upload.store') }}" enctype="multipart/form-data"
          class="mt-6 space-y-4 bg-card border border-card-border rounded p-5">
        @csrf

        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.upload_image_label') }}</label>
            <input type="file" name="image" required accept="image/jpeg,image/png,image/webp,image/gif"
                   class="block w-full text-sm text-muted">
        </div>

        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.description') }}</label>
            <textarea name="description" rows="3" maxlength="5000"
                      class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.tags') }}</label>
            <input type="text" name="tags" value="{{ old('tags') }}"
                   class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                   placeholder="{{ __('messages.tags_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.source_links') }}</label>
            <div id="sources" class="space-y-2"></div>
            <button type="button" id="add-source"
                    class="mt-2 text-xs bg-neutral-800 hover:bg-neutral-700 border border-neutral-700 text-neutral-200 px-2 py-1 rounded">
                {{ __('messages.add_source') }}
            </button>
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-accent hover:bg-accent-hover text-white font-medium px-4 py-2 rounded">
                {{ __('messages.submit_upload') }}
            </button>
        </div>
    </form>

    <script>
        (function () {
            const wrap = document.getElementById('sources');
            const addBtn = document.getElementById('add-source');
            const labelPlaceholder = @json(__('messages.label_optional'));
            let i = 0;
            function row() {
                const idx = i++;
                const div = document.createElement('div');
                div.className = 'flex gap-2';
                const labelInput = document.createElement('input');
                labelInput.type = 'text';
                labelInput.name = `sources[${idx}][label]`;
                labelInput.placeholder = labelPlaceholder;
                labelInput.className = 'w-1/3 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm';
                const urlInput = document.createElement('input');
                urlInput.type = 'url';
                urlInput.name = `sources[${idx}][url]`;
                urlInput.placeholder = 'https://…';
                urlInput.className = 'flex-1 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm';
                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'text-xs text-red-400 px-2';
                rm.textContent = '×';
                rm.addEventListener('click', () => div.remove());
                div.append(labelInput, urlInput, rm);
                wrap.appendChild(div);
            }
            addBtn.addEventListener('click', row);
            row();
        })();
    </script>
@endsection
