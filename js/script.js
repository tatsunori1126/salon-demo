/**
 * ---------------------------------------------------
 *  サロン予約モーダルカレンダー（モーダル内専用）
 * ---------------------------------------------------
 */
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reservation-modal');
    if (!modal) return; // ← モーダルがないページでは実行しない
  
    const modalCal = document.getElementById('modal-calendar');
    let selMenuKey = '', selStaffId = '', modalWeek = 0, selDate = '', selTime = '';
  
    function renderModalCalendar() {
      const fd = new FormData();
      fd.append('action', 'salon_render_calendar_front');
      fd.append('menu', selMenuKey);
      fd.append('staff', selStaffId);
      fd.append('week', modalWeek);
  
      modalCal.innerHTML = `
        <div class="modal-calendar-header" style="text-align:center; margin:10px 0;">
          <button type="button" class="btn-week" data-week="prev">← 前の週</button>
          <button type="button" class="btn-week is-today" data-week="today">今週</button>
          <button type="button" class="btn-week" data-week="next">次の週 →</button>
        </div>
        <div class="modal-calendar-body">読み込み中…</div>`;
  
      const body = modalCal.querySelector('.modal-calendar-body');
  
      fetch(salon_ajax.url, { method: 'POST', body: fd })
        .then(r => r.text())
        .then(html => { body.innerHTML = html; })
        .catch(() => { body.innerHTML = '読み込みに失敗しました'; });
    }
  
    // モーダル内だけでイベント処理
    modal.addEventListener('click', e => {
      const weekBtn = e.target.closest('.btn-week');
      if (weekBtn) {
        e.preventDefault();
        const type = weekBtn.dataset.week;
        if (type === 'prev') modalWeek--;
        if (type === 'next') modalWeek++;
        if (type === 'today') modalWeek = 0;
        renderModalCalendar();
        return;
      }
  
      const slotBtn = e.target.closest('.slot-btn');
      if (slotBtn) {
        e.preventDefault();
        modal.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
        slotBtn.classList.add('selected');
        selDate = slotBtn.dataset.date;
        selTime = slotBtn.dataset.time;
        console.log(`選択: ${selDate} ${selTime}`);
      }
    });
  });
  
  
  /**
   * ---------------------------------------------------
   *  通常カレンダー週切り替え（モーダル外専用）
   * ---------------------------------------------------
   */
  jQuery(function($) {
    // ページ内にカレンダーが存在しないなら処理しない
    if (!$('#calendar-wrapper').length) return;
  
    let currentWeek = 0;
  
    function loadCalendar() {
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'salon_calendar',
          menu_key: $('#menu_key').val(),
          staff_id: $('#staff_id').val(),
          week: currentWeek
        },
        success: function(res) {
          $('#calendar-wrapper').html(res);
          console.log('表示中の週:', currentWeek);
        },
        error: function(xhr, status, err) {
          console.error('Ajax error:', status, err);
        }
      });
    }
  
    // bodyにイベントを委譲（1回だけ登録）
    $('body')
      .off('click.salonNextWeek')
      .on('click.salonNextWeek', '#next-week', function(e) {
        e.preventDefault();
        currentWeek++;
        loadCalendar();
      });
  
    $('body')
      .off('click.salonPrevWeek')
      .on('click.salonPrevWeek', '#prev-week', function(e) {
        e.preventDefault();
        currentWeek--;
        loadCalendar();
      });
  
    // 初期表示でAjaxロードしたい場合はON
    // loadCalendar();
  });
  