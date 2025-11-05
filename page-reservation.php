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
/* “確認用”はクリック不可にする念のため */
.calendar-table .cell .slot-btn,
.calendar-table .cell a{pointer-events:none}

/* ====== モーダル ====== */
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
#modal-calendar .calendar-table .cell .slot-btn{pointer-events:auto} /* モーダル内はクリック可 */
#modal-calendar .calendar-table .cell .slot-btn{padding:4px 10px;border:1px solid #0a7;border-radius:8px;background:#fff;cursor:pointer}
#modal-calendar .calendar-table .cell .slot-btn.selected{outline:2px solid #07a;}

/* 確認画面 */
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

    <!-- ステップ1：お客様情報 -->
    <div id="step-1">
      <div class="step-title">① お客様情報</div>
      <div class="form-row">
        <label>お名前 <span style="color:#d33">*</span></label>
        <input type="text" id="f-name" class="input" placeholder="例）山田 太郎">
      </div>
      <div class="form-row">
        <label>メールアドレス</label>
        <input type="email" id="f-email" class="input" placeholder="空欄可（確認メールが不要な方）">
      </div>
      <div class="form-row">
        <label>電話番号 <span style="color:#d33">*</span></label>
        <input type="tel" id="f-tel" class="input" placeholder="例）09012345678">
      </div>
      <div class="form-actions">
        <button id="to-step-2" class="btn">次へ（メニュー選択）</button>
      </div>
    </div>

    <!-- ステップ2：メニュー／担当／日時 -->
    <div id="step-2" style="display:none;">
      <div class="step-title">② メニューと担当を選ぶ</div>
      <div class="form-row">
        <label>メニュー <span style="color:#d33">*</span></label>
        <select id="m-menu" class="select">
          <option value="">— 選択 —</option>
          <?php
            $menus = rsrv_get_menu_master();
            foreach($menus as $k=>$v){
              printf('<option value="%s" data-price="%d">%s（%s円）</option>',
                esc_attr($k), (int)$v['price'], esc_html($v['label']), number_format((int)$v['price'])
              );
            }
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

    <!-- ステップ3：確認 -->
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
        <button id="submit-reservation" class="btn">この内容で確定</button>
      </div>
      <div class="status" id="submit-status"></div>
    </div>

  </div>
</div>

<style>
/* =============== モーダルUI調整 =============== */
#reservation-modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  overflow-y: auto;
  padding: 30px 10px;
}
.modal-inner {
  background: #fff;
  border-radius: 16px;
  max-width: 720px;
  width: 95%;
  padding: 24px 28px 32px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.15);
  animation: fadeIn .3s ease;
}
@keyframes fadeIn {
  from {opacity:0; transform:translateY(-10px);}
  to {opacity:1; transform:translateY(0);}
}
.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.modal-head h3 {
  font-size: 1.3rem;
  font-weight: 600;
  margin: 0;
}
.modal-body {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.modal-body input, .modal-body select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 1rem;
}
.btn {
  display: inline-block;
  background: #222;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 20px;
  cursor: pointer;
  font-size: 1rem;
  transition: opacity .2s;
}
.btn:hover { opacity: .8; }
.btn.sub {
  background: #fff;
  border: 1px solid #222;
  color: #111;
}
.step-section { display: none; }
.step-section.active { display: block; }

.week-nav {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  margin: 10px 0 15px;
}
.week-nav .btn-week {
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 6px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 0.9rem;
}
.week-nav .btn-week:hover {
  background: #f5f5f5;
}
.week-nav .is-today {
  border-color: #000;
  font-weight: 600;
}

/* 選択した○を強調 */
.slot-btn.selected {
  background: #0066cc !important;
  color: #fff;
  border-color: #0066cc;
}

/* モーダル内容下の状態表示 */
#submit-status {
  text-align: center;
  font-size: 0.95rem;
  margin-top: 8px;
}

/* スマホ対応 */
@media(max-width:600px){
  .modal-inner{padding:20px;}
  .modal-head h3{font-size:1.1rem;}
  .btn{width:100%; text-align:center;}
}

/* --- ステップ切り替え制御 --- */
#step-1, #step-2, #step-3 { display: none; }
#step-1.active, #step-2.active, #step-3.active { display: block; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

  /* -----------------------
   * 確認用（読み取り専用）カレンダー
   * --------------------- */
  let roWeek = 0;
  const roArea = document.getElementById('readonly-calendar');
  const roPrev = document.getElementById('ro-prev');
  const roToday= document.getElementById('ro-today');
  const roNext = document.getElementById('ro-next');

  function loadReadonlyCalendar(){
    const fd = new FormData();
    fd.append('action','salon_render_calendar_public_readonly');
    fd.append('week', roWeek);
    roArea.innerHTML = '読み込み中…';
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd})
      .then(r=>r.text())
      .then(html=> roArea.innerHTML = html)
      .catch(()=> roArea.innerHTML = '読み込みに失敗しました');
  }
  roPrev.addEventListener('click', e=>{ e.preventDefault(); roWeek--; roToday.classList.remove('is-today'); loadReadonlyCalendar(); });
  roToday.addEventListener('click',e=>{ e.preventDefault(); roWeek=0; roToday.classList.add('is-today'); loadReadonlyCalendar(); });
  roNext.addEventListener('click', e=>{ e.preventDefault(); roWeek++; roToday.classList.remove('is-today'); loadReadonlyCalendar(); });
  loadReadonlyCalendar();


  /* -----------------------
   * モーダル制御
   * --------------------- */
  const modal      = document.getElementById('reservation-modal');
  const openModal  = document.getElementById('open-modal');
  const closeModal = document.getElementById('modal-close');

  const step1 = document.getElementById('step-1');
  const step2 = document.getElementById('step-2');
  const step3 = document.getElementById('step-3');

  const toStep2   = document.getElementById('to-step-2');
  const backTo1   = document.getElementById('back-to-1');
  const backTo2   = document.getElementById('back-to-2');
  const submitBtn = document.getElementById('submit-reservation');

  const fName  = document.getElementById('f-name');
  const fEmail = document.getElementById('f-email');
  const fTel   = document.getElementById('f-tel');

  const mMenu  = document.getElementById('m-menu');
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
    selDate = selTime = selStaffId = selStaffName = selMenuKey = selMenuLabel = '';
    modalWeek = 0;
    modal.style.display='flex';
  });
  closeModal.addEventListener('click', ()=> modal.style.display='none');

  // ▼ 次へ（メニュー選択）
  toStep2.addEventListener('click', ()=>{
    if(!fName.value.trim() || !fTel.value.trim()){
      alert('お名前と電話番号は必須です'); return;
    }
    step1.style.display='none';
    step2.style.display='block';
    step3.style.display='none';
  });

  // ▼ 戻る（Step1へ）
  backTo1.addEventListener('click', ()=>{
    step1.style.display='block';
    step2.style.display='none';
    step3.style.display='none';
  });

  // ▼ メニュー選択 → 対応スタッフ取得
  mMenu.addEventListener('change', ()=>{
    selMenuKey   = mMenu.value;
    selMenuLabel = mMenu.options[mMenu.selectedIndex]?.textContent || '';
    modalCal.innerHTML = '※ メニューと担当を選ぶと表示されます';
    if(!selMenuKey){
      mStaff.innerHTML='<option value="">メニューを選択してください</option>';
      mStaff.disabled=true; return;
    }
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
          const opt = document.createElement('option'); opt.value=id; opt.textContent=name;
          mStaff.appendChild(opt);
        });
        mStaff.disabled=false;
      });
  });

  // ▼ 担当選択 → カレンダー表示
  mStaff.addEventListener('change', ()=>{
    selStaffId   = mStaff.value || '';
    selStaffName = mStaff.options[mStaff.selectedIndex]?.textContent || '';
    selDate = selTime = '';
    modalWeek = 0;
    if(!selMenuKey || !selStaffId){
      modalCal.innerHTML = '※ メニューと担当を選ぶと表示されます';
      return;
    }
    renderModalCalendar();
  });


  /* -----------------------
   * カレンダー描画（週切り替え）
   * --------------------- */
  function renderModalCalendar() {
  const fd = new FormData();
  fd.append('action', 'salon_render_calendar_front');
  fd.append('menu', selMenuKey);
  fd.append('staff', selStaffId);
  fd.append('week', modalWeek);

  const modalBody = modalCal.querySelector('.modal-calendar-body');
  if (!modalBody) {
    modalCal.innerHTML = `
      <div id="modal-calendar-inner">
        <div class="modal-calendar-body">読み込み中…</div>
      </div>`;
  } else {
    modalBody.innerHTML = '読み込み中…';
  }

  // ▼ Ajax取得
  fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(html => {
      const body = modalCal.querySelector('.modal-calendar-body');

      // タイトル下に週ナビ挿入
      const modifiedHTML = html.replace(
        /(空き状況（1週間）<\/[^>]+>)/,
        `$1
        <div class="modal-calendar-header" style="text-align:center; margin:10px 0;">
          <button type="button" class="btn-week" data-week="prev">← 前の週</button>
          <button type="button" class="btn-week is-today" data-week="today">今週</button>
          <button type="button" class="btn-week" data-week="next">次の週 →</button>
        </div>`
      );

      body.innerHTML = modifiedHTML;

      // ▼ イベントを“動的に”バインド（イベントデリゲート方式）
      body.addEventListener('click', function(e) {
        const weekBtn = e.target.closest('.btn-week');
        if (weekBtn) {
          e.preventDefault();
          const type = weekBtn.dataset.week;
          if (type === 'prev') modalWeek--;
          if (type === 'next') modalWeek++;
          if (type === 'today') modalWeek = 0;
          renderModalCalendar(); // 再描画
          return;
        }

        // ○クリック処理
        const slotBtn = e.target.closest('.slot-btn');
        if (slotBtn) {
          e.preventDefault();
          body.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
          slotBtn.classList.add('selected');
          selDate = slotBtn.dataset.date;
          selTime = slotBtn.dataset.time;
          if ((selStaffId === '0' || selStaffId === 0) && slotBtn.dataset.staff) {
            selStaffId = slotBtn.dataset.staff;
          }

          document.getElementById('c-name').textContent  = fName.value || '-';
          document.getElementById('c-email').textContent = fEmail.value || '-';
          document.getElementById('c-tel').textContent   = fTel.value || '-';
          document.getElementById('c-menu').textContent  = selMenuLabel || '-';
          document.getElementById('c-staff').textContent = selStaffName || '指名なし（自動割当）';
          document.getElementById('c-datetime').textContent = selDate + ' ' + selTime;

          step1.style.display='none';
          step2.style.display='none';
          step3.style.display='block';
        }
      });
    })
    .catch(() => {
      modalCal.querySelector('.modal-calendar-body').innerHTML = '読み込みに失敗しました';
    });
}



  // ▼ 確認画面 → 修正
  backTo2.addEventListener('click', ()=>{
    step1.style.display='none';
    step2.style.display='block';
    step3.style.display='none';
  });

  // ▼ 送信処理
  submitBtn.addEventListener('click', ()=>{
    const status = document.getElementById('submit-status');
    status.style.color = '#333';
    status.textContent = '送信中…';
    submitBtn.disabled = true;

    const fd = new FormData();
    fd.append('action','salon_customer_reserve');
    fd.append('res_name',  fName.value);
    fd.append('res_email', fEmail.value);
    fd.append('res_tel',   fTel.value);
    fd.append('res_menu',  selMenuKey);
    fd.append('res_date',  selDate);
    fd.append('res_time',  selTime);
    fd.append('res_staff', selStaffId || '0');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(res=>{
        status.style.color = res.ok ? 'green' : 'red';
        status.textContent = res.msg || (res.ok ? '完了' : 'エラー');
        if(res.ok){ setTimeout(()=>location.reload(), 1000); }
      })
      .catch(()=>{
        status.style.color='red';
        status.textContent='エラーが発生しました。';
      })
      .finally(()=> submitBtn.disabled=false);
  });

});
</script>






<?php get_footer(); ?>
