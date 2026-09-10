import { createRoot } from 'react-dom/client';
import { CartIsland } from './CartIsland';
import type { MenuPayload } from './types';

const mount = document.getElementById('cart-island');
const dataScript = document.getElementById('menu-data');

if (mount && dataScript?.textContent) {
    const menu: MenuPayload = JSON.parse(dataScript.textContent);

    createRoot(mount).render(
        <CartIsland
            slug={mount.dataset.slug ?? menu.establishment.slug}
            isOpen={mount.dataset.open === '1'}
            menu={menu}
        />,
    );
}
