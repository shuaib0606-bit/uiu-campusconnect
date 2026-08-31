{{-- One batch of feed posts. --}}
@foreach ($posts as $post)
    @include('feed._post', ['post' => $post])
@endforeach
