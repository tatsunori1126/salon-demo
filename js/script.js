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
  
    // ✅ モーダルカレンダーの描画関数（1回だけ定義）
    function renderModalCalendar() {
      const fd = new FormData();
      fd.append('action', 'salon_render_calendar_front');
    fd.append('menu', selMenuKey);
    fd.append('staff', selStaffId);
    fd.append('week', modalWeek);
    fd.append('mode', 'front'); // ←★これを絶対入れる

  
      modalCal.innerHTML = '読み込み中…';
  
      fetch(salon_ajax.url, { method: 'POST', body: fd })
  .then(r => r.text())
  .then(html => {
    modalCal.innerHTML = html;
    const slots = modalCal.querySelectorAll('.slot-btn');
    console.log('slot-btn count:', slots.length);
    slots.forEach(btn => {
        btn.addEventListener('click', () => {
          selDate = btn.dataset.date;
          selTime = btn.dataset.time;
          selStaffId = btn.dataset.staff;
      
          const fName  = document.querySelector('#your-name') || document.querySelector('#f-name');
          const fEmail = document.querySelector('#your-email') || document.querySelector('#f-email');
          const fTel   = document.querySelector('#your-tel') || document.querySelector('#f-tel');
      
          const menuSelect = document.querySelector('#m-menu') || document.querySelector('#res_menu') || document.querySelector('#menu_key');
          const selMenuLabel = menuSelect?.options?.[menuSelect.selectedIndex]?.textContent || '-';
      
          const staffSelect = document.querySelector('#m-staff') || document.querySelector('#res_staff') || document.querySelector('#staff_id');
          const selStaffName = staffSelect?.options?.[staffSelect.selectedIndex]?.textContent || '自動割当';
      
          // ✅ ここをハイフン付きのIDに修正
          const step2 = document.querySelector('#step-2');
          const step3 = document.querySelector('#step-3');
      
          document.getElementById('c-name').textContent  = fName?.value || '-';
          document.getElementById('c-email').textContent = fEmail?.value || '-';
          document.getElementById('c-tel').textContent   = fTel?.value || '-';
          document.getElementById('c-menu').textContent  = selMenuLabel;
          document.getElementById('c-staff').textContent = selStaffName;
          document.getElementById('c-datetime').textContent = `${selDate} ${selTime}`;
      
          // ✅ ステップ切り替え（エラー対策付き）
          if (step2 && step3) {
            step2.style.display = 'none';
            step3.style.display = 'block';
          } else {
            console.warn('step-2 または step-3 が見つかりません');
          }
        });
      });
      
      
  })
  .catch(() => { modalCal.innerHTML = '読み込み失敗'; });

    }
  
    // モーダル内のイベント処理
    modal.addEventListener('click', e => {
        const weekBtn = e.target.closest('.btn-week');
        if (weekBtn) {
          e.preventDefault();
          const type = weekBtn.dataset.week;
          if (type === 'prev') modalWeek--;
          if (type === 'next') modalWeek++;
          if (type === 'today') modalWeek = 0;
      
          // ✅ 担当IDを確実に取得
          selMenuKey = document.querySelector('#m-menu')?.value
                    || document.querySelector('#res_menu')?.value
                    || document.querySelector('#menu_key')?.value
                    || 'cut';
      
          selStaffId = document.querySelector('#m-staff')?.value
                    || document.querySelector('#res_staff')?.value
                    || document.querySelector('#staff_id')?.value
                    || 0;
      
          console.log('📤 送信データ:', { menu: selMenuKey, staff: selStaffId, week: modalWeek });
      
          renderModalCalendar();
          return;
        }
      
        const slotBtn = e.target.closest('.slot-btn');
if (slotBtn) {
  e.preventDefault();

  // 選択状態を切り替え
  modal.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
  slotBtn.classList.add('selected');

  // 選択データ取得
  selDate = slotBtn.dataset.date;
  selTime = slotBtn.dataset.time;
  selStaffId = slotBtn.dataset.staff;

  // 指名なしモードの場合（data-autoassign="1"）
  const selStaffName =
    slotBtn.dataset.autoassign === '1'
      ? '自動割当'
      : (document.querySelector('#m-staff')?.selectedOptions?.[0]?.textContent || '-');

  console.log(`選択: ${selDate} ${selTime} / スタッフ: ${selStaffName}`);

  // 🔸確認画面（Step3）にデータ反映（例）
  document.getElementById('c-menu').textContent = document.querySelector('#m-menu')?.selectedOptions?.[0]?.textContent || '-';
  document.getElementById('c-staff').textContent = selStaffName;
  document.getElementById('c-datetime').textContent = `${selDate} ${selTime}`;

  // 🔸Step切り替え
  if (step2 && step3) {
    step2.style.display = 'none';
    step3.style.display = 'block';
  } else {
    console.warn('⚠ step2 または step3 が見つかりません');
  }
}
      });
      
  });
  
  
  /**
   * ---------------------------------------------------
   *  通常カレンダー週切り替え（モーダル外専用）
   * ---------------------------------------------------
   */
  jQuery(function($) {
    let currentWeek = 0;
    $.ajaxSetup({ cache: false });
  
    function loadCalendar() {
      let menuKey = $('#menu_key').val() || $('#res_menu').val() || 'cut';
      let staffId = $('#staff_id').val() || $('#res_staff').val() || 3;
  
      console.log('📤 送信データ:', { menuKey, staffId, currentWeek });
  
      const fd = new FormData();
      fd.append('action', 'salon_render_calendar_public_readonly');
      fd.append('menu_key', menuKey);
      fd.append('staff_id', staffId);
      fd.append('week', currentWeek);
  
      fetch(salon_ajax.url, { method: 'POST', body: fd })
        .then(r => r.text())
        .then(html => {
          $('#readonly-calendar').html(html);
        })
        .catch(() => alert('通信エラーが発生しました'));
    }
  
    // ▼ 週切り替えイベント
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
  
    // ▼ 初回ロード
    loadCalendar();
  });
  