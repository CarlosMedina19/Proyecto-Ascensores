import { describe, expect, it } from 'vitest';
import { documentoImpresionQr, escaparHtml } from '../utilidades/html';

describe('escaparHtml', () => {
  it('convierte los caracteres especiales de HTML en texto', () => {
    expect(escaparHtml(`<img src=x onerror="alert('x')">&`)).toBe('&lt;img src=x onerror=&quot;alert(&#39;x&#39;)&quot;&gt;&amp;');
  });

  it('acepta números, null y undefined', () => {
    expect(escaparHtml(42)).toBe('42');
    expect(escaparHtml(null)).toBe('');
    expect(escaparHtml(undefined)).toBe('');
  });
});

describe('documentoImpresionQr', () => {
  const svg = '<svg viewBox="0 0 10 10"><path d="M0 0h10v10H0z"/></svg>';

  it('un código de ascensor malicioso se muestra como texto y no se ejecuta', () => {
    const malicioso = '<img src=x onerror="opener.sessionStorage">';
    const html = documentoImpresionQr({ codigo: malicioso, url: 'https://sigma.test/reportar/abc', svg });

    expect(html).not.toContain('<img');
    expect(html).toContain('&lt;img src=x onerror=&quot;opener.sessionStorage&quot;&gt;');

    // Al interpretarlo, el código queda como texto en el título y no crea elementos.
    const documento = new DOMParser().parseFromString(html, 'text/html');
    expect(documento.querySelector('img')).toBeNull();
    expect(documento.querySelector('h1').textContent).toBe(`¿Falla en el ascensor ${malicioso}?`);
  });

  it('escapa también la URL', () => {
    const html = documentoImpresionQr({ codigo: 'ASC-001', url: 'https://sigma.test/"><script>x()</script>', svg });
    expect(html).not.toContain('<script');
  });

  it('conserva el QR y no incluye scripts', () => {
    const html = documentoImpresionQr({ codigo: 'ASC-001', url: 'https://sigma.test/reportar/abc', svg });
    expect(html).toContain(svg);
    expect(html).toContain('¿Falla en el ascensor ASC-001?');
    expect(html).not.toMatch(/<script/i);
  });
});
