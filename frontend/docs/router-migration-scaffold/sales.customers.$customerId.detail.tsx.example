import { createFileRoute, redirect, useParams } from '@tanstack/react-router';

import { CustomerDetailPage } from '@/modules/sales/pages/CustomerDetailPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/customers/$customerId/detail')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: CustomerDetailRoute,
});

function CustomerDetailRoute() {
    const { customerId } = useParams({ from: '/sales/customers/$customerId/detail' });
    return <CustomerDetailPage customerId={customerId} />;
}
