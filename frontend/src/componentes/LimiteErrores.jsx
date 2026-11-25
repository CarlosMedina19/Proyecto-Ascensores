import { Component } from 'react';

/**
 * Contiene un error de render (o un fragmento que no se pudo descargar, por ejemplo tras un despliegue) para que
 * no deje la pantalla en blanco: muestra `alternativa` en lugar de sus hijos.
 */
export default class LimiteErrores extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error) {
    console.error('Error contenido por LimiteErrores:', error);
  }

  render() {
    if (this.state.error) {
      return typeof this.props.alternativa === 'function' ? this.props.alternativa(this.state.error) : (this.props.alternativa ?? null);
    }
    return this.props.children;
  }
}

/** Pantalla completa para un error que no se pudo contener en una sección. */
export function ErrorGeneral() {
  return (
    <main className="error-general" role="alert">
      <h1>Algo salió mal</h1>
      <p>La página no se pudo mostrar. Si SIGMA se acaba de actualizar, recargarla lo resuelve.</p>
      <button type="button" className="boton boton--primario" onClick={() => window.location.reload()}>
        Recargar
      </button>
    </main>
  );
}
