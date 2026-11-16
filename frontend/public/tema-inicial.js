// Aplica el tema guardado antes de pintar para evitar un destello.
// Va en un archivo aparte (y no dentro de index.html) para que la política CSP pueda prohibir los
// scripts en línea (script-src 'self').
try {
  const tema = localStorage.getItem('sigma.tema');
  if (tema) document.documentElement.setAttribute('data-tema', tema);
} catch (e) {}
