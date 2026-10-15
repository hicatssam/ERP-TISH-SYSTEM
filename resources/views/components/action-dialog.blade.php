@props(['id', 'title', 'description' => null])

<dialog id="{{ $id }}" class="erp-action-dialog" aria-labelledby="{{ $id }}-title" @if($description) aria-describedby="{{ $id }}-description" @endif @if(old('_modal') === $id && $errors->any()) data-open-on-error @endif>
    <div class="erp-action-dialog-head">
        <div>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            @if($description)<p id="{{ $id }}-description">{{ $description }}</p>@endif
        </div>
        <button type="button" class="erp-action-dialog-close" data-close-dialog aria-label="إغلاق">×</button>
    </div>
    @if(old('_modal') === $id && $errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif
    {{ $slot }}
</dialog>

@once
    @push('styles')
        <style>
            .erp-action-dialog {
                box-sizing: border-box;
                width: min(620px, calc(100vw - 24px));
                max-width: min(620px, calc(100vw - 24px));
                max-height: calc(100dvh - 24px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
                padding: 1.25rem;
                overflow: auto;
                overscroll-behavior: contain;
                -webkit-overflow-scrolling: touch;
                border: 1px solid var(--theme-border, var(--border, #ddd));
                border-radius: 16px;
                color: var(--theme-text, var(--text-main, #172435));
                background: var(--theme-surface, var(--card-bg, #fff));
                box-shadow: 0 25px 80px rgba(0, 0, 0, .22);
                direction: rtl;
            }
            .erp-action-dialog::backdrop { background: rgba(12, 22, 36, .58); backdrop-filter: blur(3px); }
            .erp-action-dialog-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
            .erp-action-dialog-head h2 { margin: 0; font-size: 1.2rem; }
            .erp-action-dialog-head p { margin: .35rem 0 0; color: var(--theme-text-muted, var(--text-muted, #687482)); line-height: 1.7; }
            .erp-action-dialog-close { min-width: 44px; min-height: 44px; flex: 0 0 44px; border: 1px solid var(--theme-border, #ddd); border-radius: 10px; background: transparent; color: inherit; font-size: 1.6rem; cursor: pointer; line-height: 1; }
            .erp-action-dialog form { margin: 0; min-width: 0; }
            .erp-action-dialog .form-grid { margin-bottom: 1rem; }
            .erp-action-dialog input, .erp-action-dialog select, .erp-action-dialog textarea { max-width: 100%; box-sizing: border-box; }
            .erp-action-dialog :focus-visible { outline: 2px solid var(--theme-accent, #b8892e); outline-offset: 2px; }
            @media(max-width: 600px) {
                .erp-action-dialog {
                    width: calc(100vw - 12px);
                    max-width: calc(100vw - 12px);
                    max-height: calc(100dvh - 12px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
                    padding: 1rem .8rem max(1rem, env(safe-area-inset-bottom, 0px));
                    border-radius: 15px;
                }
                .erp-action-dialog-head h2 { font-size: 1rem; }
                .erp-action-dialog :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea) { font-size: 16px; min-height: 44px; }
                .erp-action-dialog .form-grid { grid-template-columns: minmax(0, 1fr); }
                .erp-action-dialog :is(.form-actions, .modal-actions, .card-footer) {
                    display: flex;
                    flex-wrap: wrap;
                    gap: .5rem;
                    position: sticky;
                    bottom: -.3rem;
                    background: var(--theme-surface, #fff);
                    padding-block: .6rem;
                }
                .erp-action-dialog :is(.form-actions, .modal-actions, .card-footer) .btn {
                    flex: 1 1 120px;
                    min-height: 44px;
                }
            }
        </style>
    @endpush
    @push('scripts')
        <script>
            (() => {
                // One delegated handler covers every instance of this component.
                const restoreFocus = new WeakMap();
                const dialogs = document.querySelectorAll('dialog.erp-action-dialog');

                dialogs.forEach(dialog => {
                    dialog.addEventListener('close', () => {
                        if (!document.querySelector('dialog.erp-action-dialog[open]')) {
                            document.body.classList.remove('erp-action-dialog-open');
                        }
                        const opener = restoreFocus.get(dialog);
                        if (opener?.isConnected) opener.focus({preventScroll: true});
                        restoreFocus.delete(dialog);
                    });
                });

                document.addEventListener('click', event => {
                    const opener = event.target.closest('[data-open-dialog]');
                    if (opener) {
                        const dialog = document.getElementById(opener.dataset.openDialog);
                        if (dialog instanceof HTMLDialogElement && dialog.classList.contains('erp-action-dialog') && !dialog.open) {
                            event.preventDefault();
                            restoreFocus.set(dialog, opener);
                            document.body.classList.add('erp-action-dialog-open');
                            dialog.showModal();
                        }
                    }

                    const closer = event.target.closest('[data-close-dialog]');
                    if (closer) closer.closest('dialog.erp-action-dialog')?.close();
                });

                document.querySelectorAll('.erp-action-dialog[data-open-on-error]').forEach(dialog => {
                    if (!dialog.open) {
                        document.body.classList.add('erp-action-dialog-open');
                        dialog.showModal();
                    }
                });
            })();
        </script>
    @endpush
@endonce
