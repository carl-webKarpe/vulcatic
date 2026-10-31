// Landing page motion: intro, scroll reveals, the phone rescue scene and
// the small interactive bits. Everything degrades to a static page.
(() => {
  const root = document.documentElement;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const finePointer = matchMedia('(hover: hover) and (pointer: fine)').matches;
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  // ---------- Intro: play once per visit, click to skip ----------
  const intro = document.getElementById('intro');
  let started = false;
  function finishIntro() {
    if (started) return;
    started = true;
    try { sessionStorage.setItem('vulcatic-intro', '1'); } catch (e) {}
    intro.classList.add('intro-out');
    setTimeout(() => { root.classList.add('no-intro'); intro.remove(); }, 750);
    setTimeout(startPage, 250);
  }
  if (root.classList.contains('no-intro')) {
    intro.remove();
    started = true;
    startPage();
  } else {
    setTimeout(finishIntro, 2300);
    intro.addEventListener('click', finishIntro);
  }

  function startPage() {
    revealOnScroll();
    rotateWords();
    runScene();
  }

  // ---------- Scroll reveal + number counters ----------
  function revealOnScroll() {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        entry.target.querySelectorAll('[data-count]').forEach(countUp);
        io.unobserve(entry.target);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
  }

  function countUp(el) {
    const end = Number(el.dataset.count);
    if (reduced || end === 0) return;
    const t0 = performance.now();
    const tick = (now) => {
      const p = Math.min((now - t0) / 1200, 1);
      el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  // ---------- Hero headline word rotator ----------
  function rotateWords() {
    const box = document.querySelector('.rotator');
    if (!box || reduced) return;
    const words = ['Flat tire?', 'Busted tube?', 'Low on air?', 'Stuck on the road?'];
    let i = 0;
    setInterval(() => {
      const old = box.querySelector('.rotator-word');
      old.classList.add('out');
      setTimeout(() => {
        i = (i + 1) % words.length;
        const next = document.createElement('span');
        next.className = 'rotator-word block in';
        next.textContent = words[i];
        old.replaceWith(next);
      }, 420);
    }, 2800);
  }

  // ---------- Phone: the rescue, on a loop ----------
  async function runScene() {
    const phone = document.querySelector('.phone');
    if (!phone) return;
    const status = document.getElementById('scene-status');
    const eta = document.getElementById('scene-eta');
    const bar = document.getElementById('scene-bar');
    const motion = document.getElementById('mover-motion');
    const setStatus = async (text) => {
      status.classList.add('swap');
      await sleep(220);
      status.textContent = text;
      status.classList.remove('swap');
    };

    if (reduced) {
      phone.classList.add('s-match', 's-route', 's-card');
      status.textContent = 'Jose accepted · on the way';
      bar.style.width = '60%';
      return;
    }

    while (true) {
      if (document.hidden) { await sleep(1000); continue; }
      phone.className = phone.className.replace(/\bs-\w+/g, '').trim();
      bar.style.width = '0';
      eta.textContent = '4 min';
      await setStatus('Finding help near you…');
      await sleep(1600);
      phone.classList.add('s-match');
      await setStatus('Vulcator matched!');
      await sleep(600);
      phone.classList.add('s-route');
      await sleep(800);
      phone.classList.add('s-card');
      await setStatus('Jose accepted · on the way');
      motion.beginElement();
      for (let m = 4; m >= 1; m--) {
        eta.textContent = m + ' min';
        bar.style.width = ((4 - m + 1) / 4) * 100 + '%';
        await sleep(1000);
      }
      eta.textContent = 'Here';
      phone.classList.add('s-arrive');
      await setStatus('Jose has arrived');
      await sleep(1300);
      phone.classList.add('s-fixed');
      await setStatus('Tire fixed! Paid ₱150 ✓');
      await sleep(2600);
    }
  }

  // ---------- Nav: solid on scroll, mobile menu ----------
  const nav = document.getElementById('nav');
  const onScroll = () => {
    nav.classList.toggle('is-scrolled', scrollY > 10);
    updateSteps();
  };
  addEventListener('scroll', onScroll, { passive: true });

  const menuBtn = document.getElementById('menu-btn');
  const menu = document.getElementById('mobile-menu');
  menuBtn.addEventListener('click', () => {
    const open = menuBtn.getAttribute('aria-expanded') !== 'true';
    menuBtn.setAttribute('aria-expanded', String(open));
    menu.classList.toggle('is-open', open);
    nav.classList.add('is-scrolled');
  });
  menu.querySelectorAll('a[href^="#"]').forEach((a) => a.addEventListener('click', () => menuBtn.click()));

  // ---------- How it works: line fills as you scroll ----------
  const stepsBox = document.querySelector('.steps');
  const fill = document.getElementById('steps-fill');
  const steps = [...document.querySelectorAll('.step')];
  function updateSteps() {
    if (!stepsBox) return;
    const r = stepsBox.getBoundingClientRect();
    const p = Math.min(Math.max((innerHeight * 0.75 - r.top) / (r.height + innerHeight * 0.25), 0), 1);
    fill.style.setProperty('--p', p.toFixed(3));
    steps.forEach((s, i) => s.classList.toggle('is-lit', p >= (i + 0.35) / steps.length));
  }
  onScroll();

  // ---------- Signal / no signal switch ----------
  const sw = document.querySelector('.mode-switch');
  sw?.querySelectorAll('[data-mode]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.mode;
      sw.classList.toggle('is-offline', mode === 'offline');
      sw.querySelectorAll('[data-mode]').forEach((b) => {
        const on = b === btn;
        b.setAttribute('aria-selected', String(on));
        b.classList.toggle('text-white/70', !on);
      });
      document.querySelectorAll('[data-mode-panel]').forEach((p) =>
        p.classList.toggle('is-active', p.dataset.modePanel === mode));
    });
  });

  if (!finePointer || reduced) return;

  // ---------- Mouse: hero parallax ----------
  const hero = document.getElementById('hero');
  const layers = hero.querySelectorAll('[data-depth]');
  hero.addEventListener('pointermove', (e) => {
    const x = e.clientX / innerWidth - 0.5;
    const y = e.clientY / innerHeight - 0.5;
    layers.forEach((l) => { const d = Number(l.dataset.depth); l.style.translate = `${x * d}px ${y * d}px`; });
  });

  // ---------- Mouse: 3D tilt + glow ----------
  document.querySelectorAll('[data-tilt]').forEach((el) => {
    const max = el.classList.contains('glow-card') ? 5 : 9;
    el.addEventListener('pointermove', (e) => {
      const r = el.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width;
      const py = (e.clientY - r.top) / r.height;
      el.style.transform = `perspective(900px) rotateY(${(px - 0.5) * max * 2}deg) rotateX(${(0.5 - py) * max * 2}deg)`;
      el.style.setProperty('--mx', px * 100 + '%');
      el.style.setProperty('--my', py * 100 + '%');
    });
    el.addEventListener('pointerleave', () => { el.style.transform = ''; });
  });

  // ---------- Mouse: magnetic buttons ----------
  document.querySelectorAll('.magnetic').forEach((b) => {
    b.addEventListener('pointermove', (e) => {
      const r = b.getBoundingClientRect();
      b.style.translate = `${(e.clientX - r.left - r.width / 2) * 0.18}px ${(e.clientY - r.top - r.height / 2) * 0.3}px`;
    });
    b.addEventListener('pointerleave', () => { b.style.translate = ''; });
  });
})();
