<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>予約一覧 | {{ config('app.name', 'Reservation Site') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite([
    'resources/css/app.css',
    'resources/css/pages/admin/common.css',
    'resources/css/pages/admin/reservations/list.css',
    'resources/js/app.js',
    ])
</head>

<body class="reservation-page admin-page">
    @php
    $groupedReservations = collect($reservations->items())->groupBy(
    fn ($reservation) => $reservation->slot->slot_start->timezone($timezone)->toDateString()
    );
    @endphp

    <div class="reservation-app-shell">
        <main class="admin-shell admin-list-shell">
            <section class="panel-surface admin-hero">
                <div>
                    <p class="eyebrow">Reservation List</p>
                    <h1>予約一覧</h1>
                    <!-- <p class="admin-hero-copy">一覧は要点だけ表示し、詳細はモーダルで確認できます。少ない件数でも一覧全体を把握しやすくしています。</p> -->
                </div>
                <div class="admin-hero-actions">
                    <a href="{{ route('admin.reservations.index') }}" class="admin-secondary-link">管理トップへ戻る</a>
                    <a href="{{ route('reservations.index') }}" class="admin-secondary-link">公開予約ページを見る</a>
                </div>
            </section>

            <section class="panel-surface admin-list-filter-panel">
                <div class="admin-panel-head admin-list-panel-head">
                    <div>
                        <p class="eyebrow">Filters</p>
                        <h2>絞り込み</h2>
                    </div>
                    <p class="admin-panel-copy">来店日、状態、キーワード、表示件数をここで切り替えます。</p>
                </div>

                <form method="get" class="admin-filter-form admin-filter-form-inline">
                    <label>
                        <span>来店日</span>
                        <input type="date" name="date" value="{{ $filters['date'] }}">
                    </label>

                    <label>
                        <span>ステータス</span>
                        <select name="status">
                            <option value="">すべて</option>
                            @foreach ($statusOptions as $status)
                            <option value="{{ $status }}" @selected($filters['status']===$status)>{{ $statusLabels[$status] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>キーワード</span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="予約コード / 氏名 / メール">
                    </label>

                    <label>
                        <span>1ページの件数</span>
                        <select name="per_page">
                            @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage===$option)>{{ $option }}件</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="admin-filter-actions">
                        <button type="submit" class="admin-primary-button">この条件で表示</button>
                        <a href="{{ route('admin.reservations.list') }}" class="admin-secondary-link">リセット</a>
                    </div>
                </form>

                <div class="admin-filter-note">
                    <span>今日</span>
                    <strong>{{ $today }}</strong>
                </div>
            </section>

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
                    <p class="admin-panel-copy">一覧では基本情報だけを表示し、詳細や要望は個別モーダルで確認できます。</p>
                </div>

                <div class="admin-results-toolbar">
                    <div class="admin-results-range">
                        @if ($reservations->total() > 0)
                        <strong>{{ number_format((int) $reservations->firstItem()) }} - {{ number_format((int) $reservations->lastItem()) }}</strong>
                        <span>このページに表示中</span>
                        @else
                        <strong>0</strong>
                        <span>表示対象なし</span>
                        @endif
                    </div>

                    <div class="admin-active-filters">
                        @forelse ($activeFilters as $filter)
                        <span class="admin-filter-chip">
                            <span>{{ $filter['label'] }}</span>
                            <strong>{{ $filter['value'] }}</strong>
                        </span>
                        @empty
                        <span class="admin-filter-chip is-muted">絞り込みなし</span>
                        @endforelse
                    </div>

                    <div class="admin-page-status">
                        <span class="admin-page-status-label">Page</span>
                        <strong>{{ $reservations->currentPage() }} / {{ max(1, $reservations->lastPage()) }}</strong>
                        <span>{{ $perPage }}件ずつ表示</span>
                    </div>
                </div>

                @if ($reservations->hasPages())
                <div class="admin-pagination admin-pagination-top">
                    <div class="admin-pagination-quick-nav">
                        @if ($reservations->onFirstPage())
                        <span class="admin-secondary-link is-disabled" aria-disabled="true">前のページ</span>
                        @else
                        <a href="{{ $reservations->previousPageUrl() }}" class="admin-secondary-link">前のページ</a>
                        @endif

                        @if ($reservations->hasMorePages())
                        <a href="{{ $reservations->nextPageUrl() }}" class="admin-secondary-link">次のページ</a>
                        @else
                        <span class="admin-secondary-link is-disabled" aria-disabled="true">次のページ</span>
                        @endif
                    </div>
                </div>
                @endif

                <div class="admin-reservation-groups">
                    @forelse ($groupedReservations as $items)
                    @php
                    $groupSlotStart = $items->first()->slot->slot_start->timezone($timezone);
                    @endphp

                    <section class="panel-surface admin-reservation-group">
                        <div class="admin-group-head">
                            <div>
                                <p class="eyebrow">Service Date</p>
                                <h3>{{ $groupSlotStart->locale('ja')->isoFormat('YYYY.MM.DD (ddd)') }}</h3>
                            </div>
                            <div class="admin-group-stats">
                                <span>{{ $items->count() }}件</span>
                                <span>{{ $items->sum('party_size') }}名</span>
                            </div>
                        </div>

                        <div class="admin-reservation-list">
                            @foreach ($items as $reservation)
                            @php
                            $slot = $reservation->slot;
                            $slotStart = $slot->slot_start->timezone($timezone);
                            $slotEnd = $slot->slot_end->timezone($timezone);
                            $statusClass = $reservation->status === \App\Models\Reservation::STATUS_CANCELLED ? 'tone-cancelled' : 'tone-confirmed';
                            $slotUsage = $slot->capacity > 0 ? min(100, (int) round(($slot->reserved_count / $slot->capacity) * 100)) : 0;
                            $dialogId = 'reservation-detail-'.$reservation->getKey();
                            $isCancelled = $reservation->status === \App\Models\Reservation::STATUS_CANCELLED;
                            @endphp

                            <article class="admin-reservation-item {{ $isCancelled ? 'is-muted' : '' }}">
                                <div class="admin-reservation-summary">
                                    <div class="admin-time-cell">
                                        <strong>{{ $slotStart->format('H:i') }}</strong>
                                        <span>{{ $slotEnd->format('H:i') }}まで</span>
                                    </div>

                                    <div class="admin-primary-cell">
                                        <strong>{{ $reservation->guest_name }}</strong>
                                        <span>{{ $reservation->reservation_code }}</span>
                                    </div>

                                    <div class="admin-contact-preview">
                                        <a href="mailto:{{ $reservation->guest_email }}">{{ $reservation->guest_email }}</a>
                                        <span>{{ $reservation->guest_phone ?: '電話番号未登録' }}</span>
                                    </div>

                                    <div class="admin-reservation-pills">
                                        <span class="admin-info-pill">{{ $reservation->party_size }}名</span>
                                        <span class="admin-info-pill">席 {{ $slot->reserved_count }} / {{ $slot->capacity }}</span>
                                        <span class="admin-info-pill">残り {{ $slot->remaining_capacity }}席</span>
                                        @if ($reservation->notes)
                                        <span class="admin-info-pill is-accent">要望あり</span>
                                        @endif
                                    </div>

                                    <div class="admin-quick-actions">
                                        <span class="status-chip {{ $statusClass }}">{{ $statusLabels[$reservation->status] }}</span>

                                        <button type="button" class="admin-action-button is-secondary" data-dialog-open="{{ $dialogId }}">
                                            詳細を見る
                                        </button>

                                        <form method="post" action="{{ route('admin.reservations.update', $reservation) }}">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="status" value="{{ $isCancelled ? \App\Models\Reservation::STATUS_CONFIRMED : \App\Models\Reservation::STATUS_CANCELLED }}">
                                            <button type="submit" class="admin-action-button {{ $isCancelled ? 'is-primary' : 'is-danger' }}">
                                                {{ $isCancelled ? '確定に戻す' : 'キャンセル' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>

                            <dialog id="{{ $dialogId }}" class="admin-reservation-dialog">
                                <div class="admin-dialog-panel">
                                    <div class="admin-dialog-head">
                                        <div>
                                            <p class="eyebrow">Reservation Detail</p>
                                            <h2>{{ $reservation->guest_name }}</h2>
                                            <p class="admin-panel-copy">{{ $groupSlotStart->locale('ja')->isoFormat('YYYY.MM.DD (ddd)') }} {{ $slotStart->format('H:i') }} - {{ $slotEnd->format('H:i') }}</p>
                                        </div>

                                        <button type="button" class="admin-dialog-close" data-dialog-close>閉じる</button>
                                    </div>

                                    <div class="admin-dialog-summary">
                                        <span class="status-chip {{ $statusClass }}">{{ $statusLabels[$reservation->status] }}</span>
                                        <span class="admin-info-pill">{{ $reservation->party_size }}名</span>
                                        <span class="admin-info-pill">残り {{ $slot->remaining_capacity }}席</span>
                                    </div>

                                    <div class="admin-dialog-grid">
                                        <dl>
                                            <dt>予約コード</dt>
                                            <dd>{{ $reservation->reservation_code }}</dd>
                                        </dl>
                                        <dl>
                                            <dt>メール</dt>
                                            <dd><a href="mailto:{{ $reservation->guest_email }}">{{ $reservation->guest_email }}</a></dd>
                                        </dl>
                                        <dl>
                                            <dt>電話番号</dt>
                                            <dd>{{ $reservation->guest_phone ?: '未登録' }}</dd>
                                        </dl>
                                        <dl>
                                            <dt>在庫状況</dt>
                                            <dd>{{ $slot->reserved_count }} / {{ $slot->capacity }}席使用中</dd>
                                        </dl>
                                    </div>

                                    <div class="admin-capacity-cell admin-dialog-capacity">
                                        <div class="admin-capacity-meta">
                                            <strong>{{ $slot->reserved_count }} / {{ $slot->capacity }}席</strong>
                                            <span>残り {{ $slot->remaining_capacity }}席</span>
                                        </div>
                                        <div class="admin-capacity-meter" aria-hidden="true">
                                            <span style="width: {{ $slotUsage }}%;"></span>
                                        </div>
                                    </div>

                                    @if ($reservation->notes)
                                    <div class="admin-dialog-note">
                                        <span>ご要望</span>
                                        <p>{{ $reservation->notes }}</p>
                                    </div>
                                    @endif

                                    <div class="admin-modal-actions">
                                        <form method="post" action="{{ route('admin.reservations.update', $reservation) }}">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="status" value="{{ $isCancelled ? \App\Models\Reservation::STATUS_CONFIRMED : \App\Models\Reservation::STATUS_CANCELLED }}">
                                            <button type="submit" class="admin-action-button {{ $isCancelled ? 'is-primary' : 'is-danger' }}">
                                                {{ $isCancelled ? 'この予約を確定に戻す' : 'この予約をキャンセルにする' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </dialog>
                            @endforeach
                        </div>
                    </section>
                    @empty
                    <div class="panel-surface admin-empty-state">
                        <h3>該当する予約はありません。</h3>
                        <p>絞り込み条件を変更するか、公開予約ページから新規予約を作成してください。</p>
                    </div>
                    @endforelse
                </div>

                @if ($reservations->hasPages())
                <div class="admin-pagination">
                    {{ $reservations->onEachSide(1)->links() }}
                </div>
                @endif
            </section>
        </main>
    </div>
</body>

</html>