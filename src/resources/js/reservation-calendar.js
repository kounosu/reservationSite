const appElement = document.querySelector('#reservation-app');
const configElement = document.querySelector('#reservation-config');

if (appElement instanceof HTMLElement && configElement instanceof HTMLScriptElement) {
    const config = JSON.parse(configElement.textContent ?? '{}');
    const locale = config.locale || 'en-US';
    const monthFormatter = new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long' });
    const dayFormatter = new Intl.DateTimeFormat(locale, { month: 'long', day: 'numeric', weekday: 'short' });

    const state = {
        month: config.initialMonth,
        selectedDate: config.initialDate,
        selectedSlotStart: null,
        data: null,
        loading: false,
        submitting: false,
        feedback: null,
        lastReservation: null,
        form: {
            guest_name: '',
            guest_email: '',
            guest_phone: '',
            party_size: '1',
            notes: '',
        },
    };

    appElement.addEventListener('click', handleClick);
    appElement.addEventListener('submit', handleSubmit);
    appElement.addEventListener('input', handleInput);
    appElement.addEventListener('change', handleInput);

    loadCalendar();

    function handleClick(event) {
        const target = event.target instanceof HTMLElement ? event.target : null;

        if (!target) {
            return;
        }

        const previousButton = target.closest('[data-action="previous-month"]');

        if (previousButton) {
            const nextMonth = shiftMonth(state.month, -1);
            state.month = nextMonth;
            state.selectedDate = alignDateToMonth(state.selectedDate, nextMonth);
            state.selectedSlotStart = null;
            state.feedback = null;
            state.lastReservation = null;
            loadCalendar();
            return;
        }

        const nextButton = target.closest('[data-action="next-month"]');

        if (nextButton) {
            const nextMonthValue = shiftMonth(state.month, 1);
            state.month = nextMonthValue;
            state.selectedDate = alignDateToMonth(state.selectedDate, nextMonthValue);
            state.selectedSlotStart = null;
            state.feedback = null;
            state.lastReservation = null;
            loadCalendar();
            return;
        }

        const dayButton = target.closest('[data-day]');

        if (dayButton instanceof HTMLButtonElement) {
            state.selectedDate = dayButton.dataset.day;
            state.selectedSlotStart = null;
            state.feedback = null;
            state.lastReservation = null;
            loadCalendar();
            return;
        }

        const slotButton = target.closest('[data-slot-start]');

        if (slotButton instanceof HTMLButtonElement) {
            const slotStart = slotButton.dataset.slotStart;
            const slot = findSlot(slotStart);

            if (!slot || !slot.isBookable) {
                return;
            }

            state.selectedSlotStart = slotStart;
            syncPartySize();
            render();
        }
    }

    function handleInput(event) {
        const target = event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement
            ? event.target
            : null;

        if (!target || !target.name) {
            return;
        }

        state.form[target.name] = target.value;
    }

    async function handleSubmit(event) {
        const form = event.target instanceof HTMLFormElement ? event.target : null;

        if (!form || form.dataset.reservationForm !== 'true') {
            return;
        }

        event.preventDefault();

        const slot = getSelectedSlot();

        if (!slot) {
            state.feedback = { type: 'error', text: 'Choose a time slot before submitting.' };
            render();
            return;
        }

        state.submitting = true;
        state.feedback = null;
        render();

        try {
            const response = await window.axios.post(config.reservationEndpoint, {
                ...state.form,
                slot_start: slot.slotStart,
            });

            state.lastReservation = response.data.reservation;
            state.feedback = { type: 'success', text: response.data.message };
            state.selectedSlotStart = null;
            state.form = {
                guest_name: '',
                guest_email: '',
                guest_phone: '',
                party_size: '1',
                notes: '',
            };

            await loadCalendar({ silent: true });
        } catch (error) {
            const status = error?.response?.status;

            if (status === 409) {
                state.feedback = { type: 'error', text: error.response.data.message };
                await loadCalendar({ silent: true });
            } else if (status === 422) {
                state.feedback = {
                    type: 'error',
                    text: Object.values(error.response.data.errors || {})
                        .flat()
                        .join(' '),
                };
            } else {
                state.feedback = { type: 'error', text: 'Reservation failed. Please try again.' };
            }
        } finally {
            state.submitting = false;
            render();
        }
    }

    async function loadCalendar(options = {}) {
        const { silent = false } = options;

        if (!silent) {
            state.loading = true;
            render();
        }

        try {
            const response = await window.axios.get(config.calendarEndpoint, {
                params: {
                    month: state.month,
                    date: state.selectedDate,
                },
            });

            state.data = response.data;
            state.month = response.data.month;
            state.selectedDate = response.data.selectedDate;

            if (state.selectedSlotStart && !findSlot(state.selectedSlotStart)) {
                state.selectedSlotStart = null;
            }

            syncPartySize();
        } catch (_error) {
            state.feedback = { type: 'error', text: 'Failed to load the calendar.' };
        } finally {
            state.loading = false;
            render();
        }
    }

    function render() {
        appElement.innerHTML = `
            <main class="reservation-layout">
                <section class="calendar-panel panel-surface">
                    <header class="calendar-header">
                        <div>
                            <p class="eyebrow">Reservation Calendar</p>
                            <h1>${escapeHtml(formatMonth(state.month))}</h1>
                        </div>
                        <div class="month-controls">
                            <button type="button" class="icon-button" data-action="previous-month" aria-label="Previous month">&larr;</button>
                            <button type="button" class="icon-button" data-action="next-month" aria-label="Next month">&rarr;</button>
                        </div>
                    </header>

                    <div class="calendar-meta">
                        <div>
                            <span>Hours</span>
                            <strong>${padNumber(config.settings.openHour)}:00 - ${padNumber(config.settings.closeHour)}:00</strong>
                        </div>
                        <div>
                            <span>Slot</span>
                            <strong>${config.settings.slotMinutes} min</strong>
                        </div>
                        <div>
                            <span>Max party</span>
                            <strong>${config.settings.maxPartySize} guests</strong>
                        </div>
                    </div>

                    <div class="calendar-weekdays">
                        ${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((weekday) => `<span>${weekday}</span>`).join('')}
                    </div>

                    <div class="calendar-grid">
                        ${buildCalendarCells()}
                    </div>
                </section>

                <aside class="booking-panel panel-surface">
                    <section class="booking-summary">
                        <p class="eyebrow">Selected Day</p>
                        <h2>${escapeHtml(formatDate(state.selectedDate))}</h2>
                        <p class="booking-caption">Slot inventory is protected with a database transaction so concurrent requests cannot overbook the same slot.</p>
                    </section>

                    ${state.feedback ? `
                        <div class="feedback feedback-${state.feedback.type}">
                            ${escapeHtml(state.feedback.text)}
                        </div>
                    ` : ''}

                    ${state.lastReservation ? `
                        <div class="confirmation-card">
                            <p class="confirmation-label">Confirmed</p>
                            <strong>${escapeHtml(state.lastReservation.code)}</strong>
                            <span>${escapeHtml(state.lastReservation.slotStart)} - ${escapeHtml(state.lastReservation.slotEnd.slice(11))}</span>
                        </div>
                    ` : ''}

                    <section class="slot-section">
                        <div class="section-head">
                            <h3>Time Slots</h3>
                            <span>${state.data ? state.data.selectedSlots.length : 0} slots</span>
                        </div>
                        <div class="slot-list">
                            ${buildSlotButtons()}
                        </div>
                    </section>

                    <section class="form-section">
                        <div class="section-head">
                            <h3>Reservation</h3>
                            <span>${selectedSlotSummary()}</span>
                        </div>
                        <form data-reservation-form="true" class="reservation-form">
                            <label>
                                <span>Name</span>
                                <input type="text" name="guest_name" value="${escapeHtml(state.form.guest_name)}" maxlength="80" placeholder="Hanako Yamada" required>
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" name="guest_email" value="${escapeHtml(state.form.guest_email)}" maxlength="120" placeholder="example@example.com" required>
                            </label>
                            <label>
                                <span>Phone</span>
                                <input type="text" name="guest_phone" value="${escapeHtml(state.form.guest_phone)}" maxlength="30" placeholder="090-1234-5678">
                            </label>
                            <label>
                                <span>Party size</span>
                                <select name="party_size" ${getSelectedSlot() ? '' : 'disabled'}>
                                    ${buildPartySizeOptions()}
                                </select>
                            </label>
                            <label>
                                <span>Notes</span>
                                <textarea name="notes" rows="4" maxlength="500" placeholder="Any notes for the reservation.">${escapeHtml(state.form.notes)}</textarea>
                            </label>
                            <button type="submit" class="submit-button" ${state.submitting || !getSelectedSlot() ? 'disabled' : ''}>
                                ${state.submitting ? 'Confirming...' : 'Book this slot'}
                            </button>
                        </form>
                    </section>
                </aside>
            </main>
        `;
    }

    function buildCalendarCells() {
        if (!state.data) {
            return '<div class="calendar-loading">Loading...</div>';
        }

        const dayMap = Object.fromEntries(state.data.days.map((day) => [day.date, day]));
        const [year, month] = state.month.split('-').map(Number);
        const firstDay = new Date(year, month - 1, 1, 12, 0, 0);
        const totalDays = new Date(year, month, 0).getDate();
        const leadingBlanks = firstDay.getDay();
        const cells = [];

        for (let index = 0; index < leadingBlanks; index += 1) {
            cells.push('<div class="calendar-cell blank"></div>');
        }

        for (let dayNumber = 1; dayNumber <= totalDays; dayNumber += 1) {
            const date = `${state.month}-${padNumber(dayNumber)}`;
            const summary = dayMap[date];

            if (!summary) {
                cells.push('<div class="calendar-cell blank"></div>');
                continue;
            }

            const classes = [
                'calendar-cell',
                summary.isToday ? 'is-today' : '',
                state.selectedDate === date ? 'is-selected' : '',
                summary.availableSlots === 0 ? 'is-quiet' : '',
            ].filter(Boolean).join(' ');

            const availabilityLabel = summary.availableSlots > 0
                ? `${summary.remainingSeats} seats left`
                : 'Fully booked';

            cells.push(`
                <button type="button" class="${classes}" data-day="${summary.date}">
                    <span class="day-number">${summary.day}</span>
                    <span class="day-meta">${summary.availableSlots}/${summary.totalSlots} open</span>
                    <span class="day-status">${availabilityLabel}</span>
                </button>
            `);
        }

        return cells.join('');
    }

    function buildSlotButtons() {
        if (!state.data) {
            return '<p class="empty-state">Loading slots...</p>';
        }

        if (state.data.selectedSlots.length === 0) {
            return '<p class="empty-state">No business hours are configured for this day.</p>';
        }

        return state.data.selectedSlots.map((slot) => {
            const selected = state.selectedSlotStart === slot.slotStart;
            const classes = [
                'slot-button',
                selected ? 'is-selected' : '',
                slot.isBookable ? '' : 'is-disabled',
            ].filter(Boolean).join(' ');

            return `
                <button type="button" class="${classes}" data-slot-start="${slot.slotStart}" ${slot.isBookable ? '' : 'disabled'}>
                    <span>${slot.startTime} - ${slot.endTime}</span>
                    <small>${slot.remainingSeats > 0 ? `${slot.remainingSeats} seats left` : 'Fully booked'}</small>
                </button>
            `;
        }).join('');
    }

    function buildPartySizeOptions() {
        const selectedSlot = getSelectedSlot();
        const maxPartySize = selectedSlot
            ? Math.min(config.settings.maxPartySize, selectedSlot.remainingSeats)
            : config.settings.maxPartySize;

        if (!selectedSlot || maxPartySize < 1) {
            return '<option value="1">1 guest</option>';
        }

        const options = [];

        for (let size = 1; size <= maxPartySize; size += 1) {
            options.push(`<option value="${size}" ${String(size) === state.form.party_size ? 'selected' : ''}>${size} guest${size > 1 ? 's' : ''}</option>`);
        }

        return options.join('');
    }

    function selectedSlotSummary() {
        const slot = getSelectedSlot();

        if (!slot) {
            return 'Select a time slot';
        }

        return `${slot.startTime} - ${slot.endTime} / ${slot.remainingSeats} seats left`;
    }

    function getSelectedSlot() {
        return findSlot(state.selectedSlotStart);
    }

    function findSlot(slotStart) {
        if (!state.data || !slotStart) {
            return null;
        }

        return state.data.selectedSlots.find((slot) => slot.slotStart === slotStart) || null;
    }

    function syncPartySize() {
        const selectedSlot = getSelectedSlot();

        if (!selectedSlot) {
            state.form.party_size = '1';
            return;
        }

        const maxPartySize = Math.min(config.settings.maxPartySize, selectedSlot.remainingSeats);

        if (Number(state.form.party_size) > maxPartySize) {
            state.form.party_size = String(Math.max(1, maxPartySize));
        }
    }

    function formatMonth(monthValue) {
        const [year, month] = monthValue.split('-').map(Number);

        return monthFormatter.format(new Date(year, month - 1, 1, 12, 0, 0));
    }

    function formatDate(dateValue) {
        const [year, month, day] = dateValue.split('-').map(Number);

        return dayFormatter.format(new Date(year, month - 1, day, 12, 0, 0));
    }

    function shiftMonth(monthValue, offset) {
        const [year, month] = monthValue.split('-').map(Number);
        const shifted = new Date(year, month - 1 + offset, 1, 12, 0, 0);

        return `${shifted.getFullYear()}-${padNumber(shifted.getMonth() + 1)}`;
    }

    function alignDateToMonth(dateValue, monthValue) {
        const day = Number(dateValue.split('-')[2] || 1);
        const [year, month] = monthValue.split('-').map(Number);
        const maxDay = new Date(year, month, 0).getDate();

        return `${monthValue}-${padNumber(Math.min(day, maxDay))}`;
    }

    function padNumber(value) {
        return String(value).padStart(2, '0');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }
}
