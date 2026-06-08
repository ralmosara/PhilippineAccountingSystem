import { createFileRoute, redirect } from '@tanstack/react-router';

import { SalesInvoicesPage } from '@/modules/sales/pages/SalesInvoicesPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/invoices/')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: SalesInvoicesPage,
});
