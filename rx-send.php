<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Send your prescription</title>
  <style>
    *{box-sizing:border-box;margin:0;font-family:-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
    body{min-height:100vh;background:#0f4d42;background:linear-gradient(160deg,#176B5B,#0f4d42);color:#12302a;display:flex;align-items:flex-start;justify-content:center;padding:20px 14px}
    .card{width:100%;max-width:420px;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.28)}
    .head{padding:22px 20px 16px;background:linear-gradient(160deg,#176B5B,#12604f);color:#fff}
    .head h1{font-size:1.22rem;font-weight:800;letter-spacing:-.01em}
    .head p{margin-top:4px;font-size:.84rem;opacity:.9;line-height:1.45}
    .body{padding:18px 20px 22px}
    .drop{border:2px dashed #9cc7bf;border-radius:14px;background:#f3faf8;padding:22px 14px;text-align:center;cursor:pointer}
    .drop .ic{font-size:2rem;line-height:1}
    .drop b{display:block;margin-top:8px;font-size:.95rem;color:#0f4d42}
    .drop span{display:block;margin-top:4px;font-size:.76rem;color:#6d8f89}
    .prev{margin-top:12px;display:none;border-radius:12px;overflow:hidden;border:1px solid #d7e7e3}
    .prev img{width:100%;display:block}
    label.f{display:block;margin-top:14px;font-size:.72rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#5f7f79}
    input[type=tel]{margin-top:5px;width:100%;padding:11px 13px;border:1.5px solid #cfdfe2;border-radius:10px;font-size:1rem;color:#12302a;outline:none}
    input[type=tel]:focus{border-color:#176B5B}
    .hint{margin-top:8px;font-size:.74rem;color:#7d968f;line-height:1.5}
    .send{margin-top:16px;width:100%;border:0;border-radius:12px;padding:14px;background:#176B5B;color:#fff;font-size:1.02rem;font-weight:800;cursor:pointer}
    .send:disabled{opacity:.55}
    .err{margin-top:12px;display:none;background:#fdecea;color:#B42318;border:1px solid #f4c7c2;border-radius:10px;padding:10px 12px;font-size:.82rem;line-height:1.45}
    .done{display:none;padding:34px 22px 30px;text-align:center}
    .done .ok{width:64px;height:64px;margin:0 auto 12px;border-radius:50%;background:#e6f6ef;color:#157347;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800}
    .done h2{font-size:1.1rem;color:#12302a}
    .done p{margin-top:6px;font-size:.84rem;color:#5f7f79;line-height:1.55}
  </style>
</head>
<body>
  <div class="card">
    <div class="head">
      <h1>Send your prescription</h1>
      <p>Photo-forward your Rx to the pharmacy counter. Take a clear, well-lit photo of the whole prescription.</p>
    </div>
    <div class="body" id="stepForm">
      <div class="drop" id="drop" role="button" tabindex="0">
        <div class="ic">📄</div>
        <b>Tap to take a photo / choose from gallery</b>
        <span>JPEG or PNG · up to 6 MB</span>
        <input type="file" id="file" accept="image/*" hidden>
      </div>
      <div class="prev" id="prev"><img id="img" alt="Prescription preview"></div>
      <label class="f" for="phone">Your mobile number (optional)</label>
      <input type="tel" id="phone" inputmode="numeric" maxlength="15" placeholder="So the cashier can match your name">
      <div class="hint">Voluntary — it only helps the counter find your customer record faster.</div>
      <button class="send" id="send" disabled>Send to the counter</button>
      <div class="err" id="err"></div>
    </div>
    <div class="done" id="stepDone">
      <div class="ok">✓</div>
      <h2>Received at the counter</h2>
      <p>Your prescription photo has reached the pharmacy. Hand over the paper Rx when collecting — keep this screen if they ask.</p>
    </div>
  </div>
  <script>
    (function () {
      var file = document.getElementById('file');
      var drop = document.getElementById('drop');
      var prev = document.getElementById('prev');
      var img  = document.getElementById('img');
      var send = document.getElementById('send');
      var err  = document.getElementById('err');
      var data = null;
      drop.addEventListener('click', function () { file.click(); });
      file.addEventListener('change', function () {
        var f = file.files && file.files[0];
        if (!f) return;
        if (!/^image\//.test(f.type)) { err.style.display = 'block'; err.textContent = 'That is not a photo — choose an image file.'; return; }
        if (f.size > 6 * 1024 * 1024) { err.style.display = 'block'; err.textContent = 'Photo larger than 6 MB — retake it a little closer.'; return; }
        var rd = new FileReader();
        rd.onload = function () { data = rd.result; img.src = rd.result; prev.style.display = 'block'; send.disabled = false; err.style.display = 'none'; };
        rd.readAsDataURL(f);
      });
      send.addEventListener('click', function () {
        if (!data) return;
        send.disabled = true; send.textContent = 'Sending…';
        fetch('api/v1/rx-inbox.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'upload', image: data, phone: document.getElementById('phone').value.trim() })
        }).then(function (r) { return r.json(); }).then(function (j) {
          if (j && (j.ok || j.status === 'ok' || (j.data && j.data.received))) {
            document.getElementById('stepForm').style.display = 'none';
            document.getElementById('stepDone').style.display = 'block';
          } else throw new Error((j && j.error) || 'Could not send — check the network and retry.');
        }).catch(function (e) {
          send.disabled = false; send.textContent = 'Send to the counter';
          err.style.display = 'block';
          err.textContent = String(e.message || e);
        });
      });
    })();
  </script>
</body>
</html>
