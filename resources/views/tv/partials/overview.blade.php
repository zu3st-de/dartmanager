<div class="flex min-h-[calc(100vh-5rem)] w-full items-center justify-center">
    <div class="flex items-start justify-center gap-24">
        @foreach ($tournaments as $tournament)
            @php
                $isLucky = $tournament->parent_id !== null;
                $lucky = $tournaments->firstWhere('parent_id', $tournament->id);
                $size = $lucky ? 320 : 444;
            @endphp

            @if ($isLucky)
                @continue
            @endif

            <div class="flex flex-col items-center gap-8">
                <div class="text-center">
                    <div class="mb-6 text-3xl font-semibold">
                        {{ $tournament->name }}
                    </div>

                    <div class="flex justify-center">
                        <div class="rounded-lg bg-white p-4">
                            {!! QrCode::size($size)->generate(url('/follow/' . $tournament->public_id)) !!}
                        </div>
                    </div>
                </div>

                @if ($lucky)
                    <div class="text-center">
                        <div class="mb-4 text-xl text-yellow-400">
                            Lucky-Loser
                        </div>

                        <div class="flex justify-center">
                            <div class="rounded-lg bg-white p-4">
                                {!! QrCode::size($size)->generate(url('/follow/' . $lucky->public_id)) !!}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
