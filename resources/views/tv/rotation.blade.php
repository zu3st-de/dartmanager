@extends('layouts.tv')

@section('content')
    <div id="overviewTemplate" style="display:none">{!! $initialConfig['overview_html'] !!}</div>

    <div id="tvStage"></div>
@endsection

@push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const stage = document.getElementById("tvStage");
            const overviewTemplate = document.getElementById("overviewTemplate");
            const configUrl = "{{ route('tv.rotation-config') }}";

            let config = @json($initialConfig);
            let pages = config.pages?.length ? config.pages : [{
                type: "overview"
            }];
            let index = 0;
            let rotationTimer = null;

            function currentPageKey(page) {
                return page.type === "tournament"
                    ? `tournament:${page.public_id ?? page.url}`
                    : "overview";
            }

            function restartRotationTimer() {
                if (rotationTimer) {
                    clearInterval(rotationTimer);
                }

                rotationTimer = setInterval(next, (config.rotation_time ?? 20) * 1000);
            }

            function showPage() {
                const page = pages[index] ?? pages[0] ?? {
                    type: "overview"
                };

                stage.style.opacity = 0;

                setTimeout(() => {
                    if (page.type === "overview") {
                        stage.innerHTML = overviewTemplate.innerHTML;
                    } else {
                        stage.innerHTML =
                            `<iframe src="${page.url}" style="width:100%;height:100vh;border:none"></iframe>`;
                    }

                    stage.style.opacity = 1;
                }, 500);
            }

            function next() {
                index++;

                if (index >= pages.length) {
                    index = 0;
                }

                showPage();
            }

            function applyConfig(nextConfig) {
                const currentKey = currentPageKey(pages[index] ?? {
                    type: "overview"
                });

                config = nextConfig;
                pages = nextConfig.pages?.length ? nextConfig.pages : [{
                    type: "overview"
                }];
                overviewTemplate.innerHTML = nextConfig.overview_html ?? "";

                const nextIndex = pages.findIndex(page => currentPageKey(page) === currentKey);
                index = nextIndex >= 0 ? nextIndex : 0;

                showPage();
                restartRotationTimer();
            }

            async function refreshConfig() {
                try {
                    const response = await fetch(configUrl, {
                        cache: "no-store",
                        headers: {
                            "Accept": "application/json",
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    });

                    if (!response.ok) {
                        return;
                    }

                    const nextConfig = await response.json();

                    if (nextConfig.signature !== config.signature) {
                        applyConfig(nextConfig);
                    }
                } catch (error) {
                    console.error("tv rotation refresh failed", error);
                }
            }

            showPage();
            restartRotationTimer();
            setInterval(refreshConfig, 5000);
        });
    </script>
@endpush

@push('styles')
    <style>
        #tvStage {
            transition: opacity .6s ease;
        }
    </style>
@endpush
