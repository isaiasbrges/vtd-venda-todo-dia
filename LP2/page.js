const gallery = document.querySelector('#telas');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

document.querySelectorAll('[data-move]').forEach(button => {
  button.addEventListener('click', () => {
    const first = gallery.children[0];
    const next = gallery.children[1];
    const step = next.offsetLeft - first.offsetLeft;
    gallery.scrollBy({
      left: step * Number(button.dataset.move),
      behavior: reducedMotion.matches ? 'auto' : 'smooth'
    });
  });
});
