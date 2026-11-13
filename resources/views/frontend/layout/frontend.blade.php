<!DOCTYPE html>

<html lang="en-US">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="ThemeStarz">

    @php
    $title = ucwords(str_replace('.', '-', request()->route()->getName()));
    $title = ucwords(str_replace('-', ' ', $title));
    @endphp

    <title>AMELAUG - {{ $title }}</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home One || Agrikon || HTML Template For Agriculture Farm & Farmers</title>

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('farm_assets/images/farm_images/mini-logo1.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('farm_assets/images/farm_images/mini-logo1.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('farm_assets/images/farm_images/mini-logo1.png') }}">
    <link rel="manifest" href="{{ asset('farm_assets/images/favicons/site.webmanifest') }}">

    <!-- Standard Meta Description for SEO -->
    <meta name="description" content="Amela Global Trading, based in Kampala, Uganda, specializes in sourcing and exporting premium green coffee beans.">

    <!-- Open Graph Meta Tags for Social Media -->
    <meta property="og:title" content="Amela Global Trading">
    <meta property="og:description" content="Based in Kampala, Uganda, Amela Global Trading exports premium green coffee beans sourced from top growers.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://amelaug.com/">
    <meta property="og:image" content="https://amelaug.com/farm_assets/images/farm_images/amelaug-share.png">
    

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Handlee&family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('farm_assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/swiper.min.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/animate.min.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/odometer.min.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/jarallax.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/bootstrap-select.min.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/agrikon-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/nouislider.min.css') }}">
    <link rel="stylesheet" href="{{ asset('farm_assets/css/nouislider.pips.css') }}">

    <link rel="stylesheet" href="{{ asset('farm_assets/css/main.css') }}">

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-VWJMWN1X5V"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-VWJMWN1X5V');
    gtag('config', 'AW-18076910978');
    </script>

    <script>
    function gtag_report_conversion(url) {
        var callback = function () {
        if (typeof(url) != 'undefined') {
            window.location = url;
        }
        };
        gtag('event', 'conversion', {
        'send_to': 'AW-18076910978/fjcxCNirsZscEIKL36tD',
        'event_callback': callback
        });
        return false;
    }
    </script>

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-5MCVCR7H');</script>
    <!-- End Google Tag Manager -->
    
</head>

<body>
    <div class="page-wrapper">
        <header class="main-header">
            <div class="topbar">
                <div class="container">
                    <div class="topbar__left">
                        <div class="topbar__social">
                            <a href="https://www.facebook.com/profile.php?id=61574580716336"
                                class="fab fa-facebook-square"  target="_blank"></a>
                            <a href="https://www.instagram.com/amela.ug?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                                class="fab fa-instagram"  target="_blank"></a>
                        </div>
                        <p>WELCOME TO AMELA UGANDA - GREEN COFFEE EXPERTS</p>
                    </div>
                    <div class="topbar__right">
                        <a href="mailto:info@amelaug.com">
                            <i class="agrikon-icon-email"></i> info@amelaug.com
                        </a>
                        <a href="mailto:sales@amelaug.com">
                            <i class="agrikon-icon-email"></i> sales@amelaug.com
                        </a>
                    </div>
                </div>
            </div>
            <nav class="main-menu">
                <div class="container">
                    <div class="logo-box">
                        <a href="{{route('home')}}" aria-label="logo image">
                            <img src="{{ asset('farm_assets/images/farm_images/Amela-logo.png') }}" width="130px"
                                alt="amelaug">
                        </a>
                        <span class="fa fa-bars mobile-nav__toggler"></span>
                    </div>
                    <ul class="main-menu__list">
                        <li @if(request()->routeIs('home')) class="current" @endif>
                            <a href="{{ route('home') }}" onclick="return gtag_report_conversion('{{ route('home') }}')">Home</a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}#about">About</a>
                        </li>
                        <li @if(request()->is('fair-labor') || request()->is('premium-coffee-plants') ||
                            request()->is('souting-experts') || request()->is('worldwide-export')) class="current" @endif>
                            <a href="{{ route('home') }}#services">Services</a>
                        </li>
                        <li @if(request()->is('products*')) class="current" @endif>
                            <a href="{{ route('products') }}" onclick="return gtag_report_conversion('{{ route('products') }}')">Products</a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}#contact">Contact Us</a>
                        </li>
                    </ul>

                    <div class="main-header__info">
                        <a href="https://wa.me/256787430199" class="main-header__info-phone" target="_blank" onclick="gtag('event','conversion',{'send_to':'AW-18076910978/fjcxCNirsZscEIKL36tD'})">
                            <i class="agrikon-icon-phone-call"></i>
                            <span class="main-header__info-phone-content">
                                <span class="main-header__info-phone-text">Message on WhatsApp</span>
                                <span class="main-header__info-phone-title">+256 787 430199</span>
                            </span>
                        </a>

                    </div>
                </div>
            </nav>
        </header>

        @yield('content')

        <footer class="site-footer">
            <img src="{{ asset('farm_assets/images/icons/footer-bg-icon-1.png') }}" class="site-footer__shape-1" alt="">
            {{-- <img src="{{ asset('farm_assets/images/icons/footer-bg-icon-2.png') }}" class="site-footer__shape-2" alt=""> --}}

            <div class="container">
                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="footer-widget">
                            <a href="{{route('home')}}" class="footer-widget__Logo">
                                <img src="{{ asset('farm_assets/images/farm_images/logo-white.png') }}" width="60%">
                            </a>
                            <!-- /.topbar__social -->
                        </div><!-- /.footer-widget -->
                    </div><!-- /.col-sm-12 col-md-6 col-lg-4 -->
                    <div class="col-12 col-lg-4">
                        <div class="footer-widget footer-widget__links-widget">
                            <p>Subscribe with your email to receive updates on new products, pricing, and special
                                offers.
                            </p>
                            <form id="subscribe-form" class="mc-form" method="POST">
                                @csrf
                                    <input type="email" id="email" name="email" placeholder="Your E-mail..." required>
                                    <button type="submit" id="form-submit"><i class="agrikon-icon-right-arrow"></i></button>
                            </form>
                            
                            <div class="mc-form__response"></div><!-- /.mc-form__response -->
                            <div class="footer__social">
                                <a href="https://www.facebook.com/profile.php?id=61574580716336"
                                    class="fab fa-facebook-square"  target="_blank"></a>
                                <a href="https://www.instagram.com/amela.ug?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                                    class="fab fa-instagram"  target="_blank"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <h3 class="footer-widget__title">Contact</h3>
                        <ul class="list-unstyled footer-widget__contact">
                            <li>
                                <i class="agrikon-icon-telephone"></i>
                                <a href="https://wa.me/256787430199"  target="_blank">+256 787 430199</a>
                            </li>
                            <li>
                                <i class="agrikon-icon-email"></i>
                                <a href="mailto:info@amelaug.com"  target="_blank">info@amelaug.com</a>
                            </li>
                            <li>
                                <i class="agrikon-icon-email"></i>
                                <a href="mailto:sales@amelaug.com"  target="_blank">sales@amelaug.com</a>
                            </li>
                            <li></li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
        <div class="bottom-footer">
            <div class="container">
                <p>AMELAUG ©
                    <?php echo date('Y'); ?> By <a href="https://www.arzgt.com"  target="_blank">ARZGT</a>
                </p>
                <!-- <div class="bottom-footer__links">
                    <a href="#">Terms & Conditions</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Sitemap</a>
                </div> -->
            </div>
        </div>
    </div>

    <div class="mobile-nav__wrapper">
        <div class="mobile-nav__overlay mobile-nav__toggler"></div>

        <div class="mobile-nav__content">
            <span class="mobile-nav__close mobile-nav__toggler"><i class="far fa-times"></i></span>

            <div class="logo-box">
                <a href="{{route('home')}}" aria-label="logo image">
                    <img src="{{ asset('farm_assets/images/farm_images/logo-white.png') }}" width="155" alt="" />
                </a>
            </div>
            <div class="mobile-nav__container"></div>
            <ul class="mobile-nav__contact list-unstyled">
                <li>
                    <i class="agrikon-icon-telephone"></i>
                    <a href="https://wa.me/256787430199"  target="_blank">+256 787 430199</a>
                </li>
                <li>
                    <i class="agrikon-icon-email"></i>
                    <a href="mailto:info@amelaug.com">info@amelaug.com</a>
                </li>
                <li>
                    <i class="agrikon-icon-email"></i>
                    <a href="mailto:sales@amelaug.com">sales@amelaug.com</a>
                </li>
            </ul>
            <div class="mobile-nav__top">
                {{-- <div class="mobile-nav__language">
                    <img src="{{ asset('farm_assets/images/resources/flag-1-1.jpg') }}" alt="">
                    <label class="sr-only" for="language-select">select language</label>
                    <select class="selectpicker" id="language-select">
                        <option value="english">English</option>
                        <option value="arabic">Arabic</option>
                    </select>
                </div> --}}
                <div class="mobile-nav__social">
                    <a href="https://www.facebook.com/profile.php?id=61574580716336" class="fab fa-facebook-square"  target="_blank"></a>
                    <a href="https://www.instagram.com/amela.ug?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                        class="fab fa-instagram"  target="_blank"></a>
                </div>
            </div>



        </div>
    </div>

    {{-- <div class="search-popup">
        <div class="search-popup__overlay search-toggler"></div>

        <div class="search-popup__content">
            <form action="#">
                <label for="search" class="sr-only">search here</label>
                <input type="text" id="search" placeholder="Search Here..." />
                <button type="submit" aria-label="search submit" class="thm-btn">
                    <i class="fa fa-search"></i>
                </button>
            </form>
        </div>
    </div> --}}


    <a href="#" data-target="html" class="scroll-to-target scroll-to-top"><i class="fa fa-angle-up"></i></a>
 

    <script src="{{ asset('farm_assets/js/jquery-3.5.1.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/swiper.min.js') }}"></script>
    {{-- <script src="{{ asset('farm_assets/js/jquery.ajaxchimp.min.js') }}"></script> --}}
    <script src="{{ asset('farm_assets/js/jquery.magnific-popup.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/jquery.validate.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/wow.js') }}"></script>
    <script src="{{ asset('farm_assets/js/odometer.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/jquery.appear.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/jarallax.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/circle-progress.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/wNumb.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/nouislider.min.js') }}"></script>
    <script src="{{ asset('farm_assets/js/theme.js') }}"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

    <script>
        $(document).ready(function () {
            $('#form-submit').on('click', function (e) {
                e.preventDefault(); 

                var form = $('#subscribe-form');
                var formData = form.serialize(''); 

                var url = '{{ route('subscribe') }}'; 


                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        gtag('event', 'conversion', {'send_to': 'AW-18076910978/fjcxCNirsZscEIKL36tD'});
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonColor: '#F15D3A',
                        }).then(() => {
                        
                            window.location.href = '{{ route('home') }}'; 
                        });

                        $('#email').val('');
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: errors.email ? errors.email[0] : 'Something went wrong.',
                                confirmButtonColor: '#F15D3A',
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An unexpected error occurred. Please try again later.',
                                confirmButtonColor: '#F15D3A',
                            });
                        }
                    }
                });
            });
        });
    </script>

</body>