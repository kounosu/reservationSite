<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>予約管理 | {{ config('app.name', 'Reservation Site') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="reservation-page admin-page">
        <div class="reservation-app-shell">
            <main class="admin-shell">
                <section class="panel-surface admin-hero">
                    <div>
                        <p class="eyebrow">Reservation Admin</p>
                        <h1>予約管理</h1>
                        <p class="admin-hero-copy">予約の検索、日付確認、キャンセルと再確定をこの画面で管理できます。</p>
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

                <section class="admin-content-grid">
                    <aside class="panel-surface admin-filters">
                        <div class="admin-panel-head">
                            <div>
                                <p class="eyebrow">Filters</p>
                                <h2>絞り込み</h2>
                            </div>
                            <p class="admin-panel-copy">日付、ステータス、キーワードで予約一覧を絞り込めます。</p>
                        </div>

                        <form method="get" class="admin-filter-form">
                            <label>
                                <span>来店日</span>
                                <input type="date" name="date" value="{{ $filters['date'] }}">
                            </label>

                            <label>
                                <span>ステータス</span>
                                <select name="status">
                                    <option value="">すべて</option>
                                    @foreach ($statusOptions as $status)
                                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $statusLabels[$status] }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span>キーワード</span>
                                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="予約コード / 氏名 / メール">
                            </label>

                            <div class="admin-filter-actions">
                                <button type="submit" class="admin-primary-button">この条件で表示</button>
                                <a href="{{ route('admin.reservations.index') }}" class="admin-secondary-link">リセット</a>
                            </div>
                        </form>

                        <div class="admin-filter-note">
                            <span>今日</span>
                            <strong>{{ $today }}</strong>
                        </div>
                    </aside>

                    <section class="admin-results">
                        @if (session('status'))
                            <div class="feedback feedback-success">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if (isset($errors) && $errors->any())
                            <div class="feedback feedback-error">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="admin-results-head">
                            <div>
                                <p class="eyebrow">Reservations</p>
                                <h2>{{ $reservations->total() }}件の予約</h2>
                            </div>
                            <p class="admin-panel-copy">カード内の操作でステータスを変更できます。変更時には在庫数も同時に補正されます。</p>
                        </div>

                        <div class="admin-card-list">
                            @forelse ($reservations as $reservation)
                                @php
                                    $slot = $reservation->slot;
                                    $slotStart = $slot->slot_start->timezone($timezone);
                                    $slotEnd = $slot->slot_end->timezone($timezone);
                                    $statusClass = $reservation->status === \App\Models\Reservation::STATUS_CANCELLED ? 'tone-cancelled' : 'tone-confirmed';
                                @endphp

                                <article class="panel-surface admin-reservation-card {{ $reservation->status === \App\Models\Reservation::STATUS_CANCELLED ? 'is-muted' : '' }}">
                                    <div class="admin-reservation-head">
                                        <div>
                                            <p class="eyebrow">予約コード</p>
                                            <h3>{{ $reservation->reservation_code }}</h3>
                                        </div>
                                        <span class="status-chip {{ $statusClass }}">{{ $statusLabels[$reservation->status] }}</span>
                                    </div>

                                    <div class="admin-reservation-grid">
                                        <dl>
                                            <dt>来店日時</dt>
                                            <dd>{{ $slotStart->locale('ja')->isoFormat('YYYY.MM.DD (ddd)') }}<br>{{ $slotStart->format('H:i') }} - {{ $slotEnd->format('H:i') }}</dd>
                                        </dl>
                                        <dl>
                                            <dt>予約者</dt>
                                            <dd>{{ $reservation->guest_name }}</dd>
                                        </dl>
                                        <dl>
                                            <dt>メール</dt>
                                            <dd>{{ $reservation->guest_email }}</dd>
                                        </dl>
                                        <dl>
                                            <dt>電話番号</dt>
                                            <dd>{{ $reservation->guest_phone ?: '未登録' }}</dd>
                                        </dl>
                                        <dl>
                                            <dt>人数</dt>
                                            <dd>{{ $reservation->party_size }}名</dd>
                                        </dl>
                                        <dl>
                                            <dt>在庫状況</dt>
                                            <dd>{{ $slot->reserved_count }} / {{ $slot->capacity }}席使用中</dd>
                                        </dl>
                                    </div>

                                    @if ($reservation->notes)
                                        <div class="admin-note-card">
                                            <span>ご要望</span>
                                            <p>{{ $reservation->notes }}</p>
                                        </div>
                                    @endif

                                    <div class="admin-reservation-actions">
                                        <form method="post" action="{{ route('admin.reservations.update', $reservation) }}">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="status" value="{{ \App\Models\Reservation::STATUS_CONFIRMED }}">
                                            <button type="submit" class="admin-action-button is-primary" @disabled($reservation->status === \App\Models\Reservation::STATUS_CONFIRMED)>確定に戻す</button>
                                        </form>

                                        <form method="post" action="{{ route('admin.reservations.update', $reservation) }}">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="status" value="{{ \App\Models\Reservation::STATUS_CANCELLED }}">
                                            <button type="submit" class="admin-action-button is-danger" @disabled($reservation->status === \App\Models\Reservation::STATUS_CANCELLED)>キャンセルにする</button>
                                        </form>
                                    </div>
                                </article>
                            @empty
                                <div class="panel-surface admin-empty-state">
                                    <h3>該当する予約はありません。</h3>
                                    <p>絞り込み条件を変更するか、公開予約ページから新規予約を作成してください。</p>
                                </div>
                            @endforelse
                        </div>

                        @if ($reservations->hasPages())
                            <div class="admin-pagination">
                                {{ $reservations->links() }}
                            </div>
                        @endif
                    </section>
                </section>
            </main>
        </div>
    </body>
</html>
