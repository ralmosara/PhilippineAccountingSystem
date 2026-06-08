import { createFileRoute, redirect } from '@tanstack/react-router';

import { StockMovementsPage } from '@/modules/inventory/pages/StockMovementsPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/inventory/stock-movements')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'inventory.movements.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: StockMovementsPage,
});
