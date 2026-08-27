{{--
    Not $paginator->previousPageUrl()/nextPageUrl(): under Livewire's
    WithPagination, the paginator's path resolver returns request()->path()
    (no scheme/host, no leading slash), so those helpers build a fragile
    relative URL instead of the absolute one every other SEO tag here uses.
--}}
@props(['paginator'])

@php
    $base = request()->url();
@endphp

@if (! $paginator->onFirstPage())
    @php $prevPage = $paginator->currentPage() - 1; @endphp
    <link rel="prev" href="{{ $prevPage > 1 ? $base.'?page='.$prevPage : $base }}">
@endif

@if ($paginator->hasMorePages())
    <link rel="next" href="{{ $base }}?page={{ $paginator->currentPage() + 1 }}">
@endif
