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
        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-bookmark"></i>
                {{ $activePresetId ? $presets->firstWhere('id', $activePresetId)->name : 'Presets' }}
            </button>
            <ul class="dropdown-menu" style="min-width: 280px;">
                @foreach($presets as $preset)
                    <li class="d-flex align-items-center">
                        <a class="dropdown-item text-truncate {{ $preset->id === $activePresetId ? 'active' : '' }}"
                           href="{{ route("admin.{$context}.index", $preset->filters) }}">
                            {{ $preset->name }}
                        </a>
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
            </ul>
        </div>
    @endif

    @if($canSave)
        <button type="button" class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#savePresetModal">
            <i class="bi bi-bookmark-plus"></i> Save preset
        </button>
    @endif

    <span class="text-muted" style="font-size: 0.8rem;">Saved views are private to your account.</span>
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
        document.getElementById('duplicatePresetModal').addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            var name = btn.getAttribute('data-name');
            document.getElementById('duplicatePresetForm').action = btn.getAttribute('data-url');
            document.getElementById('duplicatePresetSource').textContent = name;
            document.getElementById('duplicatePresetName').value = name + ' (copy)';
        });
    </script>
    @endpush
@endif
