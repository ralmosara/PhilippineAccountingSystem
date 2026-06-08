import { createFileRoute, redirect } from '@tanstack/react-router';

import { ItemsPage } from '@/modules/inventory/pages/ItemsPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/inventory/items')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'inventory.items.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: ItemsPage,
});
