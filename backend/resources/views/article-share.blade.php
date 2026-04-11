<!DOCTYPE html>
<html lang="en">
@php
    $title = $article->shareTitle();
    $previewText = $article->sharePreviewText();
    $description = \Illuminate\Support\Str::limit($previewText, 180);
    $category = $article->shareCategory();
    $authorName = $article->shareAuthorName();
    $imageUrl = $article->shareImageUrl();
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Mono Reader</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="Mono Reader">
    @if ($imageUrl)
        <meta property="og:image" content="{{ $imageUrl }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $imageUrl }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <style>
        :root {
            color-scheme: dark;
            --bg: #0c1016;
            --panel: rgba(20, 25, 36, 0.88);
            --panel-border: rgba(122, 137, 168, 0.18);
            --text: #f4f7ff;
            --muted: #a9b4c8;
            --accent: #7b68ee;
            --accent-soft: rgba(123, 104, 238, 0.14);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(123, 104, 238, 0.38), transparent 34%),
                radial-gradient(circle at top right, rgba(78, 199, 255, 0.22), transparent 26%),
                linear-gradient(160deg, #090c12 0%, #111723 100%);
        }

        .shell {
            width: min(1080px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 40px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 24px;
            align-items: stretch;
        }

        .image-card,
        .story-card {
            border-radius: 32px;
            border: 1px solid var(--panel-border);
            overflow: hidden;
            background: var(--panel);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
            backdrop-filter: blur(16px);
        }

        .image-card img,
        .fallback {
            width: 100%;
            height: 100%;
            min-height: 420px;
            object-fit: cover;
            display: block;
        }

        .fallback {
            display: grid;
            place-items: end start;
            padding: 28px;
            background: linear-gradient(135deg, #28225a 0%, #131926 100%);
            font-size: clamp(1.9rem, 4vw, 3.4rem);
            font-weight: 800;
            line-height: 1.05;
        }

        .story-card {
            padding: 28px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            align-self: flex-start;
            padding: 10px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #d9d2ff;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 14px;
            font-size: clamp(2.1rem, 4vw, 4rem);
            line-height: 0.98;
        }

        .dek {
            margin: 0;
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.75;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }

        .meta span {
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            color: #e5ebf8;
            font-weight: 600;
        }

        .body-grid {
            display: grid;
            grid-template-columns: 1.5fr 0.9fr;
            gap: 24px;
            margin-top: 24px;
        }

        .panel {
            border-radius: 28px;
            border: 1px solid var(--panel-border);
            background: var(--panel);
            padding: 24px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(16px);
        }

        .panel h2 {
            margin: 0 0 14px;
            font-size: 1.1rem;
        }

        .panel p {
            margin: 0;
            color: var(--muted);
            line-height: 1.75;
        }

        .stats {
            display: grid;
            gap: 12px;
        }

        .stat {
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .stat strong {
            display: block;
            font-size: 1.35rem;
            margin-top: 6px;
        }

        .cta {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 18px;
            width: 100%;
            border-radius: 18px;
            background: linear-gradient(135deg, #7b68ee 0%, #4ec7ff 100%);
            color: #fff;
            text-decoration: none;
            font-weight: 800;
        }

        .footer-note {
            margin-top: 12px;
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        @media (max-width: 860px) {
            .hero,
            .body-grid {
                grid-template-columns: 1fr;
            }

            .shell {
                width: min(100%, calc(100% - 24px));
                padding-top: 18px;
            }

            .story-card,
            .panel {
                padding: 22px;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <div class="image-card">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $title }}">
                @else
                    <div class="fallback">{{ $title }}</div>
                @endif
            </div>

            <article class="story-card">
                <div>
                    <div class="eyebrow">{{ $category }} Preview</div>
                    <h1>{{ $title }}</h1>
                    <p class="dek">{{ $previewText }}</p>

                    <div class="meta">
                        <span>By {{ $authorName }}</span>
                        <span>{{ $article->price }} coins</span>
                        <span>{{ number_format((float) $article->rating_average, 1) }} rating</span>
                    </div>
                </div>

                <div>
                    <a href="{{ url('/') }}" class="cta">Open Reader Portal</a>
                    <p class="footer-note">
                        This public page shares the article preview. Full content stays inside the reader experience.
                    </p>
                </div>
            </article>
        </section>

        <section class="body-grid">
            <div class="panel">
                <h2>About this story</h2>
                <p>{{ $previewText }}</p>
            </div>

            <aside class="stats">
                <div class="panel stat">
                    Category
                    <strong>{{ $category }}</strong>
                </div>
                <div class="panel stat">
                    Views
                    <strong>{{ number_format($article->view_count) }}</strong>
                </div>
                <div class="panel stat">
                    Unlocks
                    <strong>{{ number_format($article->unlock_count) }}</strong>
                </div>
                <div class="panel stat">
                    Published
                    <strong>{{ optional($article->published_at)->format('d M Y') ?? 'Recently' }}</strong>
                </div>
            </aside>
        </section>
    </main>
</body>
</html>
