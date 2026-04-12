<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>予約管理 | {{ config('app.name', 'Reservation Site') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite([
            'resources/css/app.css',
            'resources/css/pages/admin/common.css',
            'resources/css/pages/admin/reservations/index.css',
            'resources/js/app.js',
        ])
    </head>
    <body class="reservation-page admin-page">
        <div class="reservation-app-shell">
            <main class="admin-shell">
                <section class="panel-surface admin-hero">
                    <div>
                        <p class="eyebrow">Reservation Admin</p>
                        <h1>予約管理</h1>
                        <p class="admin-hero-copy">一覧操作と公開ページ確認を分けた管理トップです。必要な画面へ遷移して作業できます。</p>
                    </div>
                    <div class="admin-hero-actions">
                        <a href="{{ route('reservations.index') }}" class="admin-secondary-link">公開予約ページを見る</a>
                    </div>
                </section>

                <section class="admin-summary-grid">
                    @foreach ($summary as $item)
                        <article class="panel-surface admin-summary-card tone-{{ $item['tone'] }}">
                            <p class="eyebrow">{{ $item['label'] }}</p>
                            <strong>{{ number_format($item['value']) }}</strong>
                            <span>{{ $item['hint'] }}</span>
                        </article>
                    @endforeach
                </section>

                <section class="admin-action-grid">
                    <article class="panel-surface admin-nav-card">
                        <p class="eyebrow">Reservation List</p>
                        <h2>予約一覧を開く</h2>
                        <p class="admin-panel-copy">検索、ページ送り、ステータス変更は一覧専用ページで行います。</p>
                        <a href="{{ route('admin.reservations.list') }}" class="admin-primary-button">一覧ページへ</a>
                    </article>

                    <article class="panel-surface admin-nav-card">
                        <p class="eyebrow">Today</p>
                        <h2>本日の予約を見る</h2>
                        <p class="admin-panel-copy">今日の来店分だけを絞り込んだ状態で予約一覧を開きます。</p>
                        <a href="{{ route('admin.reservations.list', ['date' => $today]) }}" class="admin-secondary-link">本日の一覧へ</a>
                    </article>

                    <article class="panel-surface admin-nav-card">
                        <p class="eyebrow">Public Page</p>
                        <h2>公開画面を確認</h2>
                        <p class="admin-panel-copy">ユーザーが見る予約ページの状態を確認できます。</p>
                        <a href="{{ route('reservations.index') }}" class="admin-secondary-link">公開ページを見る</a>
                    </article>
                </section>
            </main>
        </div>
    </body>
</html>
