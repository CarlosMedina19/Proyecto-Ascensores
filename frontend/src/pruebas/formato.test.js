import { describe, expect, it } from 'vitest';
import { fecha, humanizar, iniciales, nombreRol, numero } from '../utilidades/formato';

describe('utilidades de formato', () => {
  it('convierte identificadores en texto legible', () => {
    expect(humanizar('cambio_estado')).toBe('Cambio estado');
    expect(humanizar('alta_equipo')).toBe('Alta equipo');
  });

  it('obtiene las iniciales del nombre', () => {
    expect(iniciales('María Fernanda López')).toBe('MF');
    expect(iniciales('')).toBe('');
  });

  it('muestra el nombre de los roles en español', () => {
    expect(nombreRol('tecnico')).toBe('Técnico');
    expect(nombreRol('auxiliar_bodega')).toBe('Auxiliar bodega');
  });

  it('formatea números y valores vacíos', () => {
    expect(numero(1000, ' kg')).toMatch(/1[.\s]?000 kg/);
    expect(numero(null)).toBe('—');
  });

  it('interpreta fechas AAAA-MM-DD en la zona local', () => {
    expect(fecha('2026-01-05')).toMatch(/05/);
    expect(fecha(null)).toBe('—');
  });
});
