// js/script.js
// PKL Journey - Interactive Script & Status Animation

document.addEventListener('DOMContentLoaded', () => {
  // Elements
  const formLogin = document.getElementById('form-login');
  const loginStatus = document.getElementById('login-status');

  const loadingOverlay = document.getElementById('journey-loading-overlay');
  const statusStepText = document.getElementById('status-step-text');
  const statusStepSub = document.getElementById('status-step-sub');

  // Show / Hide Password Toggles
  const toggleButtons = document.querySelectorAll('.password-toggle-btn');
  toggleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const isPassword = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPassword ? 'text' : 'password');

      // Toggle Eye Icon SVG
      if (isPassword) {
        // Eye-slash / Hide icon
        btn.innerHTML = `
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
          </svg>`;
      } else {
        // Eye / Show icon
        btn.innerHTML = `
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>`;
      }
    });
  });

  // Helper: Sleep utility
  const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

  // Helper: Show Step Animation in Overlay
  function showOverlay(initialText, initialSub = 'Memproses permintaan...') {
    if (!loadingOverlay) return;
    if (statusStepText) statusStepText.innerText = initialText;
    if (statusStepSub) statusStepSub.innerText = initialSub;
    loadingOverlay.classList.add('active');
  }

  function hideOverlay() {
    if (!loadingOverlay) return;
    loadingOverlay.classList.remove('active');
  }

  // Helper: Show Error Alert
  function showError(box, message) {
    if (!box) return;
    box.className = 'status-box error';
    box.innerText = message;
    box.style.display = 'block';
  }

  function showSuccess(box, message) {
    if (!box) return;
    box.className = 'status-box success';
    box.innerText = message;
    box.style.display = 'block';
  }

  function clearStatus(box) {
    if (!box) return;
    box.style.display = 'none';
    box.innerText = '';
  }

  // -------------------------------------------------
  // LOGIN FORM SUBMISSION
  // Status Animation:
  // Checking account... -> Verifying access... -> Journey ready ✓
  // -------------------------------------------------
  if (formLogin) {
    formLogin.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearStatus(loginStatus);

      const formData = new FormData(formLogin);
      const submitBtn = formLogin.querySelector('button[type="submit"]');

      // Client-side check: NIS/Username and password
      const username = formData.get('username')?.trim();
      const password = formData.get('password');

      if (!username || !password) {
        showError(loginStatus, 'Silakan masukkan NIS / Username dan password.');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      showOverlay('Checking account...', 'Memeriksa kredensial akun...');

      try {
        // Step 1: Checking account...
        await sleep(550);
        if (statusStepText) statusStepText.innerText = 'Verifying access...';
        if (statusStepSub) statusStepSub.innerText = 'Memvalidasi hak akses...';

        const response = await fetch('login.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          // Step 2: Journey ready ✓
          await sleep(550);
          if (statusStepText) statusStepText.innerText = 'Journey ready ✓';
          if (statusStepSub) statusStepSub.innerText = 'Mengarahkan ke Dashboard ' + (result.user?.role || '') + '...';

          await sleep(650);
          window.location.href = result.redirect || 'dashboard/murid.php';
        } else {
          hideOverlay();
          if (submitBtn) submitBtn.disabled = false;
          showError(loginStatus, result.message || 'Login gagal, periksa kembali data Anda.');
        }
      } catch (err) {
        hideOverlay();
        if (submitBtn) submitBtn.disabled = false;
        showError(loginStatus, 'Terjadi kendala jaringan/server. Pastikan MySQL sudah menyala.');
      }
    });
  }
});
