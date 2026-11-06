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

  // Os vídeos usam controles nativos do navegador, sem depender de JavaScript local.
})();
