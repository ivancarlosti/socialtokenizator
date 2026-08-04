@extends('layouts.app')

@section('title', __('messages.edit_image_heading').' — '.$siteTitle)

@section('content')
    <h1 class="text-2xl font-semibold text-copy">{{ __('messages.edit_image_heading') }}</h1>

    @if($errors->any())
        <div class="mt-4 bg-red-900/30 border border-red-700 text-red-200 rounded px-4 py-2 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mt-4 bg-card border border-card-border rounded p-3 inline-block">
        <img src="{{ $image->public_url }}" alt="" class="max-h-48 w-auto bg-black">
    </div>

    <form method="post" action="{{ route('admin.images.update', ['uuid' => $image->uuid]) }}"
          class="mt-6 space-y-4 bg-card border border-card-border rounded p-5">
        @csrf
        @method('PUT')

        {{-- Per-locale headlines & descriptions --}}
        @foreach(\App\Support\Locales::supported() as $code => $info)
            @php
                $colH = 'headline_'.str_replace('-', '_', $code);
                $colD = 'description_'.str_replace('-', '_', $code);
            @endphp
            <div class="border border-card-border rounded p-3">
                <p class="text-xs font-semibold text-muted mb-2">{{ $info['name'] }} ({{ $code }})</p>
                <div class="space-y-2">
                    <div>
                        <label class="block text-xs text-muted mb-1">{{ __('messages.headline') }}</label>
                        <input type="text" name="{{ $colH }}" value="{{ old($colH, $image->$colH) }}"
                               maxlength="300"
                               class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                               placeholder="{{ __('messages.headline_help') }}">
                    </div>
                    <div>
                        <label class="block text-xs text-muted mb-1">{{ __('messages.description') }}</label>
                        <textarea name="{{ $colD }}" rows="4" maxlength="5000"
                                  class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy desc-field"
                                  data-locale="{{ $code }}">{{ old($colD, $image->$colD) }}</textarea>
                        <button type="button" class="ai-translate-link text-xs text-accent mt-1 inline-block"
                                data-target="{{ $colD }}"
                                data-target-locale="{{ $code }}">
                            {{ __('messages.translate_with_ai') }}
                        </button>
                        <span class="ai-translate-status text-xs text-muted ml-2 hidden"></span>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Categories (multi-select checkboxes) --}}
        @if($categories->isNotEmpty())
            <div>
                <label class="block text-sm text-muted mb-2">{{ __('messages.categories') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach($categories as $cat)
                        <label class="flex items-center gap-2 text-sm text-copy">
                            <input type="checkbox" name="categories[]" value="{{ $cat->id }}"
                                   {{ in_array($cat->id, old('categories', $selectedCategories)) ? 'checked' : '' }}>
                            {{ $cat->getName($currentLocale) }}
                            <span class="text-xs text-muted">({{ $cat->handle }})</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <label class="block text-sm text-muted mb-1">{{ __('messages.tags') }}</label>
            <input type="text" name="tags" value="{{ old('tags', $tagList) }}"
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

        <div class="pt-2 flex gap-3">
            <button type="submit" class="bg-accent hover:bg-accent-hover text-white font-medium px-4 py-2 rounded">
                {{ __('messages.save_changes') }}
            </button>
            <a href="{{ route('image.show', ['uuid' => $image->uuid]) }}"
               class="text-muted hover:text-copy px-3 py-2 text-sm">
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

            // AI Translate
            document.querySelectorAll('.ai-translate-link').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const targetName = this.dataset.target;
                    const targetLocale = this.dataset.targetLocale;
                    const targetField = document.querySelector(`[name="${targetName}"]`);
                    const statusEl = this.nextElementSibling;

                    // Find the best source: any non-empty description from another locale
                    let sourceText = '';
                    const allDescs = document.querySelectorAll('.desc-field');
                    for (const desc of allDescs) {
                        if (desc.name !== targetName && desc.value.trim()) {
                            sourceText = desc.value.trim();
                            break;
                        }
                    }
                    if (!sourceText) {
                        alert(@json(__('messages.translate_no_source')));
                        return;
                    }

                    this.classList.add('hidden');
                    statusEl.classList.remove('hidden');
                    statusEl.textContent = @json(__('messages.translating'));

                    try {
                        const resp = await fetch('{{ route('admin.translate') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                text: sourceText,
                                target_locale: targetLocale,
                            }),
                        });

                        const data = await resp.json();
                        if (data.translated_text) {
                            targetField.value = data.translated_text;
                            statusEl.textContent = @json(__('messages.translate_done'));
                        } else {
                            statusEl.textContent = data.error || @json(__('messages.translate_error'));
                        }
                    } catch (e) {
                        statusEl.textContent = @json(__('messages.translate_error'));
                    }

                    this.classList.remove('hidden');
                    setTimeout(() => {
                        statusEl.classList.add('hidden');
                        statusEl.textContent = '';
                    }, 3000);
                });
            });
        })();
    </script>
@endsection
