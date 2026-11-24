<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/academic_events.php';
require_once __DIR__ . '/admin_layout.php';

requireLogin(['admin']);

if (($_GET['events'] ?? '') === '1') {
    $start = trim((string) ($_GET['start'] ?? ''));
    $end = trim((string) ($_GET['end'] ?? ''));
    $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);

    if ($startDate === false || $endDate === false
        || $startDate->format('Y-m-d') !== $start
        || $endDate->format('Y-m-d') !== $end
        || $endDate <= $startDate
        || $startDate->diff($endDate)->days > 370) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Rentang tanggal kalender tidak valid.']);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['events' => fetchAcademicEvents($koneksi, $start, $end)],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

$user = currentUser();
$sidebarMenus = adminSidebarMenus('/dashboard/admin_jadwal.php');

renderAdminLayoutStart(
    'Jadwal Akademik',
    'Jadwal Akademik',
    'Kalender agenda bimbingan, seminar, dan sidang dari database.',
    $user,
    $sidebarMenus
);
?>
<section class="info-card full-calendar-card">
  <div class="calendar-toolbar">
    <div class="calendar-navigation">
      <button class="calendar-button" id="previousPeriod" type="button" aria-label="Periode sebelumnya">&lsaquo;</button>
      <button class="calendar-button" id="todayButton" type="button">Hari ini</button>
      <button class="calendar-button" id="nextPeriod" type="button" aria-label="Periode berikutnya">&rsaquo;</button>
      <h2 id="calendarTitle">Jadwal Akademik</h2>
    </div>
    <div class="view-switch" role="group" aria-label="Tampilan kalender">
      <button class="view-button is-active" data-view="month" type="button">Bulan</button>
      <button class="view-button" data-view="week" type="button">Minggu</button>
      <button class="view-button" data-view="day" type="button">Hari</button>
    </div>
  </div>
  <div class="calendar-legend">
    <span><i class="legend-dot guidance"></i>Bimbingan</span>
    <span><i class="legend-dot proposal"></i>Seminar proposal</span>
    <span><i class="legend-dot result"></i>Seminar hasil</span>
    <span><i class="legend-dot defense"></i>Sidang</span>
  </div>
  <div id="calendarGrid" class="calendar-grid" aria-live="polite">Memuat agenda…</div>
</section>

<dialog id="eventDialog" class="event-dialog">
  <div class="dialog-heading">
    <h2 id="eventTitle">Detail Agenda</h2>
    <button id="closeDialog" class="calendar-button" type="button" aria-label="Tutup">×</button>
  </div>
  <dl id="eventDetails" class="event-details"></dl>
</dialog>
<style>
.full-calendar-card { min-height: 75vh; }
.calendar-toolbar, .calendar-navigation, .view-switch, .calendar-legend, .dialog-heading { display: flex; align-items: center; gap: 10px; }
.calendar-toolbar { justify-content: space-between; flex-wrap: wrap; margin-bottom: 16px; }
.calendar-navigation h2 { margin: 0 0 0 8px; font-size: 1.15rem; }
.calendar-button, .view-button { border: 1px solid #d5dbe5; border-radius: 10px; padding: 8px 12px; background: #fff; color: #475061; font: inherit; font-weight: 800; cursor: pointer; }
.view-button.is-active, .calendar-button:hover { border-color: #4d38c7; background: #4d38c7; color: #fff; }
.calendar-legend { flex-wrap: wrap; margin-bottom: 12px; color: #67707d; font-size: .85rem; font-weight: 700; }
.calendar-legend span { display: inline-flex; align-items: center; gap: 6px; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; background: #4d38c7; }
.legend-dot.proposal { background: #177d8b; }
.legend-dot.result { background: #c17a13; }
.legend-dot.defense { background: #c43b45; }
.calendar-grid { min-height: 60vh; }
.month-weekdays, .month-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; }
.month-weekdays { margin-bottom: 5px; text-align: center; color: #67707d; font-size: .86rem; font-weight: 800; }
.month-cell { min-height: 110px; padding: 7px; overflow: hidden; border: 1px solid #e1e6ef; border-radius: 10px; background: #fff; }
.month-cell.is-outside { background: #f7f8fb; color: #9aa2af; }
.month-cell.is-today, .period-day.is-today { border-color: #4d38c7; box-shadow: inset 0 0 0 1px #4d38c7; }
.day-number { margin-bottom: 5px; font-weight: 800; }
.calendar-event { display: block; width: 100%; margin: 3px 0; padding: 4px 6px; overflow: hidden; border: 0; border-radius: 6px; background: #eeeaff; color: #4d38c7; font: inherit; font-size: .72rem; text-align: left; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
.calendar-event.category-seminar-proposal { background: #e5f4f4; color: #126d79; }
.calendar-event.category-seminar-hasil { background: #fff2dc; color: #955d08; }
.calendar-event.category-sidang { background: #fde9eb; color: #a52e38; }
.week-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
.period-day { min-height: 420px; padding: 10px; border: 1px solid #e1e6ef; border-radius: 12px; }
.period-day h3 { margin: 0 0 10px; font-size: .9rem; }
.day-view { max-width: 900px; margin: 0 auto; }
.day-view .period-day { min-height: 420px; }
.empty-calendar { padding: 32px; color: #67707d; text-align: center; }
.event-dialog { width: min(560px, calc(100vw - 32px)); max-height: 85vh; padding: 22px; border: 0; border-radius: 18px; box-shadow: 0 20px 70px #0004; }
.event-dialog::backdrop { background: #18203388; }
.dialog-heading { justify-content: space-between; }
.dialog-heading h2 { margin: 0; }
.event-details { display: grid; grid-template-columns: 130px 1fr; gap: 10px; margin: 20px 0 0; }
.event-details dt { color: #67707d; font-weight: 800; }
.event-details dd { margin: 0; overflow-wrap: anywhere; }
@media (max-width: 760px) {
  .month-cell { min-height: 78px; padding: 4px; }
  .calendar-event { font-size: .62rem; }
  .week-grid { grid-template-columns: 1fr; }
  .period-day { min-height: 150px; }
}
</style>
<script>
(function () {
  var currentDate = new Date();
  var currentView = 'month';
  var calendarGrid = document.getElementById('calendarGrid');
  var calendarTitle = document.getElementById('calendarTitle');
  var dialog = document.getElementById('eventDialog');
  var eventTitle = document.getElementById('eventTitle');
  var eventDetails = document.getElementById('eventDetails');
  var endpoint = <?= json_encode(appPath('/dashboard/admin_jadwal.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var weekdayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  var longDate = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  function dateOnly(date) {
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
  }
  function startOfWeek(date) {
    var result = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    result.setDate(result.getDate() - ((result.getDay() + 6) % 7));
    return result;
  }
  function eventClass(event) {
    if (event.category === 'Seminar Proposal') return 'category-seminar-proposal';
    if (event.category === 'Seminar Hasil') return 'category-seminar-hasil';
    if (event.category === 'Sidang Tugas Akhir') return 'category-sidang';
    return 'category-bimbingan';
  }
  function eventTime(event) {
    var date = new Date(String(event.starts_at).replace(' ', 'T'));
    return date.getHours() === 0 && date.getMinutes() === 0
      ? ''
      : new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(date) + ' ';
  }
  function showEvent(event) {
    eventTitle.textContent = event.title || event.category;
    eventDetails.replaceChildren();
    var rows = [
      ['Kategori', event.category],
      ['Tanggal', longDate.format(new Date(String(event.starts_at).replace(' ', 'T')))],
      ['Waktu', eventTime(event).trim() || 'Tidak ditentukan'],
      ['Waktu selesai', 'Tidak tersedia pada database'],
      ['Lokasi', event.location || 'Tidak tersedia'],
      ['Mahasiswa', event.student_name || 'Tidak tersedia'],
      ['NIM', event.nim || 'Tidak tersedia'],
      ['Dosen terkait', event.lecturer_name || 'Tidak tersedia'],
      ['Status', event.status || 'Tidak tersedia'],
      ['Catatan', event.details || 'Tidak ada catatan'],
    ];
    rows.forEach(function (row) {
      var term = document.createElement('dt');
      var description = document.createElement('dd');
      term.textContent = row[0];
      description.textContent = row[1];
      eventDetails.append(term, description);
    });
    dialog.showModal();
  }
  function createEventButton(event) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'calendar-event ' + eventClass(event);
    button.textContent = eventTime(event) + (event.title || event.category);
    button.title = button.textContent;
    button.addEventListener('click', function () { showEvent(event); });
    return button;
  }
  function rangeForView() {
    if (currentView === 'month') {
      var first = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
      var last = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
      var start = startOfWeek(first);
      var end = startOfWeek(last);
      end.setDate(end.getDate() + 7);
      return { start: start, end: end };
    }
    if (currentView === 'week') {
      var weekStart = startOfWeek(currentDate);
      var weekEnd = new Date(weekStart);
      weekEnd.setDate(weekEnd.getDate() + 7);
      return { start: weekStart, end: weekEnd };
    }
    var dayStart = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
    var dayEnd = new Date(dayStart);
    dayEnd.setDate(dayEnd.getDate() + 1);
    return { start: dayStart, end: dayEnd };
  }
  function eventsOn(events, date) {
    var key = dateOnly(date);
    return events.filter(function (event) { return String(event.starts_at).slice(0, 10) === key; });
  }
  function renderMonth(events) {
    var first = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    var range = rangeForView();
    calendarTitle.textContent = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(currentDate);
    var weekdays = document.createElement('div');
    weekdays.className = 'month-weekdays';
    weekdayNames.slice(1).concat(weekdayNames[0]).forEach(function (name) {
      var label = document.createElement('span');
      label.textContent = name;
      weekdays.append(label);
    });
    var grid = document.createElement('div');
    grid.className = 'month-grid';
    for (var day = new Date(range.start); day < range.end; day.setDate(day.getDate() + 1)) {
      var cellDate = new Date(day);
      var cell = document.createElement('div');
      cell.className = 'month-cell';
      if (cellDate.getMonth() !== first.getMonth()) cell.classList.add('is-outside');
      if (dateOnly(cellDate) === dateOnly(new Date())) cell.classList.add('is-today');
      var number = document.createElement('div');
      number.className = 'day-number';
      number.textContent = String(cellDate.getDate());
      cell.append(number);
      var dayEvents = eventsOn(events, cellDate);
      dayEvents.slice(0, 3).forEach(function (event) { cell.append(createEventButton(event)); });
      if (dayEvents.length > 3) {
        var more = document.createElement('small');
        more.textContent = '+' + (dayEvents.length - 3) + ' agenda lainnya';
        cell.append(more);
      }
      grid.append(cell);
    }
    calendarGrid.replaceChildren(weekdays, grid);
  }
  function renderDays(events, dates, view) {
    var grid = document.createElement('div');
    grid.className = view === 'week' ? 'week-grid' : 'day-view';
    dates.forEach(function (date) {
      var cell = document.createElement('section');
      cell.className = 'period-day';
      if (dateOnly(date) === dateOnly(new Date())) cell.classList.add('is-today');
      var heading = document.createElement('h3');
      heading.textContent = longDate.format(date);
      cell.append(heading);
      var dayEvents = eventsOn(events, date);
      if (dayEvents.length === 0) {
        var empty = document.createElement('p');
        empty.className = 'empty-calendar';
        empty.textContent = 'Tidak ada agenda.';
        cell.append(empty);
      } else {
        dayEvents.forEach(function (event) { cell.append(createEventButton(event)); });
      }
      grid.append(cell);
    });
    calendarGrid.replaceChildren(grid);
  }
  function render(events) {
    if (currentView === 'month') {
      renderMonth(events);
      return;
    }
    var start = currentView === 'week' ? startOfWeek(currentDate) : new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
    var count = currentView === 'week' ? 7 : 1;
    var dates = [];
    for (var index = 0; index < count; index++) {
      var date = new Date(start);
      date.setDate(start.getDate() + index);
      dates.push(date);
    }
    calendarTitle.textContent = currentView === 'week'
      ? dates[0].toLocaleDateString('id-ID') + ' – ' + dates[6].toLocaleDateString('id-ID')
      : longDate.format(dates[0]);
    renderDays(events, dates, currentView);
  }
  function loadEvents() {
    var range = rangeForView();
    calendarGrid.textContent = 'Memuat agenda…';
    var url = endpoint + '?events=1&start=' + encodeURIComponent(dateOnly(range.start))
      + '&end=' + encodeURIComponent(dateOnly(range.end));
    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (response) {
        if (!response.ok) throw new Error('Agenda gagal dimuat (' + response.status + ').');
        return response.json();
      })
      .then(function (payload) {
        if (!Array.isArray(payload.events)) throw new Error(payload.error || 'Format data agenda tidak valid.');
        render(payload.events);
      })
      .catch(function (error) {
        calendarGrid.textContent = error.message || 'Agenda gagal dimuat.';
      });
  }
  document.getElementById('previousPeriod').addEventListener('click', function () {
    if (currentView === 'month') currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
    else currentDate.setDate(currentDate.getDate() - (currentView === 'week' ? 7 : 1));
    loadEvents();
  });
  document.getElementById('nextPeriod').addEventListener('click', function () {
    if (currentView === 'month') currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
    else currentDate.setDate(currentDate.getDate() + (currentView === 'week' ? 7 : 1));
    loadEvents();
  });
  document.getElementById('todayButton').addEventListener('click', function () {
    currentDate = new Date();
    loadEvents();
  });
  document.querySelectorAll('.view-button').forEach(function (button) {
    button.addEventListener('click', function () {
      currentView = button.dataset.view;
      document.querySelectorAll('.view-button').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      loadEvents();
    });
  });
  document.getElementById('closeDialog').addEventListener('click', function () { dialog.close(); });
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) dialog.close();
  });
  loadEvents();
})();
</script>
<?php renderAdminLayoutEnd(); ?>
