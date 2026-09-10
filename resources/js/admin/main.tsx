import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { AdminApp } from './App';

const mount = document.getElementById('admin');

if (mount) {
    createRoot(mount).render(
        <StrictMode>
            <AdminApp />
        </StrictMode>,
    );
}
