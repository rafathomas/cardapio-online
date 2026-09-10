<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('landing') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
@foreach ($establishments as $establishment)
    <url>
        <loc>{{ route('menu.show', $establishment->slug) }}</loc>
        <lastmod>{{ $establishment->updated_at?->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
