import { createFileRoute, redirect } from '@tanstack/react-router';

import { CustomersPage } from '@/modules/sales/pages/CustomersPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/customers/')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: CustomersPage,
});
