@extends('frontend.layout.frontend')
@section('content')

<div class="stricky-header stricked-menu main-menu">
    <div class="sticky-header__content"></div>
</div>
<section class="page-header">

    <div class="page-header__bg" style="background-image: url('{{ asset('farm_assets/images/backgrounds/page-header-bg-1-1.jpg')}}')}}') }}');"></div>

    <!-- /.page-header__bg -->
    <div class="container">
        <ul class="thm-breadcrumb list-unstyled">
            <li><a href="{{route('home')}}">Home</a></li>
            <li>/</li>
            <li><span>Products</span></li>
        </ul><!-- /.thm-breadcrumb list-unstyled -->
        <h2>Our Products</h2>
    </div><!-- /.container -->
</section><!-- /.page-header -->

<div class="projects-one">
    <div class="container">
        @foreach($categories as $categoryKey => $products)
            <section id="{{ $categoryKey }}">
                <h2 class="text-center mb-4"><strong>{{ ucfirst($categoryKey) }}</strong></h2>
                <div class="row">
                    @foreach($products as $product)
                        <div class="col-sm-12 col-md-6 col-lg-4">
                            <div class="projects-one__single">
                                <img src="{{ asset('farm_assets/images/products/' . $product['image']) }}" alt="">
                                <div class="projects-one__content">
                                    <h3>{{ $product['name'] }}</h3>
                                    <h6>{{ $product['process'] }}</h6>
                                    <a href="{{ route('product-details', ['category' => $categoryKey, 'productId' => $product['id']]) }}"
                                       class="projects-one__button">
                                        <i class="agrikon-icon-right-arrow"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            <hr>
        @endforeach
    </div>
</div>

@endsection