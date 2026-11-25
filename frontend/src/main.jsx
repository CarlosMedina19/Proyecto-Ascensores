import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import LimiteErrores, { ErrorGeneral } from './componentes/LimiteErrores';
import './estilos/global.css';

createRoot(document.getElementById('raiz')).render(
  <StrictMode>
    <LimiteErrores alternativa={<ErrorGeneral />}>
      <App />
    </LimiteErrores>
  </StrictMode>,
);
