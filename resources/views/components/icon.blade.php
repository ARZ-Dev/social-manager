@props(['name'])
{!! \App\Support\Icon::svg($name, $attributes->get('class') ?? 'size-5', $attributes->except('class')->getAttributes()) !!}
