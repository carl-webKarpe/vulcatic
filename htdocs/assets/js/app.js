// Shared JavaScript for every page.

// Show / hide password buttons: <button data-toggle-password="inputId">
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.togglePassword);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
  });
});

// Stop double submits on slow connections.
document.querySelectorAll('form[data-once]').forEach((form) => {
  form.addEventListener('submit', () => {
    form.querySelectorAll('button[type=submit]').forEach((b) => {
      b.disabled = true;
      b.classList.add('opacity-60');
    });
  });
});

// Hide a field's error message as soon as the user starts fixing it.
document.querySelectorAll('form input').forEach((input) => {
  input.addEventListener('input', () => {
    const wrapper = input.closest('div:has(> p.text-red-600), fieldset');
    wrapper?.querySelectorAll(':scope > p.text-red-600').forEach((p) => p.remove());
  });
});
