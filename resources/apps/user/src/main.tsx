import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles/theme.css';

const el = document.getElementById('root');
if (el) {
  createRoot(el).render(
    <StrictMode>
      <App />
    </StrictMode>
  );
}
