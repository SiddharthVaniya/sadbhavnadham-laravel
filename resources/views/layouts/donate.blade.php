<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <!-- Google Tag Manager -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-TZXKP2D6');
    </script>
    <!-- End Google Tag Manager -->

    <!-- Google tag (gtag.js) event -->
    <script>
        gtag('event', 'purchase', {
            // <event_parameters>
        });
    </script>

    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TZXKP2D6"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-09456MZ2BR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-09456MZ2BR');
    </script>
    <!-- Meta Pixel Code -->
    <script>
        ! function(f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function() {
                n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = !0;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = !0;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '2930779970588380');
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=2930779970588380&ev=PageView&noscript=1" /></noscript>
    <!-- End Meta Pixel Code -->
    @php
    $seoTitle = trim($__env->yieldContent('title')) ?: ('Donate | ' . $branding['shortName']);
    $seoDescription = trim($__env->yieldContent('meta_description')) ?: $branding['tagline'];
    $seoCanonical = trim($__env->yieldContent('canonical')) ?: \App\Support\Seo::canonicalUrl();
    $seoOgImage = trim($__env->yieldContent('og_image')) ?: ($branding['ogImageUrl'] ?: $branding['logoPublicUrl']);
    if ($seoOgImage !== '' && ! str_starts_with($seoOgImage, 'http://') && ! str_starts_with($seoOgImage, 'https://')) {
    $seoOgImage = asset($seoOgImage);
    }
    @endphp
    <meta charset="UTF-8">
    <title>{{ $seoTitle }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $seoDescription }}">
    @if (trim($__env->yieldContent('robots')))
    <meta name="robots" content="{{ trim($__env->yieldContent('robots')) }}">
    @endif
    <link rel="canonical" href="{{ $seoCanonical }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $branding['name'] }}">
    <meta property="og:title" content="{{ trim($__env->yieldContent('og_title')) ?: $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:image" content="{{ $seoOgImage }}">
    <meta property="og:image:alt" content="{{ trim($__env->yieldContent('og_image_alt')) ?: $branding['name'] }}">
    <meta property="og:locale" content="en_IN">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ trim($__env->yieldContent('og_title')) ?: $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoOgImage }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://www.googletagmanager.com">
    <link rel="dns-prefetch" href="https://checkout.razorpay.com">

    <link rel="icon" href="{{ $branding['faviconUrl'] }}" sizes="32x32" />
    <link rel="icon" href="{{ $branding['faviconUrl'] }}" sizes="192x192" />
    <link rel="apple-touch-icon" href="{{ $branding['faviconUrl'] }}" />
    <meta name="msapplication-TileImage" content="{{ $branding['faviconUrl'] }}" />
    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/swiper-bundle.min.css') }}">
    {{-- Base Styles --}}
    <link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/donate.css') }}">

    @stack('structured_data')
    @stack('styles')
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17450390051">
    </script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'AW-17450390051');
    </script>
    <script id="chatway" async="true" src="https://cdn.chatway.app/widget.js?id=H9DW6appeLiG"></script>
</head>

<body class="donate-layout">

    {{-- HEADER --}}
    @include('partials.site-header')

    {{-- OPTIONAL PAGE HEADING --}}
    @hasSection('page_header')
    <section class="page-header">
        <div class="container">
            @yield('page_header')
        </div>
    </section>
    @endif

    {{-- MAIN CONTENT --}}
    <main class="donate-main">
        <div class="container">
            @yield('content')
        </div>
    </main>

    {{-- FOOTER --}}
    @include('partials.site-footer')
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/swiper-bundle.min.js') }}"></script>
    <script>
        document.querySelectorAll('.swiper').forEach((el) => {
            const delay = Number(el.dataset.delay) || 2000;
            new Swiper(el, {
                loop: true,
                speed: 800,
                effect: "fade",
                navigation: false,
                autoplay: {
                    delay: delay,
                    disableOnInteraction: false,
                },
            });
        });
    </script>
    <script src="{{ \App\Support\PublicAsset::url('js/donation-attribution.js') }}"></script>
    @include('partials.donor-login-modal')
    <script src="{{ \App\Support\PublicAsset::url('js/donor-login.js') }}"></script>
    @stack('scripts')
    {{-- Google Translate 
    <div id="google_translate_element" style="display: none;"></div>

    <script>
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,hi,gu',
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>

    <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    <!-- Language Selection Modal -->
    <div id="languageModal" class="language-modal">
        <div class="language-box">
            <h4>Choose Your Language</h4>
            <!-- Primary Default Button -->
            <button onclick="setLanguage('en')" class="btn-english-default">
                Continue in English
            </button>

            <div class="divider">
                <span>OR SELECT LANGUAGE</span>
            </div>

            <div class="language-options">

                <button onclick="setLanguage('hi')" class="lang-btn">
                    <img src="https://flagcdn.com/w40/in.png" alt="Hindi">
                    <span>हिंदी</span>
                </button>

                <button onclick="setLanguage('gu')" class="lang-btn">
                    <img src="https://flagcdn.com/w40/in.png" alt="Gujarati">
                    <span>ગુજરાતી</span>
                </button>
            </div>
        </div>
    </div>
     <script>
        function setLanguage(lang) {

            localStorage.setItem("site_language", lang);

            document.cookie = "googtrans=/en/" + lang + ";path=/";

            if (lang === 'gu') {
                document.documentElement.classList.add('gu-lang');
            } else {
                document.documentElement.classList.remove('gu-lang');
            }

            location.reload();
        }

        document.addEventListener("DOMContentLoaded", function() {

            const selectedLang = localStorage.getItem("site_language");

            if (!selectedLang) {
                document.getElementById("languageModal").style.display = "flex";
            } else {

                document.cookie = "googtrans=/en/" + selectedLang + ";path=/";

                if (selectedLang === 'gu') {
                    document.documentElement.classList.add('gu-lang');
                }

                document.getElementById("languageModal").style.display = "none";
            }
        });
    </script> --}}
</body>

</html>