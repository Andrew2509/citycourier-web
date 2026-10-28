@if ($paginator->hasPages())
    <nav class="flex items-center justify-between text-xs">
        <div class="flex-1 flex justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary text-xs h-8 px-3 opacity-50 cursor-not-allowed">
                    Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary text-xs h-8 px-3">
                    Sebelumnya
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary text-xs h-8 px-3">
                    Selanjutnya
                </a>
            @else
                <span class="btn btn-secondary text-xs h-8 px-3 opacity-50 cursor-not-allowed">
                    Selanjutnya
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs text-slate-500">
                    Menampilkan
                    <span class="font-semibold text-slate-800">{{ $paginator->firstItem() }}</span>
                    sampai
                    <span class="font-semibold text-slate-800">{{ $paginator->lastItem() }}</span>
                    dari
                    <span class="font-semibold text-slate-800">{{ $paginator->total() }}</span>
                    data
                </p>
            </div>
            <div>
                <ul class="flex items-center gap-1">
                    {{-- Previous Page --}}
                    @if ($paginator->onFirstPage())
                        <li>
                            <span class="w-8 h-8 rounded-lg border border-slate-200 bg-slate-50 text-slate-300 inline-flex items-center justify-center cursor-not-allowed">
                                <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                            </span>
                        </li>
                    @else
                        <li>
                            <a href="{{ $paginator->previousPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                            </a>
                        </li>
                    @endif

                    {{-- Page Numbers --}}
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <li>
                                <span class="w-8 h-8 inline-flex items-center justify-center text-xs text-slate-400">
                                    {{ $element }}
                                </span>
                            </li>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li>
                                        <span class="w-8 h-8 rounded-lg bg-primary-600 text-white font-semibold inline-flex items-center justify-center text-xs shadow-sm">
                                            {{ $page }}
                                        </span>
                                    </li>
                                @else
                                    <li>
                                        <a href="{{ $url }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 inline-flex items-center justify-center text-xs transition-colors font-medium">
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
                            <a href="{{ $paginator->nextPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                            </a>
                        </li>
                    @else
                        <li>
                            <span class="w-8 h-8 rounded-lg border border-slate-200 bg-slate-50 text-slate-300 inline-flex items-center justify-center cursor-not-allowed">
                                <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                            </span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif
