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

  // ======== カレンダー生成関数 ========
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

            const step2 = document.querySelector('#step-2');
            const step3 = document.querySelector('#step-3');

            document.getElementById('c-name').textContent  = fName?.value || '-';
            document.getElementById('c-email').textContent = fEmail?.value || '-';
            document.getElementById('c-tel').textContent   = fTel?.value || '-';
            document.getElementById('c-menu').textContent  = selMenuLabel;
            document.getElementById('c-staff').textContent = selStaffName;
            document.getElementById('c-datetime').textContent = `${selDate} ${selTime}`;

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

  // ======== 過去週ブロック付き：モーダル週切り替え ========
  modal.addEventListener('click', e => {
    const btn = e.target.closest('.cal-prev-week, .cal-next-week, .cal-this-week');
    if (!btn) return;

    let newWeek = modalWeek;

    if (btn.classList.contains('cal-prev-week')) newWeek--;
    if (btn.classList.contains('cal-next-week')) newWeek++;
    if (btn.classList.contains('cal-this-week')) newWeek = 0;

    // 🚫 過去週への切り替えを禁止
    if (newWeek < 0) {
      console.log('⛔ 過去週への切り替えは無効');
      return;
    }

    modalWeek = newWeek;
    console.log('🌀 モーダル週切り替え:', modalWeek);

    renderModalCalendar();
  });





// ---------------------------------------------------
// 担当選択時にのみカレンダー再描画（メニュー変更時はまだ描画しない）
// ---------------------------------------------------
modal.addEventListener('change', e => {
  // まず、現在のメニュー値と担当値を取得
  selMenuKey = document.querySelector('#m-menu')?.value
            || document.querySelector('#res_menu')?.value
            || document.querySelector('#menu_key')?.value
            || 'default';

  selStaffId = document.querySelector('#m-staff')?.value
            || document.querySelector('#res_staff')?.value
            || document.querySelector('#staff_id')?.value
            || '';

  // ✅ 条件1：担当のセレクトボックスが変更された時だけ動作
  // ✅ 条件2：メニューが選ばれていない場合は何もしない
  if (e.target.matches('#m-staff, #res_staff, #staff_id')) {
    if (!selMenuKey || selMenuKey === 'default' || selMenuKey === '0') {
      console.log('⚠ メニュー未選択のため、カレンダー表示をスキップ');
      return;
    }

    console.log('🌀 担当変更 → カレンダー再読み込み', { menu: selMenuKey, staff: selStaffId, week: modalWeek });
    renderModalCalendar();
  }
});

  
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
  


  document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.salon-calendar-wrapper');
    if (!wrapper) return;
  
    const tabs = wrapper.querySelectorAll('.salon-calendar-tabs .tab');
    const content = wrapper.querySelector('#salon-calendar-content');
  
    tabs.forEach(tab => {
      tab.addEventListener('click', function() {
        tabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
  
        const staffId = this.dataset.staff;
        const menuKey = wrapper.dataset.menu;
        const week = wrapper.dataset.week;
  
        content.innerHTML = '<p class="loading">読み込み中...</p>';
  
        // 🔧 キャッシュ無効化のために Date.now() を付与
        fetch(`${ajaxurl}?_=${Date.now()}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'salon_load_calendar',
            staff_id: staffId,
            menu_key: menuKey,
            week: week
          })
        })
        .then(res => res.text())
        .then(html => {
          content.innerHTML = html;
          console.log('✅ カレンダーを最新状態で再描画しました');
        })
        .catch(err => {
          console.error('❌ カレンダー読み込みエラー:', err);
          content.innerHTML = '<p class="error">読み込みに失敗しました。</p>';
        });
      });
    });
  });
  
  

/**
 * ===============================================
 * 予約確定ボタン処理（完了ページ遷移版）
 * ===============================================
 */
document.addEventListener('DOMContentLoaded', function() {
  const confirmBtn = document.getElementById('confirm-btn');
  if (!confirmBtn) {
    console.warn('⚠️ confirm-btn が見つかりません');
    return;
  }

  confirmBtn.addEventListener('click', async (e) => {
    e.preventDefault();

    if (confirmBtn.disabled) return; // 二重クリック防止
    console.log('✅ 予約確定ボタン押下');

    // ---------- 各入力データ取得 ----------
    const fName  = document.querySelector('#your-name') || document.querySelector('#f-name');
    const fEmail = document.querySelector('#your-email') || document.querySelector('#f-email');
    const fTel   = document.querySelector('#your-tel') || document.querySelector('#f-tel');
    const menuSelect  = document.querySelector('#m-menu') || document.querySelector('#res_menu') || document.querySelector('#menu_key');
    const selMenuKey  = menuSelect?.value || 'default';
    const staffSelect = document.querySelector('#m-staff') || document.querySelector('#res_staff') || document.querySelector('#staff_id');
    const selStaffId  = staffSelect?.value || 0;
    const selDateTime = document.getElementById('c-datetime')?.textContent?.trim() || '';
    const [selDate, selTime] = selDateTime.split(' ');

    // ---------- salon_ajax 確認 ----------
    if (!salon_ajax || !salon_ajax.url || !salon_ajax.nonce) {
      alert('nonceが正しく読み込まれていません。functions.php を確認してください。');
      console.error('❌ salon_ajax が未定義または nonce が存在しません');
      return;
    }

    console.log('📦 送信データ', { selDate, selTime, selMenuKey, selStaffId });

    // ---------- 送信用FormData ----------
    const fd = new FormData();
    fd.append('action', 'salon_submit_reservation');
    fd.append('nonce', salon_ajax.nonce);
    fd.append('name', fName?.value || '');
    fd.append('email', fEmail?.value || '');
    fd.append('tel', fTel?.value || '');
    fd.append('menu', selMenuKey);
    fd.append('staff', selStaffId);
    fd.append('date', selDate);
    fd.append('time', selTime);

    // ▼ auto_assigned を追加（これが最重要）
const slotBtnSelected = document.querySelector('.slot-btn.selected');
const autoAssigned = slotBtnSelected?.dataset.autoassign === '1' ? 1 : 0;
fd.append('auto_assigned', autoAssigned);

    // ボタン制御
    confirmBtn.disabled = true;
    confirmBtn.textContent = '送信中...';

    try {
      const res = await fetch(salon_ajax.url, { method: 'POST', body: fd });
      const json = await res.json();
      console.log('📥 応答:', json);

      // ボタンを元に戻す（エラー時）
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'この内容で確定';

      if (json.success) {
        console.log('✅ 予約成功');

        // ✅ モーダルを閉じる
        const modal = document.querySelector('.modal, .p-reservation__modal, .reservation-modal');
        if (modal) {
          modal.classList.remove('is-active', 'open', 'show', 'active');
          modal.style.display = 'none';
          modal.style.opacity = '0';
          modal.style.pointerEvents = 'none';
          modal.style.visibility = 'hidden';
        }

        // ✅ thanksページへ遷移
        // WordPressの固定ページ「thanks」などを想定
        window.location.href = `${location.origin}/reservation-thanks/?menu=${encodeURIComponent(selMenuKey)}&date=${encodeURIComponent(selDate)}&time=${encodeURIComponent(selTime)}`;

      } else {
        alert(json.data?.msg || 'エラーが発生しました。');
      }

    } catch (err) {
      console.error('通信エラー:', err);
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'この内容で確定';
      alert('通信エラーが発生しました');
    }
  });
});


document.addEventListener('DOMContentLoaded', () => {
  // 定期的に最新の更新情報をチェック（3秒おき）
  setInterval(() => {
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'salon_get_last_update' })
    })
    .then(res => res.json())
    .then(json => {
      if (!json.success || !json.data || !json.data.date) return;
      const data = json.data;

      // カレンダー内で該当セルを探す
      const dateCell = document.querySelector(
        `.cal-table th:contains("${data.date.split('-').slice(1).join('/')}")
        `
      );

      if (!dateCell) return;

      const table = dateCell.closest('.cal-table');
      const allRows = table.querySelectorAll('tbody tr');

      allRows.forEach(row => {
        const timeCell = row.querySelector('th');
        const time = timeCell ? timeCell.textContent.trim() : '';

        if (time === data.time) {
          const targetCell = row.querySelector(`td.available, td.booked`);
          if (targetCell) {
            targetCell.className = data.staff > 0 ? 'booked' : 'available';
            targetCell.textContent = data.staff > 0 ? '×' : '○';
          }
        }
      });
    })
    .catch(err => console.error('更新チェックエラー:', err));
  }, 3000);
});


