@extends('frontend.layout.frontend')
@section('content')




<div class="stricky-header stricked-menu main-menu">
    <div class="sticky-header__content"></div>
</div>
<section class="main-slider">
    <div class="swiper-container thm-swiper__slider" data-swiper-options='{
                "slidesPerView": 1,
                "loop": true,
                "effect": "fade",
                "autoplay": {
                    "delay": 5000
                },
                "navigation": {
                    "nextEl": "#main-slider__swiper-button-next",
                    "prevEl": "#main-slider__swiper-button-prev"
                }
            }'>

        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <div class="image-layer"
                    style="background-image: url('{{ asset('farm_assets/images/farm_images/hero-1.JPG') }}');">
                </div>
                <!-- /.image-layer -->
                <div class="container">
                    <div class="row">
                        <div class="col-xl-7 col-lg-7">
                            <span class="tagline">WELCOME TO AMELA UGANDA - GREEN COFFEE EXPERTS</span>
                            <h2><span>Exporting </span> <br>
                                Uganda’s Coffee to the World</h2>
                            <p>Amela Global Trading is based in Kampala, Uganda, and specializes in sourcing and
                                exporting high-quality green coffee beans.</p>
                            {{-- <a href="#" class=" thm-btn">Discover More</a> --}}
                            <!-- /.thm-btn dynamic-radius -->
                        </div><!-- /.col-lg-7 text-right -->
                    </div><!-- /.row -->
                </div><!-- /.container -->
            </div><!-- /.swiper-slide -->
            <div class="swiper-slide">
                <div class="image-layer"
                    style="background-image: url('{{ asset('farm_assets/images/farm_images/hero-2.jpg') }}');">
                </div>
                <!-- /.image-layer -->
                <div class="container">
                    <div class="row">
                        <div class="col-xl-7 col-lg-7">
                            <span class="tagline">It's Time To Try The Coffee From The Pearl</span>
                            <h2><span>Outstanding </span> <br>
                                Quality And Ethical Trade</h2>
                            <p>To share Uganda’s unique coffee heritage with the world, through ethical trade and
                                outstanding qualitybean by bean.
                                <!-- /.thm-btn dynamic-radius -->
                        </div><!-- /.col-lg-7 text-right -->
                    </div><!-- /.row -->
                </div><!-- /.container -->
            </div><!-- /.swiper-slide -->
        </div><!-- /.swiper-wrapper -->

        <!-- If we need navigation buttons -->
        <div class="main-slider__nav">
            <div class="swiper-button-prev" id="main-slider__swiper-button-next"><i class="agrikon-icon-left-arrow"></i>
            </div>
            <div class="swiper-button-next" id="main-slider__swiper-button-prev"><i
                    class="agrikon-icon-right-arrow"></i></div>
        </div><!-- /.main-slider__nav -->

    </div><!-- /.swiper-container thm-swiper__slider -->
</section><!-- /.main-slider -->

<section class="about-one" id="about">
    <img src="{{ asset('farm_assets/images/icons/about-bg-icon-1-1.png') }}" class="about-one__bg-shape-1" alt="">
    <div class="container">
        <div class="row">
            <div class="col-lg-5">
                <div class="about-one__images">
                    <img id="aboutImg1" src="{{ asset('farm_assets/images/farm_images/about-1.jpg') }}" alt="">
                    <img id="aboutImg2" src="{{ asset('farm_assets/images/farm_images/about2.jpg') }}" alt="">
                    {{-- <div class="about-one__count wow fadeInLeft" data-wow-duration="1500ms">
                        <span>Since</span>
                        <h4>1987</h4>
                    </div><!-- /.about-one__count --> --}}
                </div><!-- /.about-one__images -->
            </div><!-- /.col-lg-6 -->
            <div class="col-lg-7">
                <div class="about-one__content">
                    <div class="block-title text-left">
                        <div class="block-title__image"></div><!-- /.block-title__image -->
                        <p>Welcome to SocialM</p>
                        <h3>Committed to fair labor and sustainable practices</h3>
                    </div><!-- /.block-title -->
                    <div class="about-one__tagline">
                        <p>Experienced in Uganda's Coffee Exporting to the world</p>
                    </div><!-- /.about-one__tagline -->
                    <div class="about-one__summery">
                        <p>We work hand-in-hand with Ugandan farmers, supporting fair prices, sustainable farming
                            practices, and empowering rural communities.</p>
                    </div><!-- /.about-one__summery -->
                    <div class="about-one__icon-row">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="about-one__box">
                                    <i class="agrikon-icon-farmer"></i>
                                    <h4>Local Farmers</h4>
                                </div><!-- /.about-one__box -->
                            </div><!-- /.col-lg-6 -->
                            <div class="col-lg-6">
                                <div class="about-one__box">
                                    <i class="agrikon-icon-agriculture"></i>
                                    <h4>Ethical Sourcing</h4>
                                </div><!-- /.about-one__box -->
                            </div><!-- /.col-lg-6 -->
                        </div><!-- /.row -->
                    </div><!-- /.about-one__icon-row -->
                    {{-- <a href="#" class="thm-btn">Discover More</a><!-- /.thm-btn --> --}}
                </div><!-- /.about-one__content -->
            </div><!-- /.col-lg-6 -->
        </div><!-- /.row -->
    </div><!-- /.container -->
</section><!-- /.about-one -->

{{-- <section class="service-one">
    <div class="container">
        <div class="row">
            <div class="col-md-12 col-lg-4 wow fadeInUp" data-wow-duration="1500ms">
                <div class="service-one__box">
                    <img src="{{ asset('farm_assets/images/services/service-1-2.jpg') }}" alt="">
                    <div class="service-one__box-content">
                        <h3>Training Coffee Farmers</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-12 col-lg-4 wow fadeInUp" data-wow-duration="1500ms">
                <div class="service-one__box">
                    <img src="{{ asset('farm_assets/images/services/service-1-2.jpg') }}" alt="">
                    <div class="service-one__box-content">
                        <h3>Quality Check</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-lg-4 wow fadeInUp" data-wow-duration="1500ms">
                <div class="service-one__box">
                    <img src="{{ asset('farm_assets/images/services/service-1-3.jpg') }}" alt="">
                    <div class="service-one__box-content">
                        <h3>Trusted Partners</h3>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section> --}}

<section class="service-two" id="services">
    <div class="service-two__bottom-curv"></div><!-- /.service-two__bottom-curv -->
    <div class="container">
        <div class="block-title text-center">
            <div class="block-title__image"></div><!-- /.block-title__image -->
            <h3>What Amela Offers</h3>
        </div><!-- /.block-title -->
        <div class="row">
            <div class="col-sm-12 col-md-6 col-lg-4">
                <div class="service-two__card">
                    <div class="service-two__card-image">
                        <img src="{{ asset('farm_assets/images/farm_images/service-1.jpg') }}" alt="">
                    </div>
                    <div class="service-two__card-content">
                        <div class="service-two__card-icon">
                            <img src="{{asset('farm_assets/images/farm_images/service-i1.png')}}" width="75px" />
                        </div>
                        {{-- <h3><a href="{{ route('coffee-sourcing') }}">Coffee Sourcing</a></h3> --}}
                        <h3>Coffee Sourcing</h3>
                        <p>Premium Arabica & Robusta Coffee Plants For A Better Corp.</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-12 col-md-6 col-lg-4">
                <div class="service-two__card">
                    <div class="service-two__card-image">
                        <img src="{{ asset('farm_assets/images/farm_images/service-2.jpg') }}" alt="">
                    </div>
                    <div class="service-two__card-content">
                        <div class="service-two__card-icon">
                            <img src="{{asset('farm_assets/images/farm_images/service-i2.png')}}" width="75px" />
                        </div><!-- /.service-two__card-icon -->
                        {{-- <h3><a href="{{route('coffee-processing')}}">Coffee Processing</a></h3> --}}
                        <h3>Coffee Processing</h3>
                        <p>Specialize In Sorting Beans For A Diffect Free Outcome.</p>
                    </div><!-- /.service-two__card-content -->
                </div><!-- /.service-two__card -->
            </div><!-- /.col-sm-12 col-md-6 col-lg-3 -->
            <div class="col-sm-12 col-md-6 col-lg-4">
                <div class="service-two__card">
                    <div class="service-two__card-image">
                        <img src="{{ asset('farm_assets/images/farm_images/service-3.jpg') }}" alt="">
                    </div><!-- /.service-two__card-image -->
                    <div class="service-two__card-content">
                        <div class="service-two__card-icon">
                            <img src="{{asset('farm_assets/images/farm_images/service-i3.png')}}" width="75px" />
                        </div><!-- /.service-two__card-icon -->
                        {{-- <h3><a href="{{route('trusted-partners')}}">Trusted Partners</a></h3> --}}
                        <h3>Trusted Partners</h3>
                        <p>Your Trusted Partner In The Pearl Of Africa.</p>
                    </div><!-- /.service-two__card-content -->
                </div><!-- /.service-two__card -->
            </div><!-- /.col-sm-12 col-md-6 col-lg-3 -->
        </div><!-- /.row -->
    </div><!-- /.container -->
</section><!-- /.service-two -->

<section class="service-one py-5" style="background-color: #f7f7f7;">
    <div class="container">
        <!-- Section Title -->
        <div class="block-title text-center">
            <div class="block-title__image"></div><!-- /.block-title__image -->
            <h3>Our Categories</h3>
        </div><!-- /.block-title -->

        <!-- Products Grid -->
        <div class="row g-4 justify-content-center">
            <!-- Product Card -->
            <div class="col-sm-12 col-md-6 col-lg-4">
                <a href="{{ route('products') }}#robusta" class="text-decoration-none text-dark">
                    <div class="service-two__card">
                        <div class="service-two__card-image position-relative">
                            <img src="{{ asset('farm_assets/images/products/Arzgt-UgandaRobustaSC12.jpg') }}" alt="Ugandan Robusta Scr. 12"
                                class="img-fluid rounded">
                        </div>
                        <div class="service-two__card-content text-center" style="padding:0px 10px 30px 10px;">
                            <div class="service-two__card-icon" style="height:75px; background:transparent !important; border:0px;"></div>
                            <h3 class="fw-bold">ROBUSTA</h3>
                            <p class="text-muted">Robusta Description ...</p>
                        </div>
                    </div>
                </a>
            </div>
            {{-- <div class="col-sm-12 col-md-6 col-lg-2"></div> --}}
            <div class="col-sm-12 col-md-6 col-lg-4">
                <a href="{{ route('products') }}#arabica" class="text-decoration-none text-dark">
                    <div class="service-two__card">
                        <div class="service-two__card-image position-relative">
                            <img src="{{ asset('farm_assets/images/products/Arzgt-UgandaAA.jpg') }}" alt="Ugandan Robusta Scr. 12"
                                class="img-fluid rounded">
                        </div>
                        <div class="service-two__card-content text-center" style="padding:0px 10px 30px 10px;">
                            <div class="service-two__card-icon" style="height:75px; background:transparent !important; border:0px;"></div>
                            <h3 class="fw-bold">ARABICA</h3>
                            <p class="text-muted">Arabica Description ...</p>
                        </div>
                    </div>
                </a>
            </div>

        </div>

    </div>
</section>


<div class="projects-one project-one__home-one">
    <div class="container">
        <div class="block-title text-center">
            <div class="block-title__image"></div><!-- /.block-title__image -->
            <p>Gallery</p>
            <h3>Glimpse of Amela</h3>
        </div><!-- /.block-title -->
        <div class="thm-swiper__slider swiper-container" data-swiper-options='{"spaceBetween": 0, "slidesPerView": 1, "loop": true, "slidesPerGroup": 1, "pagination": {
            "el": "#projects-one__swiper-pagination",
            "type": "bullets",
            "clickable": true
        },
        "breakpoints": {
            "0": {
                "spaceBetween": 0,
                "slidesPerView": 1,
                "slidesPerGroup": 1
            },
            "640": {
                "spaceBetween": 30,
                "slidesPerView": 2,
                "slidesPerGroup": 2
            },
            "992": {
                "spaceBetween": 30,
                "slidesPerView": 2,
                "slidesPerGroup": 2
            }
        }}'>
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <div class="projects-one__single">
                        <img src="{{ asset('farm_assets/images/farm_images/gallery-2.jpg') }}" alt="">
                        {{-- <div class="projects-one__content">
                            <h3>Harvest Innovation</h3>
                            <a href="{{route('project-details')}}" class="projects-one__button"><i
                                    class="agrikon-icon-right-arrow"></i></a>
                        </div> --}}
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="projects-one__single">
                        <img src="{{ asset('farm_assets/images/farm_images/gallery-1.jpg') }}" alt="">
                        {{-- <div class="projects-one__content">
                            <h3>Harvest Innovation</h3>
                            <a href="{{route('project-details')}}" class="projects-one__button"><i
                                    class="agrikon-icon-right-arrow"></i></a>
                        </div> --}}
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="projects-one__single">
                        <img src="{{ asset('farm_assets/images/farm_images/gallery-3.jpg') }}" alt="">
                        {{-- <div class="projects-one__content">
                            <h3>Harvest Innovation</h3>
                            <a href="{{route('project-details')}}" class="projects-one__button"><i
                                    class="agrikon-icon-right-arrow"></i></a>
                        </div> --}}
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="projects-one__single">
                        <img src="{{ asset('farm_assets/images/farm_images/gallery-4.jpg') }}" alt="">
                        {{-- <div class="projects-one__content">
                            <h3>Harvest Innovation</h3>
                            <a href="{{route('project-details')}}" class="projects-one__button"><i
                                    class="agrikon-icon-right-arrow"></i></a>
                        </div> --}}
                    </div>
                </div>
            </div>

            <div class="swiper-pagination" id="projects-one__swiper-pagination"></div>
        </div>
    </div>
</div>

<section class="testimonials-one">
    <img src="{{ asset('farm_assets/images/icons/testimonials-bg-1-1.png') }}" class="testimonials-one__bg" alt="">
</section><!-- /.testimonials-one -->

<section class="gray-boxed-wrapper home-one__boxed">
    <img src="{{ asset('farm_assets/images/icons/home-1-blog-bg.png') }}" alt="" class="home-one__boxed-bg">
    <section class="blog-home-two blog-home-one">
        <div class="container">
            <div class="row top-row">
                <div class="col-lg-6">
                    <div class="block-title">
                        <div class="block-title__image"></div><!-- /.block-title__image -->
                        {{-- <p>From the blog post</p> --}}
                        <h3>We Ensure Speciality In Every Lot</h3>
                    </div><!-- /.block-title -->
                </div><!-- /.col-lg-6 -->
                <div class="col-lg-6">
                    <p class="block-text">Our in-house lab in Kampala rigorously analyzes every lot. With complete
                        traceability from farm to port, we deliver quality with confidence..</p>
                </div><!-- /.col-lg-6 -->
            </div><!-- /.row -->
            <div class="row">
                <div class="col-md-12 col-lg-4">
                    <div class="blog-card">
                        <div class="blog-card__image">
                            <img src="{{ asset('farm_assets/images/farm_images/end-2.jpg') }}"
                                alt="Best Way to Do Eco and Agriculture">
                            {{-- <a href="{{route('blog-details')}}"></a> --}}
                        </div><!-- /.blog-card__image -->
                        {{-- <div class="blog-card__content">
                            <div class="blog-card__date">18 Nov</div><!-- /.blog-card__date -->
                            <div class="blog-card__meta">
                                <a href="{{route('blog-details')}}"><i class="far fa-user-circle"></i> by Admin</a>
                                <a href="{{route('blog-details')}}"><i class="far fa-comments"></i> 2 Comments</a>
                            </div><!-- /.blog-card__meta -->
                            <h3><a href="{{route('blog-details')}}">Best Way to Do Eco and Agriculture</a></h3>
                            <a href="{{route('blog-details')}}" class="thm-btn">Read More</a><!-- /.thm-btn -->
                        </div><!-- /.blog-card__content --> --}}
                    </div><!-- /.blog-card -->
                </div><!-- /.col-md-12 col-lg-4 -->
                <div class="col-md-12 col-lg-4">
                    <div class="blog-card">
                        <div class="blog-card__image">
                            <img src="{{ asset('farm_assets/images/farm_images/end-3.jpg') }}"
                                alt="Leverage agile frameworks to provide">
                            {{-- <a href="{{route('blog-details')}}"></a> --}}
                        </div><!-- /.blog-card__image -->
                        {{-- <div class="blog-card__content">
                            <div class="blog-card__date">18 Nov</div><!-- /.blog-card__date -->
                            <div class="blog-card__meta">
                                <a href="{{route('blog-details')}}"><i class="far fa-user-circle"></i> by Admin</a>
                                <a href="{{route('blog-details')}}"><i class="far fa-comments"></i> 2 Comments</a>
                            </div><!-- /.blog-card__meta -->
                            <h3><a href="{{route('blog-details')}}">Leverage agile frameworks to provide</a></h3>
                            <a href="{{route('blog-details')}}" class="thm-btn">Read More</a><!-- /.thm-btn -->
                        </div><!-- /.blog-card__content --> --}}
                    </div><!-- /.blog-card -->
                </div><!-- /.col-md-12 col-lg-4 -->
                <div class="col-md-12 col-lg-4">
                    <div class="blog-card">
                        <div class="blog-card__image">
                            <img src="{{ asset('farm_assets/images/farm_images/end-1.jpg') }}"
                                alt="Organically grow the holistic world view">
                            {{-- <a href="{{route('blog-details')}}"></a> --}}
                        </div><!-- /.blog-card__image -->
                        {{-- <div class="blog-card__content">
                            <div class="blog-card__date">18 Nov</div><!-- /.blog-card__date -->
                            <div class="blog-card__meta">
                                <a href="{{route('blog-details')}}"><i class="far fa-user-circle"></i> by Admin</a>
                                <a href="{{route('blog-details')}}"><i class="far fa-comments"></i> 2 Comments</a>
                            </div><!-- /.blog-card__meta -->
                            <h3><a href="{{route('blog-details')}}">Organically grow the holistic world view</a></h3>
                            <a href="{{route('blog-details')}}" class="thm-btn">Read More</a><!-- /.thm-btn -->
                        </div><!-- /.blog-card__content --> --}}
                    </div><!-- /.blog-card -->
                </div><!-- /.col-md-12 col-lg-4 -->
            </div><!-- /.row -->
            <hr />
        </div><!-- /.container -->
    </section><!-- /.blog-home-two -->
    <div class="blog-home__slogan">
        <div class="container">
            <div class="row">
                <div class="col-lg-9">
                    <div class="blog-home__slogan-main">
                        <i class="agrikon-icon-farm"></i>
                        <div class="blog-home__slogan-content">
                            <h3>From UGANDA To The World</h3>
                            <p>We work hand-in-hand with Ugandan farmers, supporting fair prices, sustainable farming
                                practices, and empowering rural communities.</p>
                        </div><!-- /.blog-home__slogan-content -->
                    </div><!-- /.blog-home__slogan-main -->
                </div><!-- /.col-lg-9 -->
                <div class="col-lg-3">
                    <div class="blog-home__slogan-image">
                        <img src="{{ asset('farm_assets/images/resources/blog-cta-1.png') }}" alt="">
                    </div><!-- /.blog-home__slogan-image -->
                </div><!-- /.col-lg-3 -->
            </div><!-- /.row -->
        </div><!-- /.container -->
    </div><!-- /.blog-home__slogan -->
</section><!-- /.gray-boxed-wrapper -->

<section class="contact-two" id="contact">
    <div class="container">
        <div class="row">
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-5">
                <div class="contact-two__image">
                    <div class="contact-two__image-bubble-1"></div><!-- /.contact-two__image-bubble-1 -->
                    <div class="contact-two__image-bubble-2"></div><!-- /.contact-two__image-bubble-2 -->
                    <div class="contact-two__image-bubble-3"></div><!-- /.contact-two__image-bubble-3 -->
                    <img src="{{ asset('farm_assets/images/farm_images/contact.png') }}" class="img-fluid" alt="" style="filter: brightness(85%);">
                </div><!-- /.contact-two__image -->
            </div><!-- /.col-sm-12 col-md-12 col-lg-12 col-xl-5 -->
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-3">
                <div class="contact-two__content">
                    <div class="block-title">
                        <div class="block-title__image"></div><!-- /.block-title__image -->
                        <p>Contact now</p>
                        <h3>Leave Us A
                            Message</h3>
                    </div><!-- /.block-title -->
                    <div class="contact-two__summery">
                        <p>Explore our services, discover the flavor of Uganda, and join our global coffee community.
                        </p>
                    </div><!-- /.contact-two__summery -->
                </div><!-- /.contact-two__content -->
            </div><!-- /.col-sm-12 col-md-12 col-lg-12 col-xl-4 -->
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-4">
                <form id="contact-form" method="POST" class="contact-one__form contact-form-validated">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12">
                            <input type="text" name="name" placeholder="Full Name">
                        </div><!-- /.col-lg-6 -->
                        <div class="col-lg-12">
                            <input type="text" name="email" placeholder="Email Address">
                        </div><!-- /.col-lg-6 -->
                        <div class="col-lg-12">
                            <input type="text" name="phone" placeholder="Phone Number">
                        </div><!-- /.col-lg-6 -->
                        <div class="col-lg-12">
                            <textarea name="message" placeholder="Write Message"></textarea>
                        </div><!-- /.col-lg-12 -->
                        <div class="col-lg-12">
                            <button type="button" id="submit" class="thm-btn">Send Message</button><!-- /.thm-btn -->
                        </div><!-- /.col-lg-12 -->
                    </div><!-- /.row -->
                </form>
            </div><!-- /.col-sm-12 col-md-12 col-lg-12 col-xl-4 -->
        </div><!-- /.row -->
    </div><!-- /.container -->
</section><!-- /.contact-two -->

<script src="{{ asset('farm_assets/js/jquery-3.5.1.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {
				$('#submit').on('click', function (e) {
                    // alert('hello');
					e.preventDefault(); 

					var form = $('#contact-form');
					var formData = form.serialize(''); 

					var url = '{{ route('contact') }}'; 


					$.ajax({
						url: url,
						type: 'POST',
						data: formData,
						success: function(response) {
							Swal.fire({
								icon: 'success',
								title: 'Success!',
								text: response.message,
                                confirmButtonColor: '#F15D3A',
							}).then(() => {
							
								window.location.href = '{{ route('home') }}'; 
							});

							$('#name').val('');
							$('#email').val('');
							$('#phone').val('');
							$('#message').val('');
						},
						error: function(xhr) {
                            if (xhr.status === 422) {
                                var errors = xhr.responseJSON.errors;
                                var errorMessages = '';

                                // Loop through each error and concatenate the messages
                                for (var field in errors) {
                                    if (errors.hasOwnProperty(field)) {
                                        errorMessages += errors[field][0] + '\n';
                                    }
                                }

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Validation Error',
                                    text: errorMessages,
                                    confirmButtonColor: '#F15D3A',
                                    customClass: {
                                        popup: 'swal-wide' // Optional: helps display longer messages better
                                    }
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
@endsection