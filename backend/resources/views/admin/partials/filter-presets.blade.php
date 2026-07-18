{{--
    Saved views for a list page: apply, duplicate or delete a preset, and save
    the filters currently in the URL as a new one.

    Expects:
      $context      — the page's key in FilterPreset::CONTEXTS. The index route
                      is derived from it ("admin.{$context}.index").
      $presets      — this admin's presets for the page.
      $activePresetId — the preset matching the current filters, or null.
      $presetSummary  — ["label" => "value"] of the active filters, for the
                        save dialog. Empty means nothing is filtered.

    Must be included OUTSIDE the page's GET filter form: it carries its own
    POST forms, and browsers drop nested ones.
--}}
@php $canSave = ! empty($presetSummary) && ! $activePresetId; @endphp

<div class="d-flex gap-2 align-items-center">
    @if($presets->isNotEmpty())
        {{-- auto-close="outside" keeps the menu open while typing in the search box --}}
        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside">
                <i class="bi bi-bookmark"></i>
                {{ $activePresetId ? $presets->firstWhere('id', $activePresetId)->name : 'Presets' }}
            </button>
            <ul class="dropdown-menu" style="min-width: 300px;"
                data-reorder-url="{{ route('admin.filter-presets.reorder', ['context' => $context]) }}">
                @if($presets->count() > 5)
                    <li class="px-2 pb-1 d-flex gap-1 align-items-center">
                        <input type="search" class="form-control form-control-sm preset-filter-input flex-grow-1"
                               placeholder="Search saved views…" aria-label="Search saved views" autocomplete="off">
                        {{-- Rows arrive name-ascending; this toggles to descending and back. --}}
                        <button type="button" class="btn btn-sm btn-outline-secondary preset-sort-toggle"
                                data-dir="asc" title="Sort by name (A→Z / Z→A)" aria-label="Sort by name">
                            <i class="bi bi-sort-alpha-down"></i>
                        </button>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                @endif
                @foreach($presets as $preset)
                    <li class="d-flex align-items-center preset-item"
                        data-preset-id="{{ $preset->id }}"
                        data-preset-name="{{ \Illuminate\Support\Str::lower($preset->name) }}">
                        @if($presets->count() > 1)
                            <span class="d-inline-flex flex-column ps-2 preset-move-controls">
                                <button type="button" class="btn btn-link text-secondary p-0 preset-move-btn" data-dir="up"
                                        title="Move up" aria-label="Move up" style="line-height: 1;">
                                    <i class="bi bi-caret-up-fill" style="font-size: 0.7rem;"></i>
                                </button>
                                <button type="button" class="btn btn-link text-secondary p-0 preset-move-btn" data-dir="down"
                                        title="Move down" aria-label="Move down" style="line-height: 1;">
                                    <i class="bi bi-caret-down-fill" style="font-size: 0.7rem;"></i>
                                </button>
                            </span>
                        @endif
                        <a class="dropdown-item text-truncate {{ $preset->id === $activePresetId ? 'active' : '' }}"
                           href="{{ route("admin.{$context}.index", $preset->filters) }}">
                            {{ $preset->name }}
                        </a>
                        <button type="button" class="btn btn-sm btn-link text-secondary p-0 px-1 preset-rename-btn"
                                title="Rename preset"
                                data-bs-toggle="modal" data-bs-target="#renamePresetModal"
                                data-url="{{ route('admin.filter-presets.rename', ['context' => $context, 'preset' => $preset]) }}"
                                data-name="{{ $preset->name }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-secondary p-0 px-1 preset-duplicate-btn"
                                title="Duplicate preset"
                                data-bs-toggle="modal" data-bs-target="#duplicatePresetModal"
                                data-url="{{ route('admin.filter-presets.duplicate', ['context' => $context, 'preset' => $preset]) }}"
                                data-name="{{ $preset->name }}">
                            <i class="bi bi-copy"></i>
                        </button>
                        <form method="POST" class="pe-2"
                              action="{{ route('admin.filter-presets.destroy', ['context' => $context, 'preset' => $preset]) }}"
                              onsubmit="return confirm('Delete the preset &quot;{{ $preset->name }}&quot;?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger p-0" title="Delete preset">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </li>
                @endforeach
                <li class="preset-no-matches d-none">
                    <span class="dropdown-item-text small text-muted">No saved views match.</span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item small text-muted"
                       href="{{ route('admin.filter-presets.export', ['context' => $context]) }}">
                        <i class="bi bi-download"></i>
                        Export {{ $presets->count() }} preset{{ $presets->count() === 1 ? '' : 's' }} as JSON
                    </a>
                </li>
                <li>
                    <form method="POST" action="{{ route('admin.filter-presets.destroy-all', ['context' => $context]) }}"
                          onsubmit="return confirm('Delete all {{ $presets->count() }} of your saved views for this page? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item small text-danger">
                            <i class="bi bi-trash"></i> Delete all saved views
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    @endif

    @if($canSave)
        <button type="button" class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#savePresetModal">
            <i class="bi bi-bookmark-plus"></i> Save preset
        </button>
    @endif

    {{-- Always available — importing is most useful when you have none yet. --}}
    <button type="button" class="btn btn-sm btn-link text-secondary p-0"
            data-bs-toggle="modal" data-bs-target="#importPresetsModal" title="Import presets from a JSON file">
        <i class="bi bi-upload"></i> Import
    </button>

    <span class="text-muted" style="font-size: 0.8rem;">Saved views are private to your account.</span>
</div>

{{-- Import modal — always rendered, since it works with zero existing presets --}}
<div class="modal fade" id="importPresetsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data"
              action="{{ route('admin.filter-presets.import', ['context' => $context]) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Filter Presets</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">
                        Load presets from a JSON file exported from this page. Presets whose name you
                        already use are skipped unless you choose to overwrite.
                    </p>
                    <label for="importPresetsFile" class="form-label">Preset file (.json) <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="importPresetsFile" name="file" accept=".json,application/json" required>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="importPresetsOverwrite" name="overwrite">
                        <label class="form-check-label" for="importPresetsOverwrite">
                            Overwrite presets with the same name
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if($canSave)
    {{-- The action carries the filters currently in the URL; the controller
         keeps only the ones this context declares. --}}
    <div class="modal fade" id="savePresetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.filter-presets.store', array_merge(['context' => $context], request()->query())) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Save Filter Preset</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3" style="font-size: 0.9rem;">
                            Saves the filters currently applied as a named view you can return to.
                        </p>
                        <ul class="list-unstyled mb-3" style="font-size: 0.85rem;">
                            @foreach($presetSummary as $label => $value)
                                <li><span class="text-muted">{{ $label }}:</span> <span class="fw-semibold">{{ $value }}</span></li>
                            @endforeach
                        </ul>
                        <label for="presetName" class="form-label">Preset name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="presetName" name="name" maxlength="60" required
                               placeholder="e.g. My daily review queue">
                        <div class="form-text">Re-using an existing name overwrites that preset.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-bookmark-plus"></i> Save preset</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif

@if($presets->isNotEmpty())
    <div class="modal fade" id="renamePresetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="renamePresetForm">
                @csrf
                @method('PATCH')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Rename Filter Preset</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3" style="font-size: 0.9rem;">
                            Renaming <span class="fw-semibold" id="renamePresetSource"></span> keeps its filters —
                            only the label changes.
                        </p>
                        <label for="renamePresetName" class="form-label">New name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="renamePresetName" name="name" maxlength="60" required>
                        <div class="form-text">Must differ from your other preset names on this page.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-pencil"></i> Rename</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="duplicatePresetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="duplicatePresetForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Duplicate Filter Preset</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3" style="font-size: 0.9rem;">
                            Copies the filters from <span class="fw-semibold" id="duplicatePresetSource"></span>
                            into a new preset. Adjust the copy by applying it, changing the filters, and saving over it.
                        </p>
                        <label for="duplicatePresetName" class="form-label">New name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               id="duplicatePresetName" name="name" maxlength="60" required>
                        @error('name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Must differ from your existing preset names.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-copy"></i> Duplicate</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        // Both dialogs act on whichever preset's button opened them: point the
        // form at that preset and seed the name field.
        [
            { modal: 'renamePresetModal', form: 'renamePresetForm', source: 'renamePresetSource', input: 'renamePresetName', seed: function (n) { return n; } },
            { modal: 'duplicatePresetModal', form: 'duplicatePresetForm', source: 'duplicatePresetSource', input: 'duplicatePresetName', seed: function (n) { return n + ' (copy)'; } },
        ].forEach(function (cfg) {
            document.getElementById(cfg.modal).addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                if (!btn) return;
                var name = btn.getAttribute('data-name');
                document.getElementById(cfg.form).action = btn.getAttribute('data-url');
                document.getElementById(cfg.source).textContent = name;
                document.getElementById(cfg.input).value = cfg.seed(name);
            });
        });

        // Focus the name so it can be typed over straight away.
        ['renamePresetModal', 'duplicatePresetModal'].forEach(function (id) {
            document.getElementById(id).addEventListener('shown.bs.modal', function () {
                var input = this.querySelector('input[name="name"]');
                input.focus();
                input.select();
            });
        });

        // Live filter of the saved-views list by name. Client-side: the presets
        // are already on the page, so there is nothing to fetch.
        (function () {
            var box = document.querySelector('.preset-filter-input');
            if (!box) return;
            var menu = box.closest('.dropdown-menu');
            var items = menu.querySelectorAll('.preset-item');
            var empty = menu.querySelector('.preset-no-matches');

            box.addEventListener('input', function () {
                var q = box.value.trim().toLowerCase();
                var shown = 0;
                items.forEach(function (li) {
                    var match = li.getAttribute('data-preset-name').indexOf(q) !== -1;
                    li.classList.toggle('d-none', !match);
                    if (match) shown++;
                });
                if (empty) empty.classList.toggle('d-none', shown !== 0);
            });

            // Typing/clicking inside the box must not navigate or close the menu.
            box.addEventListener('click', function (e) { e.stopPropagation(); });
        })();

        // Toggle the saved-views list between A→Z and Z→A. Reorders in place —
        // the rows are already here, name-ascending from the server.
        (function () {
            var toggle = document.querySelector('.preset-sort-toggle');
            if (!toggle) return;
            var menu = toggle.closest('.dropdown-menu');
            var anchor = menu.querySelector('.preset-no-matches'); // rows sit before this
            var icon = toggle.querySelector('i');

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var dir = toggle.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
                toggle.setAttribute('data-dir', dir);
                icon.className = dir === 'asc' ? 'bi bi-sort-alpha-down' : 'bi bi-sort-alpha-up';

                Array.prototype.slice.call(menu.querySelectorAll('.preset-item'))
                    .sort(function (a, b) {
                        var an = a.getAttribute('data-preset-name');
                        var bn = b.getAttribute('data-preset-name');
                        return dir === 'asc' ? an.localeCompare(bn) : bn.localeCompare(an);
                    })
                    .forEach(function (li) { menu.insertBefore(li, anchor); });
            });
        })();

        // Manual up/down reordering. Moves the row in place and persists the new
        // order, so the dropdown stays open across several nudges.
        (function () {
            var moveBtn = document.querySelector('.preset-move-btn');
            if (!moveBtn) return;
            var menu = moveBtn.closest('.dropdown-menu');
            var anchor = menu.querySelector('.preset-no-matches');
            var url = menu.getAttribute('data-reorder-url');
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;

            function items() {
                return Array.prototype.slice.call(menu.querySelectorAll('.preset-item'));
            }

            // Disable the up arrow on the first row and the down arrow on the last.
            function refreshEnds() {
                var rows = items();
                rows.forEach(function (li, i) {
                    var up = li.querySelector('.preset-move-btn[data-dir="up"]');
                    var down = li.querySelector('.preset-move-btn[data-dir="down"]');
                    if (up) up.disabled = i === 0;
                    if (down) down.disabled = i === rows.length - 1;
                });
            }

            function persist() {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ order: items().map(function (li) { return Number(li.getAttribute('data-preset-id')); }) }),
                });
            }

            menu.addEventListener('click', function (e) {
                var btn = e.target.closest('.preset-move-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();

                var li = btn.closest('.preset-item');
                if (btn.getAttribute('data-dir') === 'up') {
                    var prev = li.previousElementSibling;
                    if (prev && prev.classList.contains('preset-item')) menu.insertBefore(li, prev);
                } else {
                    var next = li.nextElementSibling;
                    if (next && next.classList.contains('preset-item')) menu.insertBefore(next, li);
                }
                refreshEnds();
                persist();
            });

            refreshEnds();
        })();
    </script>
    @endpush
@endif
