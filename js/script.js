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
  
        fetch(ajaxurl, {
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
        })
        .catch(err => {
          content.innerHTML = '<p class="error">読み込みに失敗しました。</p>';
        });
      });
    });
  });
  

/**
 * ===============================================
 *  予約確定ボタン処理（完全版）
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

    if (confirmBtn.disabled) return; // ← 二重クリック防止

    console.log('✅ 予約確定ボタン押下');

    // ---------- Stepエレメント ----------
    const step2 = document.querySelector('#step-2');
    const step3 = document.querySelector('#step-3');

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
      console.error('❌ salon_ajax が未定義または nonce が存在しません');
      alert('nonceが正しく読み込まれていません。functions.php を確認してください。');
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

    // 一時的に無効化（送信中だけ）
    confirmBtn.disabled = true;
    confirmBtn.textContent = '送信中...';

    try {
      const res = await fetch(salon_ajax.url, { method: 'POST', body: fd });
      const json = await res.json();

      console.log('📥 応答:', json);

      // ボタンを戻す（エラー時などに再クリック可能に）
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'この内容で確定';

      if (json.success) {
        alert(json.data.msg || 'ご予約を受け付けました！');

        // ✅ 完全に無効化（予約完了後のみ）
        confirmBtn.disabled = true;
        confirmBtn.textContent = '予約済み';

        // ✅ カレンダー再描画
        const calendarContainer = document.querySelector('.salon-calendar');
        if (calendarContainer) {
          fetch(`${location.origin}/wp-admin/admin-ajax.php?action=salon_get_calendar_html&menu_key=${selMenuKey}&staff_id=${selStaffId}`)
            .then((res) => res.text())
            .then((html) => {
              calendarContainer.innerHTML = html;
              console.log('✅ カレンダー更新完了');
            })
            .catch((err) => console.error('❌ カレンダー再取得エラー:', err));
        }

        // ✅ モーダルを自動で閉じる処理（再オープン対応版）
        try {
          // ① 閉じるボタンをクリックして閉じる（ライブラリが反応）
          const closeBtn = document.querySelector(
            '.modal-close, .js-modal-close, .p-reservation__modal-close, [data-modal-close], .close-btn'
          );
          if (closeBtn) {
            console.log('🕓 閉じるボタンをクリックしてモーダルを閉じます');
            closeBtn.click();
          } else {
            // ② フォールバック：クラス制御のみ（display:none等は操作しない）
            const modal = document.querySelector('.modal, .p-reservation__modal, .reservation-modal');
            if (modal) {
              modal.classList.remove('is-active', 'open', 'show', 'active');
              modal.style.opacity = '';          // リセット
              modal.style.pointerEvents = '';    // リセット
              modal.style.visibility = '';       // リセット
              modal.style.display = '';          // リセット
              console.log('✅ モーダルを閉じました（再オープン可能）');
            } else {
              console.warn('⚠️ モーダル要素が見つかりませんでした。');
            }
          }
        } catch (err) {
          console.error('❌ モーダル閉鎖処理でエラー:', err);
        }

        // ✅ 完了ステップ表示（任意）
        if (step2 && step3) {
          step2.style.display = 'none';
          step3.style.display = 'block';
        }

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


