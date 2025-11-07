<?php
/**
 * Template Name: Reservation
 */
get_header(); ?>
<style>
/* ====== レイアウト ====== */
.resv-wrap{max-width:1080px;margin:40px auto;padding:0 16px;}
.resv-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.btn{display:inline-block;border:1px solid #222;background:#111;color:#fff;padding:10px 16px;border-radius:10px;cursor:pointer;text-decoration:none}
.btn.sub{background:#fff;color:#111}
.btn:disabled{opacity:.5;cursor:not-allowed}
.readonly-card{background:#fff;border:1px solid #eee;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.04);padding:18px;margin-bottom:18px;}
.readonly-title{font-size:1.1rem;font-weight:600;margin:0 0 10px;}
.week-nav{display:flex;gap:8px;margin-bottom:10px}
.week-nav .btn-week{border:1px solid #ddd;background:#fafafa;color:#333;padding:6px 10px;border-radius:8px;text-decoration:none}
.week-nav .btn-week.is-today{background:#eaf4ff;border-color:#c8dfff}
.cal-legend{display:flex;gap:16px;margin-bottom:8px;font-size:.9rem;opacity:.85}
.calendar-table{width:100%;border-collapse:collapse;table-layout:fixed}
.calendar-table th,.calendar-table td{border:1px solid #eee;text-align:center;padding:8px;font-size:13px}
.calendar-table .time-col{background:#fafafa;width:86px}
.calendar-table .day-group{background:#f8fbff;font-weight:700}
.calendar-table .staff-col{background:#fbfbfb;font-weight:600}
.calendar-table .cell.off{color:#bbb;background:#fcfcfc}
.calendar-table .cell.available{background:#f7fff6}
.calendar-table .cell.booked{background:#fff5f5;color:#d33}
.calendar-table .sep {border-right:2px solid #e5ecff}
.calendar-table .cell .slot-btn,
.calendar-table .cell a{pointer-events:none}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:9999}
.modal-inner{background:#fff;border-radius:14px;padding:18px;min-width:340px;max-width:720px;width:92%}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.modal-head h3{margin:0;font-size:1.1rem}
.step-title{font-weight:700;margin:10px 0 6px}
.form-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.form-row label{min-width:110px;font-weight:600}
.input, .select{min-width:220px;padding:10px;border:1px solid #ddd;border-radius:10px}
.hint{font-size:.88rem;opacity:.75}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:8px}
.status{margin-top:6px;font-size:.95rem}
#modal-calendar .calendar-table .cell .slot-btn{pointer-events:auto}
#modal-calendar .calendar-table .cell .slot-btn{padding:4px 10px;border:1px solid #0a7;border-radius:8px;background:#fff;cursor:pointer}
#modal-calendar .calendar-table .cell .slot-btn.selected{outline:2px solid #07a;}
.confirm-box{background:#fafafa;border:1px solid #eee;border-radius:12px;padding:14px;margin:8px 0}
.confirm-row{display:flex;gap:8px;margin:6px 0}
.confirm-row dt{min-width:110px;font-weight:600}
.confirm-row dd{margin:0}
</style>

<div class="resv-wrap">
  <div class="resv-head">
    <h2 style="margin:0;font-size:1.2rem;">ご予約</h2>
    <button id="open-modal" class="btn">＋ 予約する</button>
  </div>

  <div class="readonly-card">
    <div class="readonly-title">現在の空き状況（本日から1週間）</div>
    <div class="cal-legend"><span>○：空き（シフト内・未予約）</span><span>×：予約あり</span><span>—：シフト外</span></div>
    <div class="week-nav">
      <a href="#" class="btn-week" id="ro-prev">← 前の週</a>
      <a href="#" class="btn-week is-today" id="ro-today">今週</a>
      <a href="#" class="btn-week" id="ro-next">次の週 →</a>
    </div>
    <div id="readonly-calendar">読み込み中…</div>
  </div>
</div>

<!-- ====== 予約モーダル ====== -->
<div class="modal" id="reservation-modal">
  <div class="modal-inner">
    <div class="modal-head">
      <h3>予約フォーム</h3>
      <button class="btn sub" id="modal-close">閉じる</button>
    </div>

    <!-- ステップ1 -->
    <div id="step-1">
      <div class="step-title">① お客様情報</div>
      <div class="form-row"><label>お名前 <span style="color:#d33">*</span></label><input type="text" id="f-name" class="input" placeholder="例）山田 太郎"></div>
      <div class="form-row"><label>メールアドレス</label><input type="email" id="f-email" class="input" placeholder="空欄可（確認メールが不要な方）"></div>
      <div class="form-row"><label>電話番号 <span style="color:#d33">*</span></label><input type="tel" id="f-tel" class="input" placeholder="例）09012345678"></div>
      <div class="form-actions"><button id="to-step-2" class="btn">次へ（メニュー選択）</button></div>
    </div>

    <!-- ステップ2 -->
<div id="step-2" style="display:none;">
  <div class="step-title">② メニューと担当を選ぶ</div>

  <div class="form-row">
    <label>メニュー <span style="color:#d33">*</span></label>
    <select id="m-menu" class="select">
  <option value="">— 選択 —</option>
  <?php
    $store = get_option('salon_store_settings', []);
    if (!empty($store['menus'])):
      foreach ($store['menus'] as $m):
        if (empty($m['name'])) continue;
        // 🔸 sanitize_title は絶対に使わない！！
        $key = $m['name']; // ← 日本語そのまま
        $price = isset($m['price']) ? number_format((int)$m['price']) : '';
        echo '<option value="'.esc_attr($key).'" data-price="'.esc_attr($price).'">'.esc_html($m['name']);
        if ($price) echo '（'.$price.'円）';
        echo '</option>';
      endforeach;
    else:
      echo '<option value="">店舗設定でメニューを登録してください</option>';
    endif;
  ?>
</select>
  </div>

  <div class="form-row">
    <label>担当 <span style="color:#d33">*</span></label>
    <select id="m-staff" class="select" disabled>
      <option value="">メニューを選択してください</option>
    </select>
    <span class="hint">※「指名なし」を選ぶと空いている担当者が自動割当</span>
  </div>

  <div class="step-title" style="margin-top:8px;">③ 日時を選ぶ</div>
  <div id="modal-calendar" class="form-row" style="width:100%;">※ メニューと担当を選ぶと表示されます</div>

  <div class="form-actions">
    <button id="back-to-1" class="btn sub">戻る</button>
    <button id="to-confirm" class="btn" disabled>次へ（確認）</button>
  </div>
</div>


    <!-- ステップ3 -->
    <div id="step-3" style="display:none;">
      <div class="step-title">④ 内容確認</div>
      <div class="confirm-box">
        <dl class="confirm-row"><dt>お名前</dt><dd id="c-name">-</dd></dl>
        <dl class="confirm-row"><dt>メール</dt><dd id="c-email">-</dd></dl>
        <dl class="confirm-row"><dt>電話</dt><dd id="c-tel">-</dd></dl>
        <dl class="confirm-row"><dt>メニュー</dt><dd id="c-menu">-</dd></dl>
        <dl class="confirm-row"><dt>担当</dt><dd id="c-staff">-</dd></dl>
        <dl class="confirm-row"><dt>日時</dt><dd id="c-datetime">-</dd></dl>
      </div>
      <div class="form-actions">
        <button id="back-to-2" class="btn sub">修正する</button>
        <button id="confirm-btn" class="btn">この内容で確定</button>
      </div>
      <div class="status" id="submit-status"></div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  let roWeek = 0;
  const roArea = document.getElementById('readonly-calendar');
  const roPrev = document.getElementById('ro-prev');
  const roToday = document.getElementById('ro-today');
  const roNext = document.getElementById('ro-next');

  function loadReadonlyCalendar() {
    const fd = new FormData();
    fd.append('action', 'salon_render_readonly_calendar_ajax');
    fd.append('week', roWeek);
    roArea.innerHTML = '読み込み中…';
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
      .then(r => r.text())
      .then(html => {
        roArea.innerHTML = html;
      })
      .catch(() => { roArea.innerHTML = '<div style="color:red;">読み込みに失敗しました</div>'; });
  }
  roPrev.addEventListener('click', e => { 
  e.preventDefault(); 
  if (roWeek > 0) { 
    roWeek--; 
    loadReadonlyCalendar(); 
  } 
});

roToday.addEventListener('click', e => { 
  e.preventDefault(); 
  roWeek = 0; 
  loadReadonlyCalendar(); 
});

roNext.addEventListener('click', e => { 
  e.preventDefault(); 
  roWeek++; 
  loadReadonlyCalendar(); 
});

// ✅ 初回だけ少し遅らせて確実に実行（Local環境でのPOST遅延対策）
setTimeout(() => {
  roWeek = 0; // 念のため初期化
  loadReadonlyCalendar();
}, 300);


  const modal = document.getElementById('reservation-modal');
  const openModal = document.getElementById('open-modal');
  const closeModal = document.getElementById('modal-close');
  const step1 = document.getElementById('step-1');
  const step2 = document.getElementById('step-2');
  const step3 = document.getElementById('step-3');
  const toStep2 = document.getElementById('to-step-2');
  const backTo1 = document.getElementById('back-to-1');
  const backTo2 = document.getElementById('back-to-2');
  const submitBtn = document.getElementById('submit-reservation');
  const fName = document.getElementById('f-name');
  const fEmail = document.getElementById('f-email');
  const fTel = document.getElementById('f-tel');
  const mMenu = document.getElementById('m-menu');
  const mStaff = document.getElementById('m-staff');
  const modalCal = document.getElementById('modal-calendar');

  let selDate = '', selTime = '', selStaffId = '', selStaffName = '', selMenuKey = '', selMenuLabel = '';
  let modalWeek = 0;

  openModal.addEventListener('click', ()=> {
    step1.style.display='block';
    step2.style.display='none';
    step3.style.display='none';
    fName.value = ''; fEmail.value=''; fTel.value='';
    mMenu.value=''; mStaff.innerHTML='<option value="">メニューを選択してください</option>'; mStaff.disabled = true;
    modalCal.innerHTML = '※ メニューと担当を選ぶと表示されます';
    modal.style.display='flex';
  });
  closeModal.addEventListener('click', ()=> modal.style.display='none');

  toStep2.addEventListener('click', ()=>{
    if(!fName.value.trim() || !fTel.value.trim()){ alert('お名前と電話番号は必須です'); return; }
    step1.style.display='none'; step2.style.display='block'; step3.style.display='none';
  });
  backTo1.addEventListener('click', ()=>{ step1.style.display='block'; step2.style.display='none'; step3.style.display='none'; });

  mMenu.addEventListener('change', ()=>{
    selMenuKey = mMenu.value;
    selMenuLabel = mMenu.options[mMenu.selectedIndex]?.textContent || '';
    modalCal.innerHTML = '※ メニューと担当を選ぶと表示されます';
    if(!selMenuKey){ mStaff.innerHTML='<option value="">メニューを選択してください</option>'; mStaff.disabled=true; return; }
    const fd = new FormData();
    fd.append('action','salon_get_staffs_by_menu_front');
    fd.append('menu_key', selMenuKey);
    mStaff.disabled=true;
    mStaff.innerHTML='<option value="">読み込み中…</option>';
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(list=>{
        mStaff.innerHTML='<option value="">— 選択 —</option>';
        Object.entries(list).forEach(([id,name])=>{
          const opt=document.createElement('option'); opt.value=id; opt.textContent=name; mStaff.appendChild(opt);
        });
        mStaff.disabled=false;
      });
  });

  mStaff.addEventListener('change', ()=>{
    selStaffId = mStaff.value || '';
    selStaffName = mStaff.options[mStaff.selectedIndex]?.textContent || '';
    selDate = selTime = '';
    modalWeek = 0;
    if(!selMenuKey || !selStaffId){ modalCal.innerHTML = '※ メニューと担当を選ぶと表示されます'; return; }
    renderModalCalendar();
  });

  function renderModalCalendar() {
  // === 毎回フォームから取得（スコープ外対策） ===
  const menuSelect = document.getElementById('m-menu') || document.getElementById('res_menu');
  const staffSelect = document.getElementById('m-staff') || document.getElementById('res_staff');

  const currentMenuKey = menuSelect ? menuSelect.value : (selMenuKey || '');
  const currentStaffId = staffSelect ? staffSelect.value : (selStaffId || 0);

  const fd = new FormData();
  fd.append('action', 'salon_render_calendar_front');
  fd.append('menu', currentMenuKey);
  fd.append('staff', currentStaffId);
  fd.append('week', modalWeek);
  fd.append('mode', 'front');

  console.log('📤送信データ:', Object.fromEntries(fd));

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

          document.getElementById('c-name').textContent = fName.value;
          document.getElementById('c-email').textContent = fEmail.value || '-';
          document.getElementById('c-tel').textContent = fTel.value;
          document.getElementById('c-menu').textContent = currentMenuKey;
          document.getElementById('c-staff').textContent = selStaffName || '自動割当';
          document.getElementById('c-datetime').textContent = `${selDate} ${selTime}`;

          step2.style.display = 'none';
          step3.style.display = 'block';
        });
      });
    })
    .catch(() => {
      modalCal.innerHTML = '読み込み失敗';
    });
}



  backTo2.addEventListener('click', ()=>{ step2.style.display='block'; step3.style.display='none'; });
  submitBtn.addEventListener('click', ()=>{
    const status=document.getElementById('submit-status'); status.style.color='#333'; status.textContent='送信中…'; submitBtn.disabled=true;
    const fd=new FormData();
    fd.append('action','salon_customer_reserve');
    fd.append('res_name',fName.value); fd.append('res_email',fEmail.value); fd.append('res_tel',fTel.value);
    fd.append('res_menu',selMenuKey); fd.append('res_date',selDate); fd.append('res_time',selTime); fd.append('res_staff',selStaffId||'0');
    fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(res=>{
        status.style.color=res.ok?'green':'red';
        status.textContent=res.msg||(res.ok?'完了':'エラー');
        if(res.ok){setTimeout(()=>location.reload(),1000);}
      })
      .catch(()=>{status.style.color='red';status.textContent='通信エラー';})
      .finally(()=>submitBtn.disabled=false);
  });
});
</script>
<?php get_footer(); ?>