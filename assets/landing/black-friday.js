(() => {
  'use strict';
  // Barreiras à cópia casual. Imagens públicas continuam acessíveis ao navegador;
  // isso não é DRM e não impede capturas de tela ou acesso ao arquivo original.
  const imageTarget = target => target instanceof Element && target.closest('img, [data-lightbox]');
  document.addEventListener('contextmenu', event => {
    if (imageTarget(event.target)) event.preventDefault();
  });
  document.addEventListener('dragstart', event => {
    if (imageTarget(event.target)) event.preventDefault();
  });
  // Galeria: links continuam abrindo a imagem original se JavaScript estiver desativado.
  const dialog = document.getElementById('image-dialog');
  const triggers = [...document.querySelectorAll('[data-lightbox]')];
  const images = [...new Map(triggers.map(a => [a.href, a])).values()];
  let imageIndex = 0;
  function showImage(index) {
    imageIndex = (index + images.length) % images.length;
    const link = images[imageIndex];
    const image = document.getElementById('lightbox-image');
    image.src = link.href;
    image.alt = link.dataset.title;
    document.getElementById('image-dialog-title').textContent = link.dataset.title;
    document.querySelector('[data-lightbox-count]').textContent = (imageIndex + 1) + ' de ' + images.length;
  }
  triggers.forEach(link => link.addEventListener('click', event => {
    if (typeof dialog.showModal !== 'function') return;
    event.preventDefault();
    showImage(images.findIndex(a => a.href === link.href));
    dialog.showModal();
  }));
  document.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());
  document.querySelector('[data-lightbox-prev]').addEventListener('click', () => showImage(imageIndex - 1));
  document.querySelector('[data-lightbox-next]').addEventListener('click', () => showImage(imageIndex + 1));
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
  document.addEventListener('keydown', event => {
    if (dialog.open && ['ArrowLeft', 'ArrowRight'].includes(event.key)) {
      event.preventDefault();
      showImage(imageIndex + (event.key === 'ArrowRight' ? 1 : -1));
    }
    if (!dialog.open && event.key === 'Escape') document.querySelector('.mobile-menu').open = false;
  });
  document.querySelectorAll('.mobile-menu a').forEach(a => a.addEventListener('click', () => {
    document.querySelector('.mobile-menu').open = false;
  }));

  // A mesma amostra de fornecedores da página original, conferida no seed do VTD.
  const tabs = [...document.querySelectorAll('.sample-tabs [role=tab]')];
  const rows = [...document.querySelectorAll('.sample-row')];
  function selectTab(tab, focus) {
    tabs.forEach(item => {
      item.setAttribute('aria-selected', String(item === tab));
      item.tabIndex = item === tab ? 0 : -1;
    });
    let count = 0;
    rows.forEach(row => {
      row.hidden = tab.dataset.filter !== 'todos' && !row.dataset.groups.split(' ').includes(tab.dataset.filter);
      if (!row.hidden) count++;
    });
    document.getElementById('sample-results').setAttribute('aria-labelledby', tab.id);
    document.querySelector('.sample-count').textContent = count + ' registros nesta amostra · Base com 234 fornecedores';
    if (focus) tab.focus();
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => selectTab(tab, false));
    tab.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      if (next !== undefined) { event.preventDefault(); selectTab(tabs[next], true); }
    });
  });

  // Controles VTD por meio da API oficial. A área do vídeo permanece íntegra.
  // Não utilizamos modestbranding/showinfo: esses parâmetros foram descontinuados.
  const stage = document.getElementById('matheus-stage');
  const launch = document.getElementById('matheus-launch');
  const play = document.querySelector('[data-player-play]');
  const mute = document.querySelector('[data-player-mute]');
  const seek = document.querySelector('[data-player-seek]');
  const time = document.querySelector('[data-player-time]');
  const status = document.querySelector('[data-player-status]');
  let player, started = false, timer, apiPromise;
  const formatTime = value => {
    const seconds = Math.max(0, Math.floor(Number(value) || 0));
    return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
  };
  function loadAPI() {
    if (window.YT?.Player) return Promise.resolve();
    if (apiPromise) return apiPromise;
    apiPromise = new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('player-unavailable')), 15000);
      window.onYouTubeIframeAPIReady = () => { clearTimeout(timeout); resolve(); };
      const script = document.createElement('script');
      script.src = 'https://www.youtube.com/iframe_api';
      script.async = true;
      script.onerror = () => { clearTimeout(timeout); reject(new Error('player-unavailable')); };
      document.head.append(script);
    });
    return apiPromise;
  }
  function icon(name) {
    return '<svg class="player-icon" aria-hidden="true"><use href="assets/icons/vtd-player.svg#' + name + '"></use></svg>';
  }
  function sync() {
    if (!player?.getPlayerState) return;
    const playing = player.getPlayerState() === 1;
    const muted = player.isMuted();
    play.innerHTML = icon(playing ? 'pause' : 'play');
    play.setAttribute('aria-label', playing ? 'Pausar vídeo' : 'Reproduzir vídeo');
    mute.textContent = muted ? 'Ativar som' : 'Silenciar';
    mute.setAttribute('aria-label', muted ? 'Ativar som' : 'Silenciar vídeo');
    mute.setAttribute('aria-pressed', String(!muted));
    const duration = player.getDuration() || 0;
    const current = player.getCurrentTime() || 0;
    seek.max = duration || 1;
    if (document.activeElement !== seek) seek.value = current;
    seek.setAttribute('aria-valuetext', formatTime(current) + ' de ' + formatTime(duration));
    time.textContent = formatTime(current) + (duration ? ' / ' + formatTime(duration) : '');
  }
  function unavailable() {
    clearInterval(timer);
    play.disabled = true; mute.disabled = true; seek.disabled = true;
    status.textContent = 'Use o link abaixo para assistir ao vídeo original.';
  }
  async function startPlayer() {
    if (started) return;
    started = true;
    status.textContent = 'Carregando apresentação…';
    try {
      await loadAPI();
      const iframe = document.createElement('iframe');
      iframe.id = 'matheus-embed';
      const url = new URL('https://www.youtube-nocookie.com/embed/Z13Tw3LQ97E');
      Object.entries({ autoplay: 1, mute: 1, controls: 0, playsinline: 1, rel: 0, enablejsapi: 1, iv_load_policy: 3 }).forEach(([key, value]) => url.searchParams.set(key, value));
      if (/^https?:$/.test(location.protocol)) url.searchParams.set('origin', location.origin);
      iframe.src = url.href;
      iframe.title = 'Apresentação do VTD com Matheus';
      iframe.allow = 'autoplay; encrypted-media; picture-in-picture; web-share';
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      iframe.allowFullscreen = true;
      stage.replaceChildren(iframe);
      player = new YT.Player(iframe.id, { events: {
        onReady(event) {
          event.target.mute();
          event.target.playVideo();
          play.disabled = false; mute.disabled = false; seek.disabled = false;
          status.textContent = 'Vídeo sem som. Ative o áudio quando quiser.';
          timer = setInterval(sync, 500);
          sync();
        },
        onStateChange: sync,
        onError: unavailable,
        onAutoplayBlocked() { status.textContent = 'Toque no play para iniciar a apresentação.'; }
      } });
    } catch { started = false; unavailable(); }
  }
  launch.addEventListener('click', startPlayer);
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting)) { observer.disconnect(); startPlayer(); }
    }, { rootMargin: '100px' });
    observer.observe(stage);
  }
  play.addEventListener('click', () => {
    if (player.getPlayerState() === 1) player.pauseVideo(); else player.playVideo();
    sync();
  });
  mute.addEventListener('click', () => {
    if (player.isMuted()) player.unMute(); else player.mute();
    status.textContent = player.isMuted() ? 'Vídeo sem som. Ative o áudio quando quiser.' : 'Som ativado. Você pode silenciar a qualquer momento.';
    sync();
  });
  seek.addEventListener('input', () => { if (player?.seekTo) player.seekTo(Number(seek.value), true); });
  document.querySelector('[data-player-full]').addEventListener('click', async () => {
    const wrapper = document.getElementById('matheus-player');
    try {
      if (document.fullscreenElement) await document.exitFullscreen();
      else if (wrapper.requestFullscreen) await wrapper.requestFullscreen();
      else status.textContent = 'Use o link abaixo para ampliar o vídeo original.';
    } catch { status.textContent = 'Use o link abaixo para ampliar o vídeo original.'; }
  });
})();
