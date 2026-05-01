@extends('layouts.app')

@section('title', 'Upload — '.config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold text-white">Upload image</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.upload.store') }}" enctype="multipart/form-data"
          class="mt-6 space-y-4 bg-neutral-900 border border-neutral-800 rounded p-5">
        @csrf

        <div>
            <label class="block text-sm text-neutral-300 mb-1">Image (jpg, png, webp, gif — max 10 MB)</label>
            <input type="file" name="image" required accept="image/jpeg,image/png,image/webp,image/gif"
                   class="block w-full text-sm text-neutral-300">
        </div>

        <div>
            <label class="block text-sm text-neutral-300 mb-1">Description</label>
            <textarea name="description" rows="3" maxlength="5000"
                      class="w-full bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block text-sm text-neutral-300 mb-1">Tags (comma-separated)</label>
            <input type="text" name="tags" value="{{ old('tags') }}"
                   class="w-full bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm"
                   placeholder="e.g. nature, landscape, sunset">
        </div>

        <div>
            <label class="block text-sm text-neutral-300 mb-1">Source links</label>
            <div id="sources" class="space-y-2"></div>
            <button type="button" id="add-source"
                    class="mt-2 text-xs bg-neutral-800 hover:bg-neutral-700 border border-neutral-700 text-neutral-200 px-2 py-1 rounded">
                + Add source
            </button>
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-medium px-4 py-2 rounded">
                Upload
            </button>
        </div>
    </form>

    <script>
        (function () {
            const wrap = document.getElementById('sources');
            const addBtn = document.getElementById('add-source');
            let i = 0;
            function row() {
                const idx = i++;
                const div = document.createElement('div');
                div.className = 'flex gap-2';
                div.innerHTML = `
                    <input type="text" name="sources[${idx}][label]" placeholder="Label (optional)"
                           class="w-1/3 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">
                    <input type="url" name="sources[${idx}][url]" placeholder="https://…"
                           class="flex-1 bg-neutral-950 border border-neutral-700 rounded px-3 py-2 text-sm">
                    <button type="button" class="text-xs text-red-400 px-2"
                            onclick="this.parentElement.remove()">×</button>
                `;
                wrap.appendChild(div);
            }
            addBtn.addEventListener('click', row);
            row();
        })();
    </script>
@endsection
