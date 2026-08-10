@extends('layouts.app')

@section('title', __('messages.upload_heading').' — '.$siteTitle)

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
            <input type="file" name="image" id="image-file-input" accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
                   class="block w-full text-sm text-muted">
            <p class="text-xs text-muted mt-2 mb-1">— {{ __('messages.or') }} —</p>
            <input type="url" name="image_url" id="image-url-input" value="{{ old('image_url') }}"
                   class="block w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                   placeholder="https://example.com/photo.jpg">
            <p class="text-xs text-muted mt-1">{{ __('messages.upload_image_url_help') }}</p>
            <p class="text-xs text-muted mt-2 mb-1">— {{ __('messages.or') }} —</p>
            <div id="paste-zone" class="paste-zone" tabindex="0">
                <span id="paste-zone-text" class="text-sm text-muted">{{ __('messages.paste_image_placeholder') }}</span>
                <div id="paste-preview" class="paste-preview hidden mt-2">
                    <img id="paste-preview-img" alt="{{ __('messages.new_image_preview') }}">
                    <button type="button" id="paste-clear-btn" class="clear-preview" title="{{ __('messages.clear_image') }}">×</button>
                </div>
            </div>
        </div>

        {{-- AI Generate input --}}
        <div class="border border-card-border rounded p-4 bg-card-hover/20">
            <label class="block text-sm text-muted mb-2">{{ __('messages.generate_with_ai') }}</label>
            <textarea id="ai-generate-input" rows="6" maxlength="10000"
                      class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy"
                      placeholder="{{ __('messages.generate_paste_placeholder') }}"></textarea>
            <div class="flex items-center gap-2 mt-2">
                <button type="button" id="ai-generate-btn"
                        class="bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-2 rounded">
                    {{ __('messages.generate_with_ai') }}
                </button>
                <span id="ai-generate-status" class="text-xs text-muted hidden"></span>
            </div>
        </div>

        {{-- Per-locale headlines & descriptions --}}
        @foreach(\App\Support\Locales::supported() as $code => $info)
            @php
                $colH = 'headline_'.str_replace('-', '_', $code);
                $colD = 'description_'.str_replace('-', '_', $code);
                $allLocales = \App\Support\Locales::supported();
                $otherLocales = array_filter($allLocales, fn($l, $k) => $k !== $code, ARRAY_FILTER_USE_BOTH);
            @endphp
            <div class="border border-card-border rounded p-3">
                <p class="text-xs font-semibold text-muted mb-2">{{ $info['name'] }}</p>
                <div class="space-y-2">
                    <div>
                        <label class="block text-xs text-muted mb-1">{{ __('messages.headline') }}</label>
                        <input type="text" name="{{ $colH }}" value="{{ old($colH) }}"
                               maxlength="300"
                               class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy headline-field"
                               data-locale="{{ $code }}"
                               placeholder="{{ __('messages.headline_help') }}">
                        <div class="flex flex-wrap items-center gap-1 mt-1">
                            @foreach($otherLocales as $srcCode => $srcInfo)
                                <button type="button" class="ai-translate-link text-xs text-accent inline-block"
                                        data-target="{{ $colH }}"
                                        data-target-locale="{{ $code }}"
                                        data-source-locale="{{ $srcCode }}"
                                        data-field-type="headline">
                                    {{ __('messages.translate_with_ai_from', ['locale' => $srcInfo['name']]) }}
                                </button>
                            @endforeach
                            <span class="ai-translate-status text-xs text-muted ml-2 hidden"></span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-muted mb-1">{{ __('messages.description') }}</label>
                        <textarea name="{{ $colD }}" rows="3" maxlength="5000"
                                  class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy desc-field"
                                  data-locale="{{ $code }}">{{ old($colD) }}</textarea>
                        <div class="flex flex-wrap items-center gap-1 mt-1">
                            @foreach($otherLocales as $srcCode => $srcInfo)
                                <button type="button" class="ai-translate-link text-xs text-accent inline-block"
                                        data-target="{{ $colD }}"
                                        data-target-locale="{{ $code }}"
                                        data-source-locale="{{ $srcCode }}"
                                        data-field-type="description">
                                    {{ __('messages.translate_with_ai_from', ['locale' => $srcInfo['name']]) }}
                                </button>
                            @endforeach
                            <span class="ai-translate-status text-xs text-muted ml-2 hidden"></span>
                        </div>
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
                                   {{ in_array($cat->id, old('categories', [])) ? 'checked' : '' }}>
                            {{ $cat->getName($currentLocale) }}
                            <span class="text-xs text-muted">({{ $cat->handle }})</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

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
                    class="mt-2 text-xs bg-input border border-input-border rounded px-2 py-1 text-copy hover:border-card-border-hover">
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
        // ── Clipboard paste support ──
        (function () {
            const fileInput = document.getElementById('image-file-input');
            const urlInput = document.getElementById('image-url-input');
            const pasteZone = document.getElementById('paste-zone');
            const pasteZoneText = document.getElementById('paste-zone-text');
            const pastePreview = document.getElementById('paste-preview');
            const pastePreviewImg = document.getElementById('paste-preview-img');
            const pasteClearBtn = document.getElementById('paste-clear-btn');

            function setPastedFile(file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                // Clear URL input since we're using a file
                if (urlInput) urlInput.value = '';

                // Show preview
                const reader = new FileReader();
                reader.onload = function (e) {
                    pastePreviewImg.src = e.target.result;
                    pastePreview.classList.remove('hidden');
                    pasteZoneText.classList.add('hidden');
                    pasteZone.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            }

            function clearPastedImage() {
                fileInput.value = '';
                pastePreviewImg.removeAttribute('src');
                pastePreview.classList.add('hidden');
                pasteZoneText.classList.remove('hidden');
                pasteZone.classList.remove('has-image');
            }

            // Handle paste event on the document
            document.addEventListener('paste', function (e) {
                // Don't intercept paste into text inputs/textareas
                const target = e.target;
                if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
                    // But if it's the paste zone or file input itself, handle it
                    if (target !== pasteZone && target !== fileInput) return;
                }

                const items = e.clipboardData?.items;
                if (!items) return;

                for (const item of items) {
                    if (item.type.match(/^image\/(jpeg|png|webp|gif|avif)$/)) {
                        e.preventDefault();
                        const blob = item.getAsFile();
                        // Create a proper File with a meaningful name
                        const ext = item.type.split('/')[1] === 'jpeg' ? 'jpg' : item.type.split('/')[1];
                        const file = new File([blob], 'clipboard-image.' + ext, { type: item.type });
                        setPastedFile(file);
                        return;
                    }
                }
            });

            // Click on paste zone focuses it and gives user hint
            pasteZone.addEventListener('click', function () {
                pasteZone.focus();
            });

            // Clear button
            pasteClearBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                clearPastedImage();
            });

            // When file is selected via file input, clear the paste preview
            fileInput.addEventListener('change', function () {
                if (fileInput.files?.length) {
                    // File selected via native picker — keep it, hide paste preview
                    pastePreview.classList.add('hidden');
                    pasteZoneText.classList.remove('hidden');
                    pasteZone.classList.remove('has-image');
                }
            });

            // Drag and drop support on the paste zone
            pasteZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                pasteZone.classList.add('drag-over');
            });
            pasteZone.addEventListener('dragleave', function () {
                pasteZone.classList.remove('drag-over');
            });
            pasteZone.addEventListener('drop', function (e) {
                e.preventDefault();
                pasteZone.classList.remove('drag-over');
                const files = e.dataTransfer?.files;
                if (files && files.length > 0 && files[0].type.match(/^image\//)) {
                    setPastedFile(files[0]);
                }
            });
        })();

        // ── Sources & AI ──
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
                labelInput.className = 'w-1/3 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy';
                const urlInput = document.createElement('input');
                urlInput.type = 'url';
                urlInput.name = `sources[${idx}][url]`;
                urlInput.placeholder = 'https://…';
                urlInput.className = 'flex-1 bg-input border border-input-border rounded px-3 py-2 text-sm text-copy';
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

            // AI Translate
            document.querySelectorAll('.ai-translate-link').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const targetName = this.dataset.target;
                    const targetLocale = this.dataset.targetLocale;
                    const sourceLocale = this.dataset.sourceLocale;
                    const fieldType = this.dataset.fieldType;
                    const targetField = document.querySelector(`[name="${targetName}"]`);
                    const statusEl = this.parentElement.querySelector('.ai-translate-status');

                    // Find source field by matching locale and field type
                    const srcSelector = fieldType === 'headline' ? '.headline-field' : '.desc-field';
                    const sourceField = document.querySelector(`${srcSelector}[data-locale="${sourceLocale}"]`);
                    const sourceText = sourceField?.value.trim() || '';

                    if (!sourceText) {
                        const localeNames = @json(\App\Support\Locales::supported());
                        const srcName = localeNames[sourceLocale]?.name || sourceLocale;
                        alert(@json(__('messages.translate_no_source_for')).replace(':locale', srcName));
                        return;
                    }

                    this.classList.add('hidden');
                    if (statusEl) {
                        statusEl.classList.remove('hidden');
                        statusEl.textContent = @json(__('messages.translating'));
                    }

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
                            if (statusEl) statusEl.textContent = @json(__('messages.translate_done'));
                        } else {
                            if (statusEl) statusEl.textContent = data.error || @json(__('messages.translate_error'));
                        }
                    } catch (e) {
                        const msg = e.name === 'TypeError' || (e.message && e.message.includes('fetch'))
                            ? @json(__('messages.translate_error_network'))
                            : e.name === 'AbortError'
                                ? @json(__('messages.translate_error_timeout'))
                                : @json(__('messages.translate_error'));
                        if (statusEl) statusEl.textContent = msg;
                    }

                    this.classList.remove('hidden');
                    setTimeout(() => {
                        if (statusEl) {
                            statusEl.classList.add('hidden');
                            statusEl.textContent = '';
                        }
                    }, 5000);
                });
            });

            // AI Generate
            const generateBtn = document.getElementById('ai-generate-btn');
            const generateInput = document.getElementById('ai-generate-input');
            const generateStatus = document.getElementById('ai-generate-status');

            if (generateBtn && generateInput) {
                generateBtn.addEventListener('click', async function () {
                    const text = generateInput.value.trim();
                    if (!text) {
                        alert(@json(__('messages.generate_no_text')));
                        return;
                    }

                    generateBtn.disabled = true;
                    generateStatus.classList.remove('hidden');
                    generateStatus.textContent = @json(__('messages.generating'));

                    try {
                        const resp = await fetch('{{ route('admin.generate') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ text: text }),
                        });

                        const data = await resp.json();

                        if (data.headlines && data.descriptions) {
                            // Populate headline fields
                            for (const [locale, headline] of Object.entries(data.headlines)) {
                                const colH = 'headline_' + locale.replace('-', '_');
                                const field = document.querySelector(`[name="${colH}"]`);
                                if (field && headline) field.value = headline;
                            }
                            // Populate description fields
                            for (const [locale, desc] of Object.entries(data.descriptions)) {
                                const colD = 'description_' + locale.replace('-', '_');
                                const field = document.querySelector(`[name="${colD}"]`);
                                if (field && desc) field.value = desc;
                            }
                            // Populate tags
                            if (data.tags) {
                                const tagsField = document.querySelector('[name="tags"]');
                                if (tagsField) tagsField.value = data.tags;
                            }

                            generateStatus.textContent = @json(__('messages.generate_done'));
                        } else if (data.raw_text) {
                            // AI returned something but not parsable — put it in the first description as fallback
                            generateStatus.textContent = @json(__('messages.generate_done')) + ' (raw)';
                            // Try to put raw text into the first description field
                            const firstDesc = document.querySelector('[name="description_en_US"]');
                            if (firstDesc) firstDesc.value = data.raw_text;
                            if (data.error) console.warn('Generate parse warning:', data.error);
                        } else {
                            generateStatus.textContent = data.error || @json(__('messages.generate_error'));
                        }
                    } catch (e) {
                        generateStatus.textContent = @json(__('messages.generate_error'));
                    }

                    generateBtn.disabled = false;
                    setTimeout(() => {
                        generateStatus.classList.add('hidden');
                        generateStatus.textContent = '';
                    }, 5000);
                });
            }
        })();
    </script>
@endsection
