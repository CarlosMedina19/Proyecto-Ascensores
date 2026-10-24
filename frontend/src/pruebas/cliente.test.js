import { afterEach, describe, expect, it, vi } from 'vitest';

describe('cliente de la API', () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    vi.resetModules();
  });

  // En producción el panel y la API comparten dominio y VITE_API_URL es relativa ("/api/v1").
  it('con una dirección relativa envía la solicitud al mismo dominio', async () => {
    vi.stubEnv('VITE_API_URL', '/api/v1');
    const llamada = vi.fn().mockResolvedValue(new Response(JSON.stringify({ datos: [] }), { status: 200 }));
    vi.stubGlobal('fetch', llamada);
    const { api } = await import('../api/cliente');

    await api.obtener('/emergencias', { estado: 'abiertas' });

    expect(llamada).toHaveBeenCalledOnce();
    expect(llamada.mock.calls[0][0]).toBe(`${window.location.origin}/api/v1/emergencias?estado=abiertas`);
  });
});
