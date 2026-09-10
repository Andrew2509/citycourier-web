@if ($paginator->hasPages())
    <nav class="flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-space-md py-space-xs font-label-md text-label-md text-secondary bg-surface-container-lowest border border-surface-container-high cursor-not-allowed rounded-lg">
                    Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-space-md py-space-xs font-label-md text-label-md text-on-surface bg-surface-container-lowest border border-surface-container-high rounded-lg hover:bg-surface-container-high transition-colors">
                    Sebelumnya
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-space-md py-space-xs font-label-md text-label-md text-on-surface bg-surface-container-lowest border border-surface-container-high rounded-lg hover:bg-surface-container-high transition-colors">
                    Selanjutnya
                </a>
            @else
                <span class="relative inline-flex items-center px-space-md py-space-xs font-label-md text-label-md text-secondary bg-surface-container-lowest border border-surface-container-high cursor-not-allowed rounded-lg">
                    Selanjutnya
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="font-body-sm text-body-sm text-secondary">
                    Menampilkan
                    <span class="font-semibold text-on-surface">{{ $paginator->firstItem() }}</span>
                    sampai
                    <span class="font-semibold text-on-surface">{{ $paginator->lastItem() }}</span>
                    dari
                    <span class="font-semibold text-on-surface">{{ $paginator->total() }}</span>
                    hasil
                </p>
            </div>
            <div>
                <ul class="flex items-center gap-space-xs">
                    {{-- Previous Page --}}
                    @if ($paginator->onFirstPage())
                        <li>
                            <span class="relative inline-flex items-center px-space-sm py-space-xs font-body-sm text-body-sm text-secondary bg-surface-container-lowest border border-surface-container-high cursor-not-allowed rounded-lg">
                                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                            </span>
                        </li>
                    @else
                        <li>
                            <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-space-sm py-space-xs font-body-sm text-body-sm text-on-surface bg-surface-container-lowest border border-surface-container-high rounded-lg hover:bg-surface-container-high transition-colors">
                                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                            </a>
                        </li>
                    @endif

                    {{-- Page Numbers --}}
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <li>
                                <span class="relative inline-flex items-center px-space-sm py-space-xs font-body-sm text-body-sm text-secondary bg-surface-container-lowest border border-surface-container-high cursor-default rounded-lg">
                                    {{ $element }}
                                </span>
                            </li>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li>
                                        <span class="relative inline-flex items-center px-space-sm py-space-xs font-body-sm text-body-sm font-semibold text-on-primary bg-primary-container border border-transparent cursor-default rounded-lg shadow-sm">
                                            {{ $page }}
                                        </span>
                                    </li>
                                @else
                                    <li>
                                        <a href="{{ $url }}" class="relative inline-flex items-center px-space-sm py-space-xs font-body-sm text-body-sm text-on-surface bg-surface-container-lowest border border-surface-container-high rounded-lg hover:bg-surface-container-high transition-colors">
                                            {{ $page }}
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page --}}
                    @if ($paginator->hasMorePages())
                        <li>
                            <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-space-sm py-space-xs font-body-sm text-body-sm text-on-surface bg-surface-container-lowest border border-surface-container-high rounded-lg hover:bg-surface-container-high transition-colors">
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </a>
                        </li>
                    @else
                        <li>
                            <span class="relative inline-flex items-center px-space-sm py-space-xs font-body-sm text-body-sm text-secondary bg-surface-container-lowest border border-surface-container-high cursor-not-allowed rounded-lg">
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif
