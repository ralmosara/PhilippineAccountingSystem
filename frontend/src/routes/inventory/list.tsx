import { createFileRoute, redirect } from '@tanstack/react-router';

import { InventoryListPage } from '@/modules/inventory/pages/InventoryListPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/inventory/list')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'inventory.list.generate')) {
            throw redirect({ to: '/' });
        }
    },
    component: InventoryListPage,
});
