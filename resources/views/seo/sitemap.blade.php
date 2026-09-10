@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ url('/') }}</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('cinema.movies.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    @foreach ($movies as $movie)
        <url>
            <loc>{{ route('cinema.movies.show', $movie) }}</loc>
            @if ($movie->updated_at)
                <lastmod>{{ $movie->updated_at->toAtomString() }}</lastmod>
            @endif
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
        </url>
        @foreach ($movie->screenings as $screening)
            <url>
                <loc>{{ route('cinema.screenings.show', [$movie, $screening]) }}</loc>
                @if ($screening->updated_at)
                    <lastmod>{{ $screening->updated_at->toAtomString() }}</lastmod>
                @endif
                <changefreq>hourly</changefreq>
                <priority>0.6</priority>
            </url>
        @endforeach
    @endforeach
</urlset>
