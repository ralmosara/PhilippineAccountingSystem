import { createFileRoute, redirect } from '@tanstack/react-router';

import { IssueSalesInvoicePage } from '@/modules/sales/pages/IssueSalesInvoicePage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/invoices/new')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.create')) {
            throw redirect({ to: '/' });
        }
    },
    component: IssueSalesInvoicePage,
});
