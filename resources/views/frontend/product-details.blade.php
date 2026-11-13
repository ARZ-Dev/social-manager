@extends('frontend.layout.frontend')

@section('content')
<div class="stricky-header stricked-menu main-menu">
    <div class="sticky-header__content"></div>
</div>

<section class="page-header">
    <div class="page-header__bg"></div>
    <div class="container">
        <ul class="thm-breadcrumb list-unstyled">
            <li><a href="{{ route('home') }}">Home</a></li>
            <li>/</li>
            <li><span>Product Details</span></li>
        </ul>
        <h2>{{ $product['name'] }}</h2>
        <h5>{{ $product['altitude'] }}</h5>
    </div>
</section>

<section class="project-details">
    <div class="container">
        <img src="{{ asset('farm_assets/images/products/' . $product['image']) }}" class="img-fluid" alt="{{ $product['name'] }}">

        <ul class="list-unstyled project-details__list">
            <li><span>Grade:</span> {{ $product['grade'] }}</li>
            <li><span>Process:</span> {{ $product['process'] }}</li>
            <li><span>Acidity:</span> {{ $product['acidity'] }}</li>
            <li><span>Harvest Date:</span> {{ $product['date'] }}</li>
        </ul>

        <h2>{{ $product['name'] }}</h2>
        <p>{{ $product['description'] }}</p>

        <div class="bottom-content">
            <div class="row">
                <div class="col-lg-6">
                    <h3>Amela’s Commitment to Coffee Excellence</h3>
                    <p>{{ $product['description2'] }}</p>
                    <ul class="list-unstyled project-details__check-list">
                        <li><i class="fa fa-check-circle"></i> Collaborating closely with farmers to ensure optimal harvesting practices and cherry selection</li>
                        <li><i class="fa fa-check-circle"></i> Utilizing modern machinery to deliver high-quality, defect-free coffee beans</li>
                        <li><i class="fa fa-check-circle"></i> Exporting globally with an advanced tracking system for full supply chain transparency</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="project-details__images">
                            <img src="{{ asset('farm_assets/images/products/' . $product['details_images'] ) }}" height="400px" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
